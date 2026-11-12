# Foresight Radar — Comprehensive Robustness & Reliability Audit (`ROBUSTNESS_RELIABILITY_AUDIT.md`)

**Audit Date:** 2026-10-23  
**Repository:** `https://github.com/roozylabs27/foresight-radar`  
**Audited Branch / Commit:** `development` (`83f532a`)  
**Stack Baseline:** Laravel 11.57.0 (PHP 8.3.36), MySQL 8.0, Redis, React 19.3.1, Inertia.js 2.3.29

---

## 1. Executive Summary & Reliability Posture

Foresight Radar incorporates several strong reliability patterns introduced in recent sprints:
- **Pessimistic Locking in Scenario Narrative Generation:** `ScenarioController::generate()` uses `DB::transaction()` with `Scenario::whereKey($scenario->id)->lockForUpdate()->first()` and `GenerateScenarioNarrative` implements `ShouldBeUnique` to prevent duplicate concurrent LLM narrative jobs.
- **Deterministic Offline Fallback for Signal Extraction:** `SignalExtractionService` gracefully degrades to deterministic keyword extraction (`EXTRACTION_RULES`) when `StructuredLlmClient` is unavailable or fails (`rate_limited`, `timeout`, `overloaded`, `invalid_json`), and verifies all LLM quotes verbatim against the source document via `EvidenceVerifier`.
- **Timeout & Retry Boundaries on Outbound LLM Calls:** `AnthropicStructuredClient` and `OpenAiCompatibleClient` enforce explicit connect timeouts (`10s`), request timeouts (`120s`), and bounded retries (`max_retries = 1`) on `429`/`5xx`/connection errors.
- **AbortController Cleanup on Frontend Data Fetching:** `Dashboard.jsx`, `Radar.jsx`, `PrioritizingChart.jsx`, and `use-server-table.js` cancel in-flight Axios requests via `AbortController` on unmount or filter change.

However, our audit identified **2 High-severity**, **3 Medium-severity**, and **1 Low-severity** robustness and reliability defects:

| ID | Severity | Confidence | Title | Category |
|---|---|---|---|---|
| `REL-001` | **High** | Confirmed | GitHub Actions CI PHP Version Mismatch (`8.2` vs `8.3.36` Lockfile), Missing `development` Branch Trigger, and Missing `npm run typecheck` | Reliability / CI-CD |
| `REL-002` | **High** | Confirmed | Unhandled Null/Empty Array Dereferences in `UserResource`, `UserController`, `ApprovalItemsResource`, and `ClosedItemsResource` Cause HTTP 500 Crashes | Robustness / Null-Safety |
| `REL-003` | **Medium** | Confirmed | Race Condition in `SignalIngestionController::review` Due to Pre-Transaction Status Check Without Row Lock | Reliability / Concurrency |
| `REL-004` | **Medium** | Confirmed | Non-Atomic `firstOrNew` + `save` in `CrossImpactService::score` and Double `failed()` Invocation in `ExtractSignals` Queue Job | Reliability / Concurrency & Jobs |
| `REL-005` | **Medium** | Confirmed | `RegisteredListController::export_data` Breaks Dimension Sorting/Row-Merging via `lazy(100)` and Cannot Catch Mid-Stream Exceptions | Robustness / Streaming & Export |
| `REL-006` | **Low** | Confirmed | Split API Error Response Envelopes Across Controllers (`{"errors": "..."}` vs `{"statusCode": 500, "message": "..."}`) | Maintainability / API Contract |

---

## 2. Detailed Robustness & Reliability Findings

### `REL-001` — GitHub Actions CI PHP Version Mismatch (`8.2` vs `8.3.36` Lockfile), Missing `development` Branch Trigger, and Missing TypeScript Check

- **ID:** `REL-001`
- **Category:** `Reliability` / `Testing` / `Maintainability`
- **Title:** GitHub Actions CI PHP Version Mismatch (`8.2` vs `8.3.36` Lockfile), Missing `development` Branch Trigger, and Missing TypeScript Check in CI
- **Severity:** `High`
- **Confidence:** `Confirmed`
- **Affected Location:**
  - `.github/workflows/ci.yml` lines 5–7, 34, 114
  - `composer.json` lines 10, 64 (`"php": "^8.3"`, `"config.platform.php": "8.3.36"`)
