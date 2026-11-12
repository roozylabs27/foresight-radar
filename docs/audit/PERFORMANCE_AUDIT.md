# Foresight Radar — Comprehensive Performance Audit (`PERFORMANCE_AUDIT.md`)

**Audit Date:** 2026-10-23  
**Repository:** `https://github.com/roozylabs27/foresight-radar`  
**Audited Branch / Commit:** `development` (`83f532a`)  
**Stack Baseline:** Laravel 11.57.0 (PHP 8.3.36), MySQL 8.0, React 19.3.1, Inertia.js 2.3.29, TypeScript 5.8, Vite 6.4.2, Tailwind CSS v4.2.2  
**Audit Tier:** `codebase-memory-mcp` Auditor (Tier 3 — Full Path & Scope Coverage Verified)

---

## 1. Executive Summary & Measured Performance Baseline

Foresight Radar's backend and frontend architectures have undergone significant modernization (migration from Ant Design v5 to `shadcn/ui` + Radix UI + Tailwind CSS v4, upgrade to PHP 8.3 / Laravel 11 / React 19, and extraction of domain services). However, static query tracing, resource serialization inspection, and production Vite bundle profiling uncovered **3 High-severity**, **3 Medium-severity**, and **1 Low-severity** performance bottlenecks:

1. **Unbounded Pagination (`PERF-001`, High):** All 9 paginated endpoints accept arbitrary client-supplied `pageSize` values without an upper bound, allowing a single request (`?pageSize=100000`) to hydrate the entire database into memory.
2. **Severe N+1 Query Waterfalls in Resource Serialization (`PERF-002`, High):** `ApprovalItemsResource`, `ClosedItemsResource`, and `StatusActionResource` execute **5+ lazy/direct SQL queries per row** (`50+` extra queries per 10-row page; `500+` queries at `pageSize=100`) because `DrivingForceService` omits required eager loads (`dimension`, `pic_user`, `rating.time_horizon`, `rating.priority`, `rating.status_action`) and queries `StatusAction::where(...)` directly inside `toArray()`.
3. **Eager-Loading Full `sources.raw_content` (`LONGTEXT` up to 60 KB/doc) (`PERF-003`, High):** Signal table, Driving Force table, Foresight Radar, and Prioritizing endpoints eager-load `SELECT * FROM sources`, pulling up to 60 KB of raw document text per source into PHP memory and serializing `raw_content` into `/signals/fetch` table JSON responses.
4. **Redundant Database Queries in Executive Overview (`PERF-004`, Medium):** A single request to `GET /dashboard/reasoning-data` queries `dimensions` 3 times and `driving_force_ratings` / `driving_forces` 4 times over the same filter window.
5. **Eager Bundling of `xlsx` (288 kB) and Large Entry Chunk (`PERF-005`, Medium):** `OverallStatus.jsx` statically imports `* as XLSX from "xlsx"`, and `Dashboard.jsx` statically imports all 5 tab views, forcing `/dashboard` and `/visualization/registered-list` to download and parse `OverallStatus` (`288.26 kB` / `97.62 kB` gzip) and `vendor-charts` (`421.11 kB` / `114.25 kB` gzip) alongside `app.js` (`471.37 kB` / `150.40 kB` gzip).
6. **Unindexed Leading-Wildcard `LIKE '%...%'` & `TEXT` Column Scans (`PERF-006`, Medium):** Search filters in `SignalIngestionController` and `DrivingForceService` scan unindexed `TEXT` columns (`description`, `summary`, `evidence_quote`), bypassing B-tree index usage on `keyword`.
7. **Uncached Static Lookup Tables (`PERF-007`, Low):** `dimensions`, `time_horizons`, `priorities`, and `status_actions` (14 static rows total) are queried uncached on every page render and LLM extraction call.

### 1.1 Measured Production Build & Verification Baseline

