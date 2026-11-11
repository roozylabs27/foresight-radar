# Foresight Radar: Security Findings

**Assessment date:** 2026-10-09  
**Auditor roles:** Application Security Engineer, Staff Software Engineer  
**Methodology:** Code analysis, automated static audit, container analysis, live test execution (Docker PHP 8.2)

---

## 1. Executive Summary

A comprehensive security review of the Foresight Radar codebase and runtime dependencies identified key vulnerabilities across access control, input validation, supply chain packages, and infrastructure configuration.

Critical authorization bugs identified in earlier audit reviews (SEC-001 role escalation, SEC-002 visualization access, SEC-003 query precedence) have verified fixes backed by automated regression tests in the test suite (57 tests passing). However, significant supply chain vulnerabilities, missing foreign key validation in FormRequests, and unrestricted user registration remain active risks.

---

## 2. Security Vulnerability Register

| ID | Severity | Category | Component | Title | Status |
|---|---|---|---|---|---|
| **SEC-001** | High | Access Control | `UserController`, `UserRequest` | Privilege Escalation via Role Assignment | Remediated (Verified) |
| **SEC-002** | High | Authorization | `routes/web.php`, `RegisteredListController` | Missing Permission Check on Visualizations | Remediated (Verified) |
| **SEC-003** | High | Logic / Query | `DrivingForceService` | SQL Operator Precedence Filter Bypass | Remediated (Verified) |
| **SEC-004** | High | Supply Chain | `package.json`, `composer.lock` | Known Vulnerabilities in Composer and NPM Packages | Partially Remediated |
| **SEC-005** | Medium | Information Disclosure | Visualization Controllers | Raw Exception Disclosure in Error Responses | Remediated (Verified) |
| **SEC-006** | Medium | Input Validation | FormRequests (`DrivingForceRequest`) | Missing Foreign Key Existence Validation | Unresolved |
| **SEC-007** | Medium | Access Control | `RegisteredUserController` | Unrestricted Public Account Registration | Unresolved |
| **SEC-008** | Low | Rate Limiting | `routes/web.php` | Absence of Rate Limiting on Data Export & Analytics | Unresolved |

---

## 3. Detailed Vulnerability Analyses