- **Evidence:**
  ```yaml
  # .github/workflows/ci.yml:3-8
  on:
    push:
      branches: [ main, master, develop, staging ]
    pull_request:
      branches: [ main, master, develop, staging ]
  ...
  # .github/workflows/ci.yml:32-34 (and line 114)
      - name: Setup PHP 8.2
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
  ```
  Meanwhile, `composer.json` line 10 requires `"php": "^8.3"` and `composer.lock` locks platform PHP at `8.3.36` and Symfony 7.4 packages requiring `php >= 8.2/8.3`.
- **Failure or Attack Scenario:**
  1. Every CI run in GitHub Actions fails immediately at `composer install` because PHP 8.2 does not satisfy `"php": "^8.3"` and `config.platform.php: "8.3.36"`.
  2. Pushes to the primary active branch `development` (and `feature/**` branches) do not trigger `.github/workflows/ci.yml` at all because the branch list specifies `develop` instead of `development`.
  3. The `frontend-quality` job in `.github/workflows/ci.yml` (lines 72–99) runs `npm run build` and `npm audit --audit-level=critical` but omits `npm run typecheck` (`tsc --noEmit`).
- **Root Cause:** `.github/workflows/ci.yml` was not updated when the project upgraded from PHP 8.2 / Laravel 10 to PHP 8.3.36 / Laravel 11.57 and TypeScript.
- **Impact:**
  - **System & Operational Impact:** CI pipeline is completely broken on GitHub Actions (`100%` failure rate on `composer install`) and silently skipped on pushes to `development`.
  - **Likelihood:** Confirmed (`100%`).
- **Reproduction or Validation Method:**
  Inspect `.github/workflows/ci.yml` lines 34 and 114 (`php-version: '8.2'`) against `composer.json` line 10 (`"php": "^8.3"`).
- **Recommended Remediation:**
  1. Update `.github/workflows/ci.yml` lines 34 and 114 to `php-version: '8.3'`.
  2. Add `development` to `on.push.branches` and `on.pull_request.branches`.
  3. Add `- name: Run TypeScript Typecheck` (`run: npm run typecheck`) to the `frontend-quality` job in `.github/workflows/ci.yml` and to `./verify.sh`.
- **Required Tests:**
  - Verify `./verify.sh --quick` and GitHub Actions workflow syntax validation pass.
- **Dependencies and Effort:** None | Effort: `XS`

---

### `REL-002` — Unhandled Null/Empty Array Dereferences in `UserResource`, `UserController`, `ApprovalItemsResource`, and `ClosedItemsResource` Cause HTTP 500 Crashes

- **ID:** `REL-002`
- **Category:** `Robustness` / `API`
- **Title:** Unhandled Null/Empty Array Dereferences in `UserResource`, `UserController`, `ApprovalItemsResource`, and `ClosedItemsResource` Cause HTTP 500 Crashes
- **Severity:** `High`
- **Confidence:** `Confirmed`
- **Affected Location:**
  - `app/Http/Resources/UserResource.php` lines 22–23
  - `app/Http/Controllers/UserController.php` lines 22, 24
  - `app/Http/Resources/ApprovalItemsResource.php` lines 27, 31, 33, 34, 38
  - `app/Http/Resources/ClosedItemsResource.php` lines 27, 31, 33, 34, 38
  - `app/Models/DrivingForce.php` lines 53–66 (`created_by_user`, `pic_user`, `updated_by_user`)