| Check / Metric | Command Executed | Measured Result |
|---|---|---|
| **Backend Unified Verification** | `docker exec laravel_app composer verify` | Pint: `238 files PASS`; Larastan L5: `137 files PASS (0 errors)`; PHPUnit: `244 passed (2,440 assertions)` in `421.83s` |
| **TypeScript Typecheck** | `npm run typecheck` | `0 errors` (`tsc --noEmit`) |
| **Frontend Production Build** | `npm run build` | `4,731 modules` transformed in `1m 47s` |
| **Entry JS Bundle (`app.js`)** | `vite build` output | `471.37 kB` raw / `150.40 kB` gzip |
| **Charts Vendor Chunk (`vendor-charts.js`)** | `vite build` output | `421.11 kB` raw / `114.25 kB` gzip |
| **OverallStatus Chunk (`OverallStatus.js`)** | `vite build` output | `288.26 kB` raw / `97.62 kB` gzip (dominated by `xlsx`) |
| **CSS Bundle (`app.css`)** | `vite build` output | `69.92 kB` raw / `12.64 kB` gzip |
| **E2E Headless Smoke Suite** | `npm run test:e2e:smoke` | `1 passed` (`16.66s` Chromium) |

---

## 2. Critical Execution Path & Query Map

```
[Browser: /dashboard]
  ├── GET /dashboard (Inertia Page)
  │     └── DashboardController::index() -> SELECT * FROM dimensions (1 query)
  └── GET /dashboard/reasoning-data?date[]=... (Axios XHR)
        └── ReasoningEngineService::getExecutiveOverview() (cached 30s per filter key)
              ├── loadRatings()                  -> SELECT driving_force_ratings + driving_forces + dimension + time_horizon + priority + status_action + supporting_signals
              ├── calculateCausalLoops()         -> CrossImpactService::matrix() + CausalLoopService::detect()
              ├── calculateEdaFrequency()        -> SELECT * FROM dimensions ORDER BY id (redundant #1)
              ├── calculateEdaComparison()       -> SELECT * FROM dimensions ORDER BY id (redundant #2)
              ├── calculateEdaTrend()            -> SELECT driving_forces.created_at JOIN driving_force_ratings (redundant #1)
              └── RatingHistoryAnalyticsService
                    ├── scoreTrend()             -> SELECT driving_force_ratings.id (redundant #2) + SELECT * FROM dimensions (redundant #3) + SELECT driving_force_rating_histories
                    └── transitionVelocity()     -> SELECT driving_force_ratings.id (redundant #3) + SELECT driving_force_rating_histories

[Browser: /approval-items -> GET /approval-items/fetch-data]
  └── ApprovalController::fetch_data() -> DrivingForceService::approvalItems()
        ├── 1x COUNT(*) query on driving_forces
        ├── 1x SELECT * FROM driving_forces LIMIT N
        ├── 1x SELECT * FROM driving_force_ratings WHERE driving_force_id IN (...)
        ├── 1x SELECT * FROM action_reasons WHERE driving_force_rating_id IN (...)
        └── Per-row inside ApprovalItemsResource::toArray() (N rows):
              ├── N x SELECT * FROM status_actions WHERE id = ? LIMIT 1
              ├── N x SELECT * FROM dimensions WHERE id = ? LIMIT 1
              ├── N x SELECT * FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1
              ├── N x SELECT * FROM time_horizons WHERE id = ? LIMIT 1
              └── N x SELECT * FROM priorities WHERE id = ? LIMIT 1
              => Total: 4 + 5N queries (54 queries for 10 rows; 504 queries for 100 rows)
```

---

## 3. Detailed Performance Findings

### `PERF-001` — Unbounded Pagination (`pageSize`) Across All 9 Paginated Endpoints Enables Memory & CPU Exhaustion

- **ID:** `PERF-001`
- **Category:** `Performance` / `API` / `Database`
- **Title:** Unbounded Pagination (`pageSize`) Across All 9 Paginated Endpoints Enables Memory & CPU Exhaustion
- **Severity:** `High`
- **Confidence:** `Confirmed`
- **Affected Location:**
  - `app/Services/DrivingForceService.php` lines 42–53 (`resolvePageSize`)
  - `app/Http/Controllers/RegisteredListController.php` lines 54–55 (`registered_list`)
  - `app/Http/Controllers/SignalIngestionController.php` line 109 (`fetch_signals`)
  - `app/Models/User.php` lines 74 and 124 (`filter`)