### SEC-001: Privilege Escalation via Unrestricted Role Assignment
* **Severity:** High (CVSS: 6.5)
* **Target Files:**
  * [`app/Http/Requests/UserRequest.php`](file:///c:/laragon/www/foresight-radar/app/Http/Requests/UserRequest.php#L32-L42)
  * [`app/Policies/UserPolicy.php`](file:///c:/laragon/www/foresight-radar/app/Policies/UserPolicy.php#L22-L34)
* **Initial Defect:** Non-super-admin users with `create-user` permission could assign `super-admin` or `developer` roles to any account, escalating their own or other accounts to root authority.
* **Current Remediation:**
  * `UserRequest::rules()` restricts non-super-admins from specifying `super-admin` or `developer` role IDs.
  * `UserPolicy::update()` prevents modifying users with equal or superior roles.
* **Verification Evidence:**
  * Test: `Tests\Feature\Security\RoleEscalationTest`
  * Executed: `test_regular_admin_cannot_assign_super_admin_role_on_create` (PASS)
  * Executed: `test_regular_admin_cannot_assign_super_admin_role_on_update` (PASS)
  * Executed: `test_super_admin_can_assign_super_admin_role` (PASS)

---

### SEC-002: Missing Permission Checks on Visualization Routes
* **Severity:** High (CVSS: 6.5)
* **Target Files:**
  * [`routes/web.php`](file:///c:/laragon/www/foresight-radar/routes/web.php#L35-L52)
* **Initial Defect:** Visualization endpoints (`/visualization/prioritizing`, `/visualization/registered-list`, `/visualization/foresight-radar`) and bulk data exports (`/export-data`) lacked Spatie permission gates. Any authenticated user could extract closed driving forces and strategic forecasts.
* **Current Remediation:**
  * Added route-level gates: `->can('view-prioritizing')`, `->can('view-registered-list')`, `->can('view-foresight-radar')`, and `->can('export-registered-list')`.
* **Verification Evidence:**
  * Test: `Tests\Feature\Security\VisualizationAuthorizationTest`
  * Executed: `test_unprivileged_authenticated_user_cannot_access_visualization_endpoints` (PASS, verified 403 on all endpoints)
  * Executed: `test_authorized_user_can_access_visualization_endpoints` (PASS)

---

### SEC-003: SQL Operator Precedence and Filter Bypass
* **Severity:** High (CVSS: 6.5)
* **Target Files:**
  * [`app/Services/DrivingForceService.php`](file:///c:/laragon/www/foresight-radar/app/Services/DrivingForceService.php#L60-L63)
* **Initial Defect:** Direct chaining of `->where(...)->orWhere(...)` at root query levels broke SQL operator precedence. Searching for keywords matching unapproved driving forces leaked draft records into closed lists and reports.
* **Current Remediation:**
  * All 6 query methods in `DrivingForceService` (`filter`, `timeHorizon`, `ratingUrgency`, `statusAction`, `approvalItems`, `closedItems`) enclose keyword and description searches in logical parameter grouping closures:
  ```php
  $q->where(function ($sub) use ($search) {
      $sub->where('keyword', 'LIKE', $search . '%')
          ->orWhere('description', 'LIKE', $search . '%');
  });
  ```
* **Verification Evidence:**
  * Test: `Tests\Feature\Security\FilterOperatorPrecedenceTest`
  * Executed: `test_keyword_search_does_not_leak_records_with_unmatched_status_or_dimension` (PASS)
  * Executed: `test_filter_does_not_leak_records_with_unmatched_status_or_dimension` (PASS)

---

### SEC-004: Known Vulnerabilities in Composer and NPM Packages
* **Severity:** High (CVSS: 7.8)
* **Target Files:**
  * [`composer.lock`](file:///c:/laragon/www/foresight-radar/composer.lock)
  * [`package.json`](file:///c:/laragon/www/foresight-radar/package.json#L33)
* **Current State:**
  * **PHP / Composer Audit (10 advisories):**
    * `laravel/framework` (10.48.17): CVE-2024-52301 (environment manipulation via query string, fixed in 10.48.23), CVE-2025-27515 (file validation bypass, fixed in 10.48.29), CVE-2026-48019 (CRLF injection in default email rule), CVE-2026-102279 (XSS in debug page info).
    * `league/flysystem` (3.28.0): CVE-2026-102601 (path normalizer control character bypass).
    * `phpunit/phpunit` (10.5.28): CVE-2026-24765 (unsafe deserialization in PHPT coverage).
    * `psy/psysh` (0.12.4): CVE-2026-25129 (local privilege escalation via CWD configuration file).
  * **JavaScript / NPM Audit (15 vulnerabilities: 7 high, 8 moderate):**
    * `xlsx` (^0.18.5): GHSA-4r6h-8v6p-xvw6 (Prototype Pollution, High CVSS 7.8), GHSA-5pgg-2g8v-p4x9 (ReDoS, High CVSS 7.5). Note: SheetJS stopped publishing updates to public npmjs registry; resolving this requires replacing the library with an actively supported alternative (e.g. server-side CSV streaming or `exceljs`).
    * `vite` (<=6.4.2): GHSA-fx2h-pf6j-xcff (Path traversal / server.fs.deny bypass on Windows).
    * `echarts` (<6.1.0): GHSA-fgmj-fm8m-jvvx (XSS in tooltip/DOM formatting).
    * `tailwindcss` / `chokidar` / `braces`: GHSA-vfj7-8cjw-p6xm (ReDoS in pattern expansion).
* **Remediation Plan:**
  * Run `composer update laravel/framework league/flysystem --with-dependencies` to bring Laravel to `>=10.48.29`.
  * Replace frontend `xlsx` library with backend CSV streaming or `exceljs`.
  * Upgrade `vite` and build dependencies.

---

### SEC-005: Schema Information Disclosure in Exception Handlers
* **Severity:** Medium (CVSS: 4.3)
* **Target Files:**
  * [`app/Http/Controllers/PrioritizingController.php`](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/PrioritizingController.php#L35-L38)
  * [`app/Http/Controllers/ForesightRadarController.php`](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/ForesightRadarController.php#L35-L38)
  * [`app/Http/Controllers/RegisteredListController.php`](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/RegisteredListController.php#L61-L64)
* **Initial Defect:** Controllers echoed `$th->getMessage()` directly into HTTP 500 JSON payloads, exposing SQL syntax, table schemas, and database user names during unexpected database failures.
* **Current Remediation:** Replaced raw message echo with `Log::error(...)` and generic sanitized client responses (`An unexpected server error occurred while retrieving data.`).
* **Verification Evidence:**
  * Test: `Tests\Feature\Security\InformationDisclosureTest`
  * Executed: `test_visualization_fetch_does_not_leak_raw_sql_exceptions_in_error_response` (PASS)

---

### SEC-006: Missing Foreign Key Validation in FormRequests
* **Severity:** Medium (CVSS: 4.0)
* **Target Files:**
  * [`app/Http/Requests/DrivingForceRequest.php`](file:///c:/laragon/www/foresight-radar/app/Http/Requests/DrivingForceRequest.php#L28-L33)
  * [`app/Http/Requests/StatusActionRequest.php`](file:///c:/laragon/www/foresight-radar/app/Http/Requests/StatusActionRequest.php#L28-L31)
  * [`app/Http/Requests/TimeHorizonRequest.php`](file:///c:/laragon/www/foresight-radar/app/Http/Requests/TimeHorizonRequest.php#L28-L31)
* **Defect Description:**
  * `DrivingForceRequest` validates `dimension_id` and `pic_id` as `required`, but omits `exists:dimensions,id` and `exists:users,id`.
  * Submitting invalid UUIDs triggers foreign key constraint violations at the MySQL driver level instead of returning clean HTTP 422 validation errors.
* **Remediation:** Add `Rule::exists(...)` to all foreign key fields in FormRequests.

---

### SEC-007: Open Public Registration Without Approval or Verification
* **Severity:** Medium (CVSS: 4.3)
* **Target Files:**
  * [`app/Http/Controllers/Auth/RegisteredUserController.php`](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/Auth/RegisteredUserController.php)
  * [`routes/auth.php`](file:///c:/laragon/www/foresight-radar/routes/auth.php#L15-L21)
* **Defect Description:** Any anonymous user on the network can access `/register` and create an account. While new users receive no Spatie permissions by default, open registration increases attack surface, enables user enumeration, and risks denial of service against session storage.
* **Remediation:** In an enterprise foresight platform, user account creation should be restricted to administrators or protected by invitation tokens and SSO.

---

### SEC-008: Missing Rate Limiting on Business Routes
* **Severity:** Low (CVSS: 3.5)
* **Target Files:**
  * [`routes/web.php`](file:///c:/laragon/www/foresight-radar/routes/web.php#L35-L95)
* **Defect Description:** Data fetch routes (`/fetch-data`, `/get-data`) and export routes (`/export-data`) have no rate limiting applied. Authenticated users or compromised accounts can submit repeated heavy queries, exhausting database connections and CPU.
* **Remediation:** Apply `throttle:60,1` middleware to all analytical and export routes.
