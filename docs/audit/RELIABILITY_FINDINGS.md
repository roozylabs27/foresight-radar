# Foresight Radar: Reliability and Data Integrity Findings

**Assessment date:** 2026-10-09  
**Auditor roles:** Staff Software Engineer, Site Reliability Engineer  
**Methodology:** Code analysis, state transition modeling, edge case inspection, transaction boundary review

---

## 1. Executive Summary

This evaluation analyzed transactional integrity, state machine rigor, concurrent updates, error handling, and model relationship constraints in the Foresight Radar application.

Key findings show that while foundational data protection practices (database transactions, UUID route keys, eager loading) are present and several previously identified defects (REL-001, REL-002, REL-003) were resolved and verified by automated tests, the application still lacks a formal state machine for the driving force lifecycle, contains unvalidated input types in rating endpoints, and hardcodes priority identifiers across multiple controllers.

---

## 2. Reliability Findings Register

| ID | Severity | Category | Component | Title | Status |
|---|---|---|---|---|---|
| **REL-001** | Medium | Error Handling | `DrivingForceRating`, `DrivingForceService` | Unchecked Array Key on Date Parameters | Remediated (Verified) |
| **REL-002** | Medium | Data Integrity | `UserController` | Account Collision & Role Overwrite on Duplicate Names | Remediated (Verified) |
| **REL-003** | Low | Data Integrity | `TimeHorizonController` | UUID Regeneration on Existing Record Updates | Remediated (Verified) |
| **REL-004** | High | State Machine | `ApprovalController`, `DrivingForce` | Unvalidated Lifecycle State Transitions | Unresolved |
| **REL-005** | Medium | Domain Logic | `RatingUrgencyController`, `StatusActionController` | Hardcoded Priority Identifiers | Unresolved |
| **REL-006** | Medium | Input Validation | `RatingUrgencyController` | Unvalidated Rating Input Range & Type | Unresolved |
| **REL-007** | Low | Data Integrity | `DrivingForce`, `DrivingForceRating` | Cascade Integrity on Soft-Deleted Entities | Unresolved |

---

## 3. Detailed Reliability Analyses