- **Evidence:**
  ```php
  // app/Services/DrivingForceService.php:42-53
  public function resolvePageSize(array $params, int $default = 10): int
  {
      if (isset($params['pagination']['pageSize'])) {
          return (int) $params['pagination']['pageSize'];
      }
      if (isset($params['pageSize'])) {
          return (int) $params['pageSize'];
      }
      return $default;
  }
  ```
  ```php
  // app/Http/Controllers/RegisteredListController.php:54-55
  $pagination = request('pagination.pageSize');
  $registered_list = $reportingService->getRegisteredListQuery()->paginate($pagination);
  ```
- **Failure or Attack Scenario:** An authenticated client or script sends `GET /driving-force/fetch-data?pagination[pageSize]=100000`, `GET /approval-items/fetch-data?pagination[pageSize]=100000`, or `GET /signals/fetch?pageSize=100000`. Laravel's `paginate()` executes `LIMIT 100000`, hydrating all rows and their eager/lazy relations into PHP memory in a single worker process, exhausting `memory_limit` and starving PHP-FPM workers.
- **Root Cause:** Input parameters `pagination.pageSize` and `pageSize` are cast to `(int)` (or passed raw) without clamping to a safe range `[1, 100]`.
- **Impact:**
  - **System Impact:** High risk of PHP-FPM worker OOM crashes and MySQL buffer contention, especially when combined with `PERF-002` (N+1 queries) or `PERF-003` (`raw_content` hydration).
  - **Likelihood:** High (trivial query parameter manipulation).
- **Reproduction or Validation Method:**
  Execute a feature test calling `GET /driving-force/fetch-data?pagination[pageSize]=5000` and inspect `response.json('meta.per_page')`, which currently returns `5000` instead of a capped maximum (`100`).
- **Recommended Remediation:**
  1. In `DrivingForceService::resolvePageSize()`, clamp the return value: `return min(100, max(1, $size));`.
  2. Use the same bounded helper (or a shared `TablePagination::resolve($request, $default = 10, $max = 100)`) in `RegisteredListController::registered_list()`, `SignalIngestionController::fetch_signals()`, and `User::filter()`.
- **Required Tests:**
  - Feature test asserting that `?pagination[pageSize]=99999` and `?pageSize=-5` are clamped to `100` and `1` (or default `10`) respectively across `/driving-force/fetch-data`, `/signals/fetch`, `/visualization/registered-list/get-data`, and `/user-management/fetch-data`.
- **Dependencies and Effort:** None | Effort: `XS`

---

### `PERF-002` — Severe N+1 Query Waterfalls in `ApprovalItemsResource`, `ClosedItemsResource`, and `StatusActionResource`

- **ID:** `PERF-002`
- **Category:** `Performance` / `Database`
- **Title:** Severe N+1 Query Waterfalls in `ApprovalItemsResource`, `ClosedItemsResource`, and `StatusActionResource` (Up to 5+ Queries Per Row)
- **Severity:** `High`
- **Confidence:** `Confirmed`
- **Affected Location:**
  - `app/Services/DrivingForceService.php` lines 197–199 (`statusAction`), line 258 (`approvalItems`), line 293 (`closedItems`)
  - `app/Http/Resources/ApprovalItemsResource.php` lines 18, 27, 31, 33, 34
  - `app/Http/Resources/ClosedItemsResource.php` lines 18, 27, 31, 33, 34
  - `app/Http/Resources/StatusActionResource.php` lines 23, 27, 33, 37, 41, 50, 51, 56
- **Evidence:**
  ```php
  // app/Services/DrivingForceService.php:258
  $approvalItems = DrivingForce::with(['rating.action_reasons'])
  ```
  ```php
  // app/Http/Resources/ApprovalItemsResource.php:18-39
  $status_action = StatusAction::where('id', $this->rating->status_action_id)->first(); // N+1 #1
  ...
  'dimension' => $this->dimension->name,                      // N+1 #2 (dimension not eager-loaded)
  'pic' => $this->pic_user->name,                             // N+1 #3 (pic_user not eager-loaded)
  'time_horizon' => $this->rating->time_horizon->name,        // N+1 #4 (time_horizon not eager-loaded)
  'priority' => $this->rating->priority->name,                // N+1 #5 (priority not eager-loaded)
  ```
  ```php
  // app/Http/Resources/StatusActionResource.php:55-61
  'reasons' => collect($this->rating->action_reasons)->map(function ($data) {
      $action = StatusAction::where('id', $data->status_action_id)->first(); // N+1 per history entry!
  ```