- **Evidence:**
  ```php
  // app/Http/Resources/UserResource.php:22-23
  'role' => $this->roles[0]->display_name,
  'role_id' => $this->roles[0]->id,
  ```
  ```php
  // app/Http/Controllers/UserController.php:22-25
  if (auth()->user()->roles->pluck('name')[0] == 'developer') {
      $roles = Role::all();
  } elseif (auth()->user()->roles->pluck('name')[0] == 'super-admin') {
  ```
  ```php
  // app/Http/Resources/ApprovalItemsResource.php:31-38
  'pic' => $this->pic_user->name,
  'status' => $this->status,
  'time_horizon' => $this->rating->time_horizon->name,
  'priority' => $this->rating->priority->name,
  ...
  'status_action' => $status_action->code,
  ```
- **Failure or Attack Scenario:**
  1. **Roleless User Crashes User Management (`UserResource`):** When any user exists without a role (e.g., created via `POST /register`), a `developer` opening `/user-management` triggers `User::filter()`, which includes all users without a `whereHas('roles')` filter (lines 79–82 of `User.php`). `UserResource::toArray()` evaluates `$this->roles[0]->display_name` on an empty collection, throwing `Undefined array key 0` / `Attempt to read property "display_name" on null` and returning **HTTP 500**.
  2. **Soft-Deleted PIC User Crashes Approval & Closed Items Tables (`ApprovalItemsResource` / `ClosedItemsResource`):** `User` uses `SoftDeletes`, and `UserController::destroy()` soft-deletes users (`$user->delete()`). However, `DrivingForce::pic_user()` does not include `->withTrashed()`, and `ApprovalItemsResource` / `ClosedItemsResource` access `$this->pic_user->name` without the null-safe operator (`?->`). As soon as an admin deletes a user who was assigned as PIC on any driving force in Approval Items or Closed Items, `/approval-items/fetch-data` and `/closed-items/fetch-data` crash with **HTTP 500** for all users!
