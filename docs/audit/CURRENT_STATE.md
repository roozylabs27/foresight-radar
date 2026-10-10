# Foresight Radar: Current State

**Inspection date:** 2026-10-09
**Branch:** `dev`
**Method:** Full codebase read (backend models, controllers, services, routes, migrations, seeders, frontend pages, components, tests, configuration, CI/CD)

---

## 1. What Currently Exists and Works

### 1.1 Backend (Laravel 10.48, PHP 8.2)

| Component | Evidence |
|---|---|
| **Spatie RBAC** | Roles (`super-admin`, `developer`, `admin`, `staff`) and ~30 permissions seeded via `DefaultSystemSeeder` ([database/seeders/DefaultSystemSeeder.php](file:///c:/laragon/www/foresight-radar/database/seeders/DefaultSystemSeeder.php)). Route-level `->can()` middleware on every business route ([routes/web.php](file:///c:/laragon/www/foresight-radar/routes/web.php#L32-L104)). |
| **Session auth (Breeze)** | Standard email/password login, registration, password reset, email verification ([routes/auth.php](file:///c:/laragon/www/foresight-radar/routes/auth.php)). |
| **Driving force CRUD** | `DrivingForceController` handles create, update (with `DrivingForcePolicy` ownership check), soft delete ([app/Http/Controllers/DrivingForceController.php](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/DrivingForceController.php)). |
| **Time horizon assessment** | `TimeHorizonController` creates/updates `DrivingForceRating` with `time_horizon_id` via `updateOrCreate` ([app/Http/Controllers/TimeHorizonController.php](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/TimeHorizonController.php)). |
| **Impact/uncertainty rating** | `RatingUrgencyController` stores `impact_analysis` and `uncertainty_analysis` (1-10 scale), calculates `priority_id` from a threshold matrix ([app/Http/Controllers/RatingUrgencyController.php](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/RatingUrgencyController.php)). |
| **Status action assignment** | `StatusActionController` assigns an action status (Act/Monitor/Park), recalculates priority, and logs historical reasons to `ActionReason` table within a transaction ([app/Http/Controllers/StatusActionController.php](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/StatusActionController.php)). |
| **Approval workflow** | `ApprovalController` approves (sets `approved_at`, transitions to `CLOSED`) or rejects (sets `remark`, keeps `PENDING`) a driving force ([app/Http/Controllers/ApprovalController.php](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/ApprovalController.php)). |
| **Closed items view** | `ClosedItemsController` displays finalized driving forces with status `CLOSED` ([app/Http/Controllers/ClosedItemsController.php](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/ClosedItemsController.php)). |
| **Service layer** | `DrivingForceService` centralizes query logic for 6 workflow stages with eager loading, date-range resolution, pagination, and keyword search ([app/Services/DrivingForceService.php](file:///c:/laragon/www/foresight-radar/app/Services/DrivingForceService.php)). Search terms are properly wrapped in logical closures (SEC-003 fix verified). |
| **API resource transformations** | Separate `Collection` classes for each workflow stage (`DrivingForceCollection`, `TimeHorizonCollection`, `RatingUrgencyCollection`, `StatusActionCollection`, `ApprovalItemsCollection`, `ClosedItemsCollection`). |
| **User management** | `UserController` handles CRUD with `UserPolicy` for ownership/hierarchy checks, `UserRequest` with role validation ([app/Http/Controllers/UserController.php](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/UserController.php)). |
| **Policies** | `DrivingForcePolicy` (creator/PIC/admin check for update/delete), `UserPolicy` (role hierarchy check) ([app/Policies/](file:///c:/laragon/www/foresight-radar/app/Policies/)). |
| **Database transactions** | Every state-changing controller action uses `DB::beginTransaction()` / `DB::commit()` / `DB::rollBack()`. |
| **UUID route keys** | All models use `uuid` as `routeKeyName`, preventing ID enumeration. |
| **Docker deployment** | `Dockerfile` (PHP-FPM), `docker-compose.yml` (app, mysql, nginx), `docker/nginx/default.conf` ([docker-compose.yml](file:///c:/laragon/www/foresight-radar/docker-compose.yml)). |
| **CI/CD** | GitHub Actions workflow at `.github/workflows/ci.yml` ([.github/workflows/ci.yml](file:///c:/laragon/www/foresight-radar/.github/workflows/ci.yml)). |
| **Visualization auth** | Routes under `/visualization/*` now have `->can('view-prioritizing')`, `->can('view-registered-list')`, `->can('view-foresight-radar')`, and `->can('export-registered-list')` (SEC-002 fix verified in [routes/web.php](file:///c:/laragon/www/foresight-radar/routes/web.php#L35-L52)). |

### 1.2 Frontend (React 18, Inertia.js, Ant Design 5.19)

| Component | Evidence |
|---|---|
| **Inertia SPA** | React pages resolved via Vite glob in [app.jsx](file:///c:/laragon/www/foresight-radar/resources/js/app.jsx). |
| **Ant Design theming** | `ConfigProvider` with custom primary color `#1677ff`, consistent component usage across all pages. |
| **Sidebar navigation** | Permission-gated menu items in [Sidebar.jsx](file:///c:/laragon/www/foresight-radar/resources/js/Components/Sidebar.jsx). |
| **Radar visualization** | Custom ECharts polar scatter with 8 hardcoded foresight dimensions, anti-collision algorithm for overlapping points ([Radar.jsx](file:///c:/laragon/www/foresight-radar/resources/js/Components/Radar.jsx#L82)). |
| **Prioritizing matrix** | 2D scatter chart (Impact vs Uncertainty) with 3 colored priority zones (High/Med/Low), spiral anti-collision ([PrioritizingChart.jsx](file:///c:/laragon/www/foresight-radar/resources/js/Components/PrioritizingChart.jsx#L43)). |
| **Registered list with export** | Data table with Excel export via `xlsx` library ([OverallStatus.jsx](file:///c:/laragon/www/foresight-radar/resources/js/Components/OverallStatus.jsx#L137)). |
| **CRUD forms** | `forwardRef` pattern exposing `submit()`/`reset()` to parent Dialog components. |
| **Filter bar pattern** | Date range picker + dimension select on every data table and report page. |
| **Async data fetching** | Tables and charts use `axios` calls to `/fetch-data` and `/get-data` endpoints, with `tableParams` state driving `useEffect` refetches. |
| **Inline rating** | `RatingUrgency/Table.jsx` allows direct cell-level rating with Tags and Popconfirm. |
| **Auth pages** | Custom dual-column login/register with promotional carousel, plus standard Breeze pages for password reset, email verification. |

### 1.3 Tests

| Category | Count | Location |
|---|---|---|
| Auth scaffolding | 18 tests | `tests/Feature/Auth/` |
| Driving force CRUD | 4 tests | `tests/Feature/DrivingForceTest.php` |
| Rating urgency | 3 tests | `tests/Feature/RatingUrgencyTest.php` |
| Authorization | 2 tests | `tests/Feature/AuthorizationTest.php` |
| Profile | 5 tests | `tests/Feature/ProfileTest.php` |
| Security (added by remediation) | 6+ tests | `tests/Feature/Security/` |
| Reliability (added by remediation) | 3+ tests | `tests/Feature/Reliability/` |
| Performance (added by remediation) | 2+ tests | `tests/Feature/Performance/` |
| Workflow lifecycle | 1 test | `tests/Feature/Workflow/ForesightLifecycleTest.php` |

---

## 2. What Partially Exists or Is Incomplete

| Area | Status | Evidence |
|---|---|---|
| **Role hierarchy enforcement** | Partially fixed. `UserRequest` now restricts non-super-admins from assigning `super-admin`/`developer` roles, but self-role modification prevention may still be incomplete. | [app/Http/Requests/UserRequest.php](file:///c:/laragon/www/foresight-radar/app/Http/Requests/UserRequest.php) |
| **Foreign key validation in FormRequests** | `DrivingForceRequest` checks `dimension_id` and `pic` as `required` but does not validate `exists:dimensions,id` or `exists:users,id`. Same gap in `StatusActionRequest` and `TimeHorizonRequest`. | [app/Http/Requests/DrivingForceRequest.php](file:///c:/laragon/www/foresight-radar/app/Http/Requests/DrivingForceRequest.php#L29-L30) |
| **Streaming export** | Implementation plan exists but needs verification of whether `export_data` currently streams or still uses `-\u003eget()`. | [app/Http/Controllers/RegisteredListController.php](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/RegisteredListController.php) |
| **Error response sanitization** | Implementation plan addresses `catch (\Throwable)` leaking raw exceptions. Status of fix in current codebase requires runtime verification. | Visualization controllers |
| **HTTP security headers** | `docker/nginx/default.conf` may or may not have headers added. Session secure cookie defaults to `false` in `.env.example`. | [.env.example](file:///c:/laragon/www/foresight-radar/.env.example#L25) |
| **Frontend code splitting** | `vite.config.js` splits `echarts`/`zrender` but the main bundle still exceeds 1 MB. | Prior audit PERF-002 |
| **Test coverage** | Security/reliability/performance tests were added by remediation, but Approval, StatusAction, TimeHorizon, ClosedItems, and visualization controllers remain untested or minimally tested. | `tests/Feature/` |

---

## 3. What Is Missing

| Capability | Status |
|---|---|
| **Signal management** | No `Signal` model, table, controller, or UI. Signals are not a separate entity. |
| **Trend tracking** | No `Trend` model or relationship. Trends are not modeled. |
| **Evidence/source tracking** | No way to attach source documents, URLs, or citations to a driving force or assessment. |
| **Assessment revision history** | No audit trail for rating changes. When ratings are updated, previous values are overwritten. `ActionReason` logs status action changes but not rating score changes. |
| **AI-assisted analysis** | No AI provider, LLM integration, prompt templates, or structured output handling anywhere in the codebase. No `OPENAI_API_KEY` or similar in `.env.example`. |
| **Scenario planning** | No scenario model, UI, or workflow. |
| **Strategic implications** | No model for risks, opportunities, or consequences derived from driving forces. |
| **Action tracking** | `StatusAction` assigns Act/Monitor/Park, but there is no task management or action tracking for follow-up initiatives. |
| **Alerts and monitoring** | No notification system, email alerts, or monitoring for changes in driving force status. |
| **API documentation** | No OpenAPI/Swagger spec. No public REST API (Inertia-only). |
| **Queues** | `QUEUE_CONNECTION=sync`. No queued jobs. |
| **Caching** | `CACHE_DRIVER=file`. No tagged caching for visualization data. |
| **Rate limiting on business routes** | Only auth endpoints have throttle middleware. |
| **Full-text search** | Keyword search uses `LIKE` prefix matching. No fulltext index. |
| **Soft delete recovery** | `DrivingForce` uses `SoftDeletes` but there is no UI or controller method to restore deleted records. |
| **Multi-language support** | No localization files or language switching. |
| **File uploads** | No file upload mechanism for documents or evidence. |

---

## 4. What Cannot Be Verified Without Runtime Access

| Item | Reason |
|---|---|
| **Database state and seed data integrity** | Whether migrations have been run, seeder data is consistent, and indexes exist requires a live database. |
| **Session security in production** | Whether `SESSION_SECURE_COOKIE` is actually set to `true` in production `.env`. |
| **Container security posture** | Whether the Docker container runs as root or `www-data` in the current deployment. |
| **Dependency vulnerability status** | Whether `composer update` and `npm audit` fixes from the remediation plan have been applied. |
| **Test execution** | Whether all tests pass in the current environment. |
| **Frontend rendering** | Whether visualizations render correctly, mobile layout holds, and all interactive elements function. |
| **Performance under load** | Query execution times, memory usage during export, and frontend bundle performance. |

---

## 5. Request Lifecycle

```
Browser (React 18 + Ant Design)
    |
    | HTTPS (Port 8080)
    v
Nginx Reverse Proxy
    |
    | FastCGI (Port 9000)
    v
PHP-FPM (Laravel 10.48)
    |
    v
HTTP Kernel (global middleware: TrustProxies, CORS, CSRF, TrimStrings)
    |
    v
Web Middleware Group (EncryptCookies, Session, HandleInertiaRequests)
    |
    v
Route Dispatcher (routes/web.php)
    |
    v
Auth + Verified Middleware
    |
    v
Spatie Permission Gate (->can('permission-name'))
    |
    v
Controller (thin: delegates to Service or direct Eloquent)
    |
    +---> FormRequest Validation (DrivingForceRequest, UserRequest, etc.)
    |
    +---> DrivingForceService (query building, filtering, pagination)
    |
    +---> Eloquent Models (DrivingForce, DrivingForceRating, etc.)
    |         |
    |         v
    |     MySQL (Port 3306)
    |
    v
Inertia Response (JSON props -> React page component)
    |
    v
Browser renders React component with Ant Design
```

For async data endpoints (`/fetch-data`, `/get-data`):
1. React component fires `axios.get()` with filter params
2. Controller calls `DrivingForceService` method
3. Service builds Eloquent query with eager loading, returns paginated Resource Collection
4. Controller returns JSON response
5. React updates table/chart state

---

## 6. Driving Force Lifecycle

A driving force progresses through 6 sequential stages:

```
1. CREATION (DrivingForceController::create)
   - User submits keyword, description, dimension_id, pic (person-in-charge)
   - Status set to 'PENDING'
   - Stored in driving_forces table
       |
       v
2. TIME HORIZON ASSESSMENT (TimeHorizonController::create)
   - Analyst selects time_horizon_id for the driving force
   - Creates/updates DrivingForceRating record linked to driving_force_id
       |
       v
3. IMPACT & UNCERTAINTY RATING (RatingUrgencyController::create)
   - Analyst scores impact_analysis (1-10) and uncertainty_analysis (1-10)
   - System calculates priority_id:
     - impact + uncertainty >= 12 -> High priority
     - impact + uncertainty >= 6  -> Medium priority
     - Otherwise                  -> Low priority
       |
       v
4. STATUS ACTION ASSIGNMENT (StatusActionController::create)
   - Analyst assigns status_action_id (Act / Monitor / Park)
   - Logs reason + date to action_reasons table
   - Recalculates priority based on current analysis values
       |
       v
5. APPROVAL (ApprovalController::create)
   - Reviewer approves: status -> 'CLOSED', sets approved_at and closed_at
   - Reviewer rejects: status stays 'PENDING', sets remark
       |
       v
6. CLOSED REPOSITORY
   - Finalized driving force appears in Closed Items, Radar, Prioritizing, Registered List
   - Available for export
```

---

## 7. Existing Data Structures and Limitations

### Tables and Key Columns

| Table | Key Columns | Limitations |
|---|---|---|
| `users` | uuid, name, email, password | No profile fields beyond name/email. |
| `environments` | uuid, name | Parent category for dimensions. |
| `dimensions` | uuid, environment_id, name | Hardcoded 8 dimensions in Radar.jsx angular mapping. Adding new dimensions requires frontend changes. |
| `driving_forces` | uuid, dimension_id, pic, created_by, updated_by, keyword, description, status, remark, approved_at, closed_at | No source URL, publication date, evidence, or confidence field. `keyword` limited by `WordCountRule`. No revision history. `status` is a string enum without DB-level constraint. |
| `driving_force_ratings` | uuid, driving_force_id, time_horizon_id, status_action_id, priority_id, impact_analysis, uncertainty_analysis | One-to-one with driving_force (hasOne). No multi-assessor support. No assessment history. Ratings overwritten on update. |
| `action_reasons` | uuid, driving_force_rating_id, status_action_id, date, reason | Historical log of status action changes. Good pattern for audit trail. |
| `time_horizons` | uuid, name, code | Lookup table (Short/Medium/Long). |
| `status_actions` | uuid, name, symbol, code | Lookup table (Act/Monitor/Park). |
| `priorities` | uuid, name, color | Lookup table (High/Medium/Low) with display color. |
| `permission_tables` | Spatie standard: roles, permissions, model_has_roles, model_has_permissions, role_has_permissions | Standard Spatie schema. |

### Key Structural Limitations

1. **No signal-driving force distinction.** A "driving force" serves as both the input signal and the assessed strategic force. There is no way to track raw signals before they become driving forces.
2. **No multi-source evidence.** A driving force has a keyword and description but no linked source documents, URLs, or supporting evidence.
3. **No assessment versioning.** Rating updates overwrite the previous values. Only status action changes are logged in `action_reasons`.
4. **Hardcoded dimension angles.** The Radar visualization maps 8 specific dimension names to fixed polar angles. The schema supports arbitrary dimensions, but the frontend does not.
5. **Single assessor per rating.** `DrivingForceRating` is a one-to-one relationship. No support for multiple assessors or consensus scoring.
6. **No AI content distinction.** No field or mechanism to mark content as AI-generated vs human-verified.
7. **Status as free string.** The `status` column uses string values ('PENDING', 'APPROVED', 'CLOSED') without a database-level enum constraint. Invalid transitions are not prevented at the data layer.