- **Failure or Attack Scenario:** Opening `/approval-items`, `/closed-items`, or `/status-action` triggers `50+` redundant SQL queries for a single 10-row page, or `100+` queries if a driving force has multiple `action_reasons` in `StatusActionResource`.
- **Root Cause:**
  1. `DrivingForceService::approvalItems()`, `closedItems()`, and `statusAction()` omit nested relations (`dimension`, `pic_user`, `rating.time_horizon`, `rating.priority`, `rating.status_action`, `rating.action_reasons.status_action`) from `with(...)`.
  2. `ApprovalItemsResource`, `ClosedItemsResource`, and `StatusActionResource` execute `StatusAction::where('id', ...)->first()` directly instead of using the Eloquent relationship `$this->rating->status_action` or `$data->status_action`.
- **Impact:**
  - **User & System Impact:** Linear `O(N)` query multiplication on three core operational workflow screens, increasing DB round-trips by 10x–20x per table load.
  - **Likelihood:** Confirmed on every request to `/approval-items/fetch-data`, `/closed-items/fetch-data`, and `/status-action/fetch-data`.
- **Reproduction or Validation Method:**
  Enable `DB::enableQueryLog()` in a PHPUnit test for `GET /approval-items/fetch-data` with 10 seeded approved driving forces; `count(DB::getQueryLog())` exceeds 50 queries instead of `<= 8` queries.
- **Recommended Remediation:**
  1. In `DrivingForceService::approvalItems()` and `closedItems()`, eager-load `['dimension', 'pic_user' => fn ($q) => $q->withTrashed(), 'rating.time_horizon', 'rating.priority', 'rating.status_action', 'rating.action_reasons']`.
  2. In `ApprovalItemsResource` and `ClosedItemsResource`, replace `StatusAction::where('id', $this->rating->status_action_id)->first()` with `$this->rating?->status_action`.
  3. Add a `status_action()` `BelongsTo` relationship on `ActionReason` (or eager-load `rating. status_action`, `rating.time_horizon`, `rating.priority`, `rating.action_reasons.status_action` in `DrivingForceService::statusAction()`) and remove `StatusAction::where(...)` inside `StatusActionResource::toArray()`.
- **Required Tests:**
  - Query-count regression test (`DB::enableQueryLog()`) asserting `<= 10` queries for 15 rows on `/approval-items/fetch-data`, `/closed-items/fetch-data`, and `/status-action/fetch-data`.
- **Dependencies and Effort:** Coordinate with `REL-002` (null-safe resource properties) | Effort: `S`

---

### `PERF-003` — Eager-Loading Full `sources.raw_content` (`LONGTEXT` Up to 60 KB/Document) in List & Visualization Endpoints

- **ID:** `PERF-003`
- **Category:** `Performance` / `Database` / `API`
- **Title:** Eager-Loading Full `sources.raw_content` (`LONGTEXT` Up to 60 KB/Document) in Signal, Driving Force, Radar, and Prioritizing Endpoints
- **Severity:** `High`
- **Confidence:** `Confirmed`
- **Affected Location:**
  - `app/Http/Controllers/SignalIngestionController.php` line 88 (`fetch_signals`)
  - `app/Services/DrivingForceService.php` lines 88–89 (`filter`)
  - `app/Services/ForesightReportingService.php` lines 66–67 (`getRadarDataset`) and lines 103–104 (`getPrioritizingDataset`)
- **Evidence:**
  ```php
  // app/Http/Controllers/SignalIngestionController.php:88
  $query = Signal::with(['source', 'dimension', 'suggested_time_horizon', 'reviewer', 'created_driving_force', 'suggested_driving_force:id,uuid,keyword'])
  ```
  ```php
  // app/Services/ForesightReportingService.php:66-67
  'driving_force.source_signal.source',
  'driving_force.supporting_signals.source',
  ```