### REL-001: Defensive Date Range Parsing
* **Severity:** Medium
* **Target Files:**
  * [`app/Services/DrivingForceService.php`](file:///c:/laragon/www/foresight-radar/app/Services/DrivingForceService.php#L19-L33)
  * [`app/Models/DrivingForceRating.php`](file:///c:/laragon/www/foresight-radar/app/Models/DrivingForceRating.php)
* **Initial Defect:** Calling scopes with non-array or single-element `date` query strings triggered unhandled `Undefined array key 1` runtime exceptions, producing HTTP 500 crashes.
* **Current Remediation:**
  * `resolveDateRange()` checks `is_array($params['date']) && count($params['date']) === 2` and defaults to current month boundaries (`startOfMonth` to `endOfMonth`) when parameters are missing or malformed.
* **Verification Evidence:**
  * Test: `Tests\Feature\Reliability\DateRangeValidationTest`
  * Executed: `test_visualization_endpoints_handle_missing_or_malformed_date_parameters_without_crashing` (PASS)

---

### REL-002: User Account Creation Collision & Role Overwrite
* **Severity:** Medium
* **Target Files:**
  * [`app/Http/Controllers/UserController.php`](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/UserController.php#L58-L67)
* **Initial Defect:** `UserController::create()` executed `User::firstOrCreate(['name' => $request->name], ...)`. If an administrator registered a user with a common display name matching an existing record, the existing user was retrieved and their assigned role was overwritten via `syncRoles()`.
* **Current Remediation:** Replaced `firstOrCreate` with direct `User::create()`, relying on unique email constraints.
* **Verification Evidence:**
  * Test: `Tests\Feature\Reliability\UserAccountIntegrityTest`
  * Executed: `test_creating_user_with_duplicate_name_does_not_overwrite_existing_user` (PASS)

---

### REL-003: Idempotent UUID Preservation on Rating Updates
* **Severity:** Low
* **Target Files:**
  * [`app/Http/Controllers/TimeHorizonController.php`](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/TimeHorizonController.php#L63-L72)
* **Initial Defect:** Passing `'uuid' => (string) Str::uuid()` in the update attributes array of `updateOrCreate()` caused Eloquent to regenerate primary UUIDs every time an existing time horizon was revised.
* **Current Remediation:** The controller only generates UUIDs when creating new model instances, preserving UUID immutability.
* **Verification Evidence:**
  * Test: `Tests\Feature\Reliability\TimeHorizonUuidTest`
  * Executed: `test_updating_time_horizon_preserves_original_rating_uuid` (PASS)

---

### REL-004: Unvalidated Lifecycle State Transitions (State Machine Absence)
* **Severity:** High
* **Target Files:**
  * [`app/Http/Controllers/ApprovalController.php`](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/ApprovalController.php#L49-L62)
  * [`app/Models/DrivingForce.php`](file:///c:/laragon/www/foresight-radar/app/Models/DrivingForce.php#L26-L35)
* **Defect Description:**
  * In `ApprovalController::create()`:
  ```php
  if($request->status == 'CLOSED') {
      $driving_force->closed_at = now();
  }
  if($request->status == 'APPROVED') {
      $driving_force->approved_at = now();
  }
  $driving_force->status = $request->status;
  $driving_force->save();
  ```
  * `$request->status` is assigned directly to the model without validation against allowed status values (`PENDING`, `APPROVED`, `CLOSED`).
  * The application does not enforce prerequisites for approval: an item with zero ratings, unassessed time horizons, or missing status actions can be transitioned directly to `CLOSED`.
  * Status is stored as a standard string column without a database enum or check constraint.
* **Remediation:**
  * Introduce an explicit State Machine pattern or enum (`App\Enums\DrivingForceStatus`).
  * Validate status transitions (`canTransitionTo()`).
  * Enforce prerequisite checks: a driving force must have an associated `DrivingForceRating` with `impact_analysis`, `uncertainty_analysis`, and `status_action_id` before entering `APPROVED` or `CLOSED`.

---

### REL-005: Hardcoded Priority Identifiers Across Controllers
* **Severity:** Medium
* **Target Files:**
  * [`app/Http/Controllers/RatingUrgencyController.php`](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/RatingUrgencyController.php#L62-L68)
  * [`app/Http/Controllers/StatusActionController.php`](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/StatusActionController.php#L66-L74)
* **Defect Description:**
  * Both controllers assign priority directly by numeric primary key:
  ```php
  $driving_force_rating->priority_id = 1; // Assumed High
  $driving_force_rating->priority_id = 2; // Assumed Medium
  $driving_force_rating->priority_id = 3; // Assumed Low
  ```
  * If database seeding changes or non-sequential IDs are generated, priority calculation will assign incorrect foreign keys or fail with database integrity exceptions.
  * In addition, the priority calculation logic is duplicated between `RatingUrgencyController` and `StatusActionController` with slight variation in conditional operator checks.
* **Remediation:**
  * Extract priority resolution into a single domain service or method on `DrivingForceRating` (e.g., `calculatePriority()`).
  * Resolve priority records via constant code identifiers or names (`Priority::where('name', 'High')->first()->id`).

---

### REL-006: Unvalidated Rating Input Range and Types
* **Severity:** Medium
* **Target Files:**
  * [`app/Http/Controllers/RatingUrgencyController.php`](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/RatingUrgencyController.php#L50-L59)
* **Defect Description:**
  * `RatingUrgencyController::create` accepts raw `Request $request` without a FormRequest.
  * `$request['value']` is directly written to `impact_analysis` or `uncertainty_analysis`.
  * No validation enforces integer values within the allowed 1-10 rating scale. Passing negative numbers, strings, or numbers exceeding 10 can corrupt scoring formulas and break scatter chart coordinate systems.
* **Remediation:**
  * Create `RatingUrgencyRequest` with validation rules:
  ```php
  'type' => ['required', Rule::in(['impact', 'uncertainty'])],
  'value' => ['required', 'integer', 'min:1', 'max:10'],
  ```

---

### REL-007: Cascade Integrity on Soft-Deleted Entities
* **Severity:** Low
* **Target Files:**
  * [`app/Models/DrivingForce.php`](file:///c:/laragon/www/foresight-radar/app/Models/DrivingForce.php#L17)
  * [`app/Models/DrivingForceRating.php`](file:///c:/laragon/www/foresight-radar/app/Models/DrivingForceRating.php)
* **Defect Description:**
  * `DrivingForce` uses the `SoftDeletes` trait, but related child records (`DrivingForceRating`, `ActionReason`) do not.
  * When a driving force is soft-deleted, ratings and action logs remain in the database without soft-deletion markers. If raw relational queries join on `driving_force_ratings` without checking parent deletion status, orphaned records can surface in calculations.
* **Remediation:**
  * Apply `SoftDeletes` to `DrivingForceRating` or configure model deleting events to cascade soft-deletes to children.