- **Root Cause:** Direct array indexing (`[0]`) and non-null-safe property chains (`->`) on nullable Eloquent relationships (`pic_user`, `dimension`, `time_horizon`, `priority`, `status_action`, `roles`).
- **Impact:**
  - **User & System Impact:** Complete outage (HTTP 500) of `/user-management`, `/approval-items`, and `/closed-items` when a user is soft-deleted or lacks a role.
  - **Likelihood:** High in normal operational lifecycle (deleting a former employee's user account immediately breaks Approval Items and Closed Items).
- **Reproduction or Validation Method:**
  1. Create a DrivingForce assigned to `$picUser`, complete its rating stages, then call `$picUser->delete()`.
  2. Request `GET /approval-items/fetch-data` -> returns `500 Internal Server Error` (`Attempt to read property "name" on null` at `ApprovalItemsResource.php:31`).
- **Recommended Remediation:**
  1. In `UserResource.php`, use `$this->roles->first()?->display_name` and `$this->roles->first()?->id`.
  2. In `UserController.php` lines 22–24 and `User.php` lines 79–83, use `$user->hasRole('developer')` / `$user->hasRole('super-admin')` instead of `roles->pluck('name')[0]`.
  3. Add `->withTrashed()` to `DrivingForce::pic_user()`, `created_by_user()`, and `updated_by_user()`, and use null-safe operators (`?->`) across `ApprovalItemsResource`, `ClosedItemsResource`, `StatusActionResource`, `TimeHorizonResource`, `RatingUrgencyResource`, and `OverallStatusResource`.
- **Required Tests:**
  - Feature test verifying `/approval-items/fetch-data` and `/closed-items/fetch-data` return `200 OK` when a driving force's PIC user has been soft-deleted.
  - Feature test verifying `/user-management/fetch-data` returns `200 OK` for a `developer` when a user with zero roles exists in `users`.
- **Dependencies and Effort:** Coordinate with `PERF-002` | Effort: `S`

---

### `REL-003` — Race Condition in `SignalIngestionController::review` Due to Pre-Transaction Status Check Without Row Lock

- **ID:** `REL-003`
- **Category:** `Reliability` / `Database`
- **Title:** Race Condition in `SignalIngestionController::review` Due to Pre-Transaction Status Check Without Row Lock
- **Severity:** `Medium`
- **Confidence:** `Confirmed`
- **Affected Location:**
  - `app/Http/Controllers/SignalIngestionController.php` lines 156–274 (`review`)
- **Evidence:**
  ```php
  // app/Http/Controllers/SignalIngestionController.php:156-163
  if ($action !== 'update' && $signal->review_status !== 'PENDING') {
      return response()->json([
          'errors' => 'Only pending signals can be accepted or rejected.',
      ], Response::HTTP_UNPROCESSABLE_ENTITY);
  }

  return DB::transaction(function () use ($signal, $validated, $action) {
  ```
- **Failure or Attack Scenario:** If an analyst double-clicks "Accept as New DF" (`accept_create`) or two analysts concurrently review the same `PENDING` signal, both requests read `$signal->review_status === 'PENDING'` via route model binding before entering `DB::transaction()`. Both transactions then execute `DrivingForce::create(...)`, creating duplicate `DrivingForce` and `DrivingForceRating` records from a single signal.
- **Root Cause:** Checking `$signal->review_status !== 'PENDING'` outside the transaction without acquiring a `SELECT ... FOR UPDATE` row lock (`lockForUpdate()`).
- **Impact:**
  - **Data Integrity Impact:** Duplicate `DrivingForce` records created from a single signal under concurrent/double submission.
  - **Likelihood:** Medium.
- **Reproduction or Validation Method:**
  Inspect `SignalIngestionController::review()` lines 156–163 and compare with `ScenarioController::generate()` lines 76–88 (which properly uses `lockForUpdate()` inside `DB::transaction()`).
- **Recommended Remediation:**
  Move the `review_status !== 'PENDING'` check inside `DB::transaction()` after re-fetching `$signal = Signal::whereKey($signal->id)->lockForUpdate()->firstOrFail();`.
- **Required Tests:**
  - Feature test verifying `SignalIngestionController::review` locks the signal row inside the transaction and rejects a second `accept_create` attempt with `422`.
- **Dependencies and Effort:** None | Effort: `XS`

---

### `REL-004` — Non-Atomic `firstOrNew` + `save` in `CrossImpactService::score` and Double `failed()` Execution in `ExtractSignals` Job

- **ID:** `REL-004`
- **Category:** `Reliability` / `Database` / `Robustness`
- **Title:** Non-Atomic `firstOrNew` + `save` in `CrossImpactService::score` Risks Unique Constraint Violation, and `ExtractSignals` Job Double-Invokes `failed()`
- **Severity:** `Medium`
- **Confidence:** `Confirmed`
- **Affected Location:**
  - `app/Services/CrossImpactService.php` lines 80–107 (`score`)
  - `app/Jobs/ExtractSignals.php` lines 53–69 (`handle` and `failed`)
- **Evidence:**
  ```php
  // app/Services/CrossImpactService.php:95-104
  $impact = CrossImpact::firstOrNew($keys);
  $impact->score = $score;
  $impact->rationale = $rationale !== null && trim($rationale) !== '' ? trim($rationale) : null;
  if (! $impact->exists) {
      $impact->created_by = $userId;
  }
  $impact->updated_by = $userId;
  $impact->save();
  ```
  ```php
  // app/Jobs/ExtractSignals.php:53-58
  try {
      $extraction->extract($this->source);
  } catch (Throwable $e) {
      $this->failed($e);
      throw $e;
  }
  ```
- **Failure or Attack Scenario:**
  1. In `CrossImpactService::score()`, if two requests concurrently score an empty cell `(source_force_id, target_force_id)`, both `firstOrNew()` calls see `exists === false` and both issue `INSERT INTO cross_impacts`, causing the second request to fail with a MySQL `1062 Duplicate entry` exception on `cross_impacts_source_target_unique` (`500 Internal Server Error`).
  2. In `ExtractSignals::handle()`, catching `Throwable $e`, calling `$this->failed($e)`, and then re-throwing `throw $e;` causes `failed($e)` to run **twice** when executed by a queue worker (once manually on line 56, and once by Laravel's queue worker handling the uncaught exception for `$tries = 1`). Furthermore, `ExtractSignals` does not implement `ShouldBeUnique`, unlike `GenerateScenarioNarrative`.
- **Root Cause:**
  1. Using `firstOrNew` + `save` outside a transaction instead of `DB::transaction` / `updateOrCreate` in `CrossImpactService::score()`.
  2. Manual `$this->failed($e)` invocation before `throw $e` in `ExtractSignals::handle()`.
- **Impact:**
  - **System Impact:** 500 errors on rapid Cross-Impact cell edits; duplicate error log entries and duplicate database writes on `ExtractSignals` job failures.
  - **Likelihood:** Medium.
- **Reproduction or Validation Method:**
  Run `ExtractSignals` through the queue worker when `SignalExtractionService::extract()` throws an exception and inspect `Log::error` calls — `'Signal extraction job failed'` is logged twice.
- **Recommended Remediation:**
  1. Wrap `CrossImpactService::score()` in `DB::transaction()` or use `CrossImpact::updateOrCreate(...)`.
  2. In `ExtractSignals`, implement `ShouldBeUnique` (keyed by `(string) $this->source->id`) and remove the manual `$this->failed($e)` call inside `handle()` (or keep `failed(Throwable $e)` solely for the queue worker hook while ensuring synchronous tests that call `handle()` directly are tested via `Bus::dispatchSync` / queue worker).
- **Required Tests:**
  - Unit/Feature test for `CrossImpactService::score()` idempotency and `ExtractSignals` failure handling.
- **Dependencies and Effort:** None | Effort: `XS`

---

### `REL-005` — `RegisteredListController::export_data` Breaks Dimension Sorting/Row-Merging via `lazy(100)` and Cannot Catch Mid-Stream Exceptions

- **ID:** `REL-005`
- **Category:** `Robustness` / `API` / `Frontend`
- **Title:** `RegisteredListController::export_data` Breaks Dimension Sorting/Row-Merging via `lazy(100)` and Cannot Catch Mid-Stream Exceptions
- **Severity:** `Medium`
- **Confidence:** `Confirmed`
- **Affected Location:**
  - `app/Http/Controllers/RegisteredListController.php` lines 68–96 (`export_data`)
  - `app/Services/ForesightReportingService.php` lines 139–175 (`getRegisteredListQuery`)
  - `resources/js/Components/OverallStatus.jsx` lines 91–102 (`exportWorkbook`)
- **Evidence:**
  ```php
  // app/Http/Controllers/RegisteredListController.php:71-85
  $query = $reportingService->getRegisteredListQuery();

  return response()->stream(function () use ($query) {
      echo '[';
      $first = true;
      foreach ($query->lazy(100) as $item) {
  ```
  ```javascript
  // resources/js/Components/OverallStatus.jsx:91-102
  // The xlsx rows merge the Dimension cells of consecutive rows, like the table.
  for (let index = 1; index <= rows.length; index++) {
      if (index === rows.length || rows[index].Dimension !== rows[start].Dimension) {
          if (index - start > 1) {
              merges.push({ s: { r: start + 1, c: 1 }, e: { r: index, c: 1 } });
          }
          start = index;
      }
  }
  ```
- **Failure or Attack Scenario:**
  1. `ForesightReportingService::getRegisteredListQuery()` applies `->orderBy(Dimension::select('name')...)` so rows are grouped by Dimension name. However, `Eloquent\Builder::lazy(100)` uses `chunkById()` under the hood, which orders results by `driving_force_ratings.id ASC` instead of the custom `Dimension.name` ordering! As a result, exported `.xlsx` workbooks have unsorted dimensions and broken vertical cell merges compared to the on-screen table.
  2. In `RegisteredListController::export_data()`, the `try { return response()->stream(...); } catch` block only wraps the creation of the `StreamedResponse` object, **not** the execution of the closure. If an exception occurs inside the closure after `echo '[';`, invalid truncated JSON (`[{"id":...`) is sent with a `200 OK` status header.
- **Root Cause:** Misuse of `$query->lazy(100)` (`chunkById`) on a query that requires custom `ORDER BY` semantics, and streaming JSON manually instead of using a bounded query (`limit(5000)->get()`) or `cursor()`.
- **Impact:**
  - **User Impact:** Exported Registered List `.xlsx` files do not group or merge rows by Dimension as intended.
  - **Likelihood:** Confirmed whenever driving forces across different dimensions are created out of alphabetical dimension order.
- **Reproduction or Validation Method:**
  Create 3 approved driving forces in dimensions `Technology`, `Social`, and `Technology` (in that ID order). Call `GET /visualization/registered-list/export-data` — the returned array is ordered `Technology, Social, Technology` (by ID) instead of `Social, Technology, Technology` (by Dimension name).
- **Recommended Remediation:**
  Replace `$query->lazy(100)` in `RegisteredListController::export_data()` with `$query->limit(2000)->get()` (or `$query->cursor()`, which preserves the query's `ORDER BY` clause on MySQL) and evaluate before streaming so any database exception is caught by `errorJsonResponse()` before headers are sent.
- **Required Tests:**
  - Feature test asserting `GET /visualization/registered-list/export-data` returns rows ordered by `dimension` name matching `get-data`.
- **Dependencies and Effort:** None | Effort: `XS`

---

### `REL-006` — Split API Error Response Envelopes Across Controllers (`{"errors": "..."}` vs `{"statusCode": 500, "message": "..."}`)

- **ID:** `REL-006`
- **Category:** `Maintainability` / `API` / `Robustness`
- **Title:** Split API Error Response Envelopes Across Controllers (`{"errors": "..."}` vs `{"statusCode": 500, "message": "..."}`)
- **Severity:** `Low`
- **Confidence:** `Confirmed`
- **Affected Location:**
  - `app/Http/Controllers/Concerns/HandlesApiErrors.php` lines 14–56
  - `app/Http/Controllers/ApprovalController.php` line 44
  - `app/Http/Controllers/ClosedItemsController.php` line 47
  - `app/Http/Controllers/DrivingForceController.php` line 55
  - `app/Http/Controllers/RatingUrgencyController.php` line 43
  - `app/Http/Controllers/SignalIngestionController.php` line 115
  - `app/Http/Controllers/StatusActionController.php` line 51
  - `app/Http/Controllers/TimeHorizonController.php` line 50
  - `app/Http/Controllers/UserController.php` line 49
- **Evidence:**
  8 controller `fetch_data` / `fetch_signals` methods return:
  ```php
  $error = $this->handleError($th, 'fetching ...');
  return response()->json(['errors' => $error['message']], 500);
  ```
  While `DashboardController`, `ScenarioController`, `CrossImpactController`, `ForesightRadarController`, `PrioritizingController`, and `RegisteredListController` call `$this->errorJsonResponse(...)`, which returns:
  ```json
  { "statusCode": 500, "message": "..." }
  ```
  Meanwhile, `use-server-table.js` and `Dashboard.jsx` read `error.response?.data?.message ?? error.message`, which misses `error.response.data.errors` on the 8 legacy endpoints and falls back to generic Axios `"Request failed with status code 500"`.
- **Failure or Attack Scenario:** When one of the 8 legacy `fetch_data` endpoints returns a 500 error, the frontend `useServerTable` hook fails to read the localized error message because it looks for `data.message` while the backend sends `data.errors`.
- **Root Cause:** Incomplete migration from `handleError()` to `errorJsonResponse()` across controllers.
- **Impact:** Low (degraded error toast messaging on table fetch failures).
- **Reproduction or Validation Method:** Inspect `ApprovalController.php:44` vs `RegisteredListController.php:60`.
- **Recommended Remediation:** Standardize all controllers on `$this->errorJsonResponse(...)` (returning both `message` and `errors` keys during transition if needed, or updating `HandlesApiErrors::errorJsonResponse` to include both `'message'` and `'errors'`).
- **Required Tests:** Feature test asserting consistent `statusCode` and `message` keys on 500 responses.
- **Dependencies and Effort:** None | Effort: `XS`