- **Failure or Attack Scenario:** Each `Source` record stores `raw_content` (`LONGTEXT`, validated up to `60,000` characters in `IngestSourceRequest`). When `SignalIngestionController::fetch_signals()` returns 10 signals, Eloquent selects `sources.*` and serializes the full `raw_content` of every source directly into the JSON response (`~600 KB` per 10-row page). Similarly, `DrivingForceService::filter()`, `getRadarDataset()`, and `getPrioritizingDataset()` pull full `raw_content` columns from MySQL into PHP memory even though `DrivingForceResource`, `ForesightRadarResource`, and `PrioritizingResource` only read `source->title` and `source->url`.
- **Root Cause:** Unconstrained eager-loading of the `source` relation (`'source'` instead of `'source:id,uuid,title,url,publisher,published_at'`).
- **Impact:**
  - **User & System Impact:** Massive MySQL network I/O and PHP memory allocation per request; bloated JSON responses on `/signals/fetch`.
  - **Likelihood:** Confirmed whenever ingested documents approach typical article/report lengths (10–60 KB).
- **Reproduction or Validation Method:**
  Ingest a 50 KB source document, extract 5 signals, and call `GET /signals/fetch`. Inspect `response.json('data.0.source.raw_content')` — the entire 50 KB string is transmitted in every signal row.
- **Recommended Remediation:**
  Constrain `source` column selection across all list and reporting queries:
  - `source:id,uuid,title,url,publisher,published_at`
  - `source_signal.source:id,uuid,title,url`
  - `supporting_signals.source:id,uuid,title,url`
- **Required Tests:**
  - Feature test asserting `GET /signals/fetch` includes `source.title` and `source.url` while omitting `source.raw_content` from the paginated list payload.
- **Dependencies and Effort:** None (verify `Signals/Index.jsx` inspect drawer does not require `source.raw_content` — verified: `Signals/Index.jsx` only displays `signal.evidence_quote`, `signal.source.title`, `signal.source.publisher`, and `signal.source.url`) | Effort: `XS`

---

### `PERF-004` — Redundant Queries & Duplicate Hydrations in `ReasoningEngineService::getExecutiveOverview`

- **ID:** `PERF-004`
- **Category:** `Performance` / `Backend` / `Database`
- **Title:** Redundant Queries & Duplicate Rating/Dimension Hydrations in `ReasoningEngineService::getExecutiveOverview`
- **Severity:** `Medium`
- **Confidence:** `Confirmed`
- **Affected Location:**
  - `app/Services/ReasoningEngineService.php` lines 57–115, 363, 439, 477
  - `app/Services/RatingHistoryAnalyticsService.php` lines 38, 66, 97
- **Evidence:**
  Within a single cache miss of `ReasoningEngineService::getExecutiveOverview()`:
  - `Dimension::orderBy('id')->get()` is executed in `calculateEdaFrequency()` (line 363), `calculateEdaComparison()` (line 439), and `RatingHistoryAnalyticsService::scoreTrend()` (line 66).
  - Approved `DrivingForceRating` IDs/rows are queried in `loadRatings()` (line 100), `calculateEdaTrend()` (line 477), `RatingHistoryAnalyticsService::scoreTrend()` (line 38), and `RatingHistoryAnalyticsService::transitionVelocity()` (line 97).
- **Failure or Attack Scenario:** Whenever a user changes the date or dimension filter on `/dashboard` (cache miss), `getExecutiveOverview()` executes 6+ redundant SQL queries for the same dimensions and rating IDs.
- **Root Cause:** Helper methods in `ReasoningEngineService` and `RatingHistoryAnalyticsService` re-query `Dimension` and `DrivingForceRating` independently instead of accepting the already-loaded `$ratings` collection and `$dimensions` list.
- **Impact:**
  - **System Impact:** Unnecessary DB query overhead on `/dashboard/reasoning-data` and `/dashboard/simulate`.
  - **Likelihood:** Confirmed on every filter change or simulation call.
- **Reproduction or Validation Method:**
  Run `DB::enableQueryLog()` around `app(ReasoningEngineService::class)->getExecutiveOverview($dateStart, $dateEnd, null)` with a cold cache and inspect duplicate `select * from dimensions order by id asc` and `driving_force_ratings` queries.
- **Recommended Remediation:**
  Load `$dimensions = Dimension::orderBy('id')->get()` once at the top of `getExecutiveOverview()` and pass `$dimensions` and `$ratings->pluck('id')` into `calculateEdaFrequency()`, `calculateEdaComparison()`, `scoreTrend()`, and `transitionVelocity()`.
- **Required Tests:**
  - Unit/Feature test verifying `ReasoningEngineService::getExecutiveOverview()` output structure remains identical while reducing query count by at least 5 queries on cold cache.
- **Dependencies and Effort:** None | Effort: `S`

---

### `PERF-005` — Eager Top-Level Bundling of `xlsx` (288 kB) and Static Tab Imports on Dashboard

- **ID:** `PERF-005`
- **Category:** `Performance` / `Frontend`
- **Title:** Eager Top-Level Bundling of `xlsx` (288 kB) in `OverallStatus.jsx` and Static Tab Imports in `Dashboard.jsx`
- **Severity:** `Medium`
- **Confidence:** `Confirmed`
- **Affected Location:**
  - `resources/js/Components/OverallStatus.jsx` line 5 (`import * as XLSX from "xlsx";`)
  - `resources/js/Pages/Dashboard.jsx` lines 10–16
  - `vite.config.js` lines 16–29
- **Evidence:**
  Measured `npm run build` output:
  - `public/build/assets/app-Dr9ajObe.js`: **471.37 kB** (`150.40 kB` gzip)
  - `public/build/assets/vendor-charts-dbjL0A1J.js`: **421.11 kB** (`114.25 kB` gzip)
  - `public/build/assets/OverallStatus-DBtUkMVi.js`: **288.26 kB** (`97.62 kB` gzip)
  Because `Dashboard.jsx` statically imports `OverallStatus`, `Radar`, `PrioritizingChart`, and `ScenarioSimulation`, and `OverallStatus.jsx` statically imports `xlsx`, every user landing on `/dashboard` downloads `OverallStatus.js` (`288 kB`) even though the `"registered"` tab is not open and Excel export has not been clicked.
- **Failure or Attack Scenario:** Slower initial script parse/compile time and ~100 kB extra gzipped transfer on `/dashboard` and `/visualization/registered-list`.
- **Root Cause:** Static top-level `import * as XLSX from "xlsx"` inside `OverallStatus.jsx` instead of dynamic `await import("xlsx")` inside `handleExport()`, plus static imports of heavy non-default tab organisms in `Dashboard.jsx`.
- **Impact:**
  - **User Impact:** Increased Time to Interactive (TTI) and bandwidth consumption on the primary landing page (`/dashboard`).
  - **Likelihood:** Confirmed on every cold load of `/dashboard` and `/visualization/registered-list`.
- **Reproduction or Validation Method:**
  Run `npm run build` and inspect chunk sizes: `OverallStatus-*.js` is `288.26 kB`.
- **Recommended Remediation:**
  1. Replace top-level `import * as XLSX from "xlsx"` in `OverallStatus.jsx` with a dynamic `const XLSX = await import("xlsx");` inside `exportWorkbook()` / `handleExport()` (or move export to a server-side CSV/XLSX stream to eliminate `xlsx` entirely per `SEC-002`).
  2. Use `React.lazy()` + `<Suspense>` in `Dashboard.jsx` for inactive tabs (`ScenarioSimulation`, `PrioritizingChart`, `Radar`, `OverallStatus`).
- **Required Tests:**
  - Build verification (`npm run build`) confirming `OverallStatus-*.js` drops from `~288 kB` to `< 15 kB` and E2E smoke test passes.
- **Dependencies and Effort:** Coordinate with `SEC-002` (`xlsx` remediation) | Effort: `S`

---

### `PERF-006` — Unindexed Leading-Wildcard `LIKE '%...%'` and `TEXT` Column Scans on `signals` and `driving_forces`

- **ID:** `PERF-006`
- **Category:** `Performance` / `Database`
- **Title:** Unindexed Leading-Wildcard `LIKE '%...%'` and `TEXT` Column Scans on `signals` and `driving_forces`
- **Severity:** `Medium`
- **Confidence:** `Confirmed`
- **Affected Location:**
  - `app/Http/Controllers/SignalIngestionController.php` lines 93–95
  - `app/Services/DrivingForceService.php` lines 94–95, 132–133, 165–166, 202–203, 261–262, 296–297
- **Evidence:**
  ```php
  // app/Http/Controllers/SignalIngestionController.php:93-95
  $sub->where('title', 'LIKE', '%'.$search.'%')
      ->orWhere('summary', 'LIKE', '%'.$search.'%')
      ->orWhere('evidence_quote', 'LIKE', '%'.$search.'%');
  ```
  ```php
  // app/Services/DrivingForceService.php:94-95
  $sub->where('keyword', 'LIKE', $search.'%')
      ->orWhere('description', 'LIKE', $search.'%');
  ```
- **Failure or Attack Scenario:** Because `OR description LIKE 'term%'` references an unindexed `TEXT` column, MySQL's query optimizer cannot use a single index range scan on `driving_forces_keyword_index` alone and must evaluate row contents across the date range. On `signals`, leading-wildcard `%term%` across two `TEXT` columns (`summary`, `evidence_quote`) forces a full scan of matching `review_status` rows.
- **Root Cause:** Combining indexed `VARCHAR` prefix search with unindexed `TEXT` column `OR LIKE` predicates instead of MySQL `FULLTEXT` indexes (`MATCH(...) AGAINST(...)`).
- **Impact:**
  - **System Impact:** Search latency degrades linearly `O(N)` as `signals` and `driving_forces` grow beyond a few thousand rows.
  - **Likelihood:** Medium (proportional to dataset growth).
- **Reproduction or Validation Method:**
  Run `EXPLAIN SELECT * FROM driving_forces WHERE (keyword LIKE 'ai%' OR description LIKE 'ai%') AND deleted_at IS NULL` in MySQL 8.0 and verify `type: ALL` or full index/table scan in the execution plan.
- **Recommended Remediation:**
  Add MySQL `FULLTEXT` indexes on `driving_forces(keyword, description)` and `signals(title, summary, evidence_quote)` via migration (with fallback to `LIKE` for short 1–2 char queries or SQLite test environments).
- **Required Tests:**
  - Search filter feature tests on `/driving-force/fetch-data` and `/signals/fetch` verifying matching keyword/description/quote results.
- **Dependencies and Effort:** None | Effort: `M`

---

### `PERF-007` — Uncached Static Reference Tables Queried on Every Page Render & Extraction Call

- **ID:** `PERF-007`
- **Category:** `Performance` / `Database`
- **Title:** Uncached Static Reference Tables (`dimensions`, `priorities`, `time_horizons`, `status_actions`) Queried Repeatedly on Every Page & API Request
- **Severity:** `Low`
- **Confidence:** `Confirmed`
- **Affected Location:**
  - `app/Http/Controllers/RegisteredListController.php` lines 22–45
  - `app/Http/Controllers/DrivingForceController.php` lines 24–38
  - `app/Http/Controllers/SignalIngestionController.php` lines 40–66
  - `app/Services/Extraction/LlmSignalExtractor.php` lines 35–36
- **Evidence:**
  `RegisteredListController::index()` executes 4 separate queries (`Dimension::all()`, `TimeHorizon::all()`, `Priority::all()`, `StatusAction::all()`) on every page load for tables that contain 14 immutable rows seeded at deployment.
- **Failure or Attack Scenario:** Unnecessary repetitive queries on every Inertia page transition.
- **Root Cause:** Absence of a cached lookup helper (e.g. `Cache::rememberForever('lookup.dimensions', ...)`).
- **Impact:** Low (4 small queries per page navigation).
- **Reproduction or Validation Method:** Enable query log on `GET /visualization/registered-list` and observe 4 separate `SELECT *` queries for static lookup tables.
- **Recommended Remediation:** Centralize lookup retrieval in a `ReferenceDataService` or model-level `Cache::remember('reference.dimensions', 3600, ...)` helper.
- **Required Tests:** Unit test verifying cached reference lookups return expected `value`/`label` pairs.
- **Dependencies and Effort:** None | Effort: `XS`
