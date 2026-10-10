# Cross-Module Dependency Analysis - Foresight Radar

This document delivers a rigorous architectural audit of dependencies between features and modules across Foresight Radar. It identifies explicit and hidden couplings, evaluates data and workflow contracts, and assesses fragility across module boundaries.

---

## 1. Dependency Taxonomy & Classification Framework

Dependencies across Foresight Radar are classified into six structural types:

1. **Direct Dependency:** One module directly imports, invokes, or constructs another module's classes, services, or endpoints.
2. **Data Dependency:** Modules share or consume records in the relational schema without direct code invocation.
3. **Workflow Dependency:** A downstream module requires an upstream entity to reach a specific lifecycle state or satisfy specific non-null invariants before processing is permitted.
4. **Authorization Dependency:** Feature access is gated by permissions, roles, or ownership policies defined and managed elsewhere.
5. **Presentation Dependency:** A frontend component or visualization page relies on the specific payload shape or visual conventions emitted by another module.
6. **External Dependency:** Behavior relies on an external network endpoint, third-party library, or remote API service.

---

## 2. Exhaustive Cross-Module Dependency Register

### 2.1 FEAT-013 (Signal Ingestion) -> FEAT-003 (Driving Force Register)
* **Dependency Type:** Direct & Workflow Dependency
* **1. Source of Truth:** `sources` and `signals` tables for candidate signals; `driving_forces` table for promoted concepts.
* **2. Business Rule Owner:** [`SignalIngestionController`](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/SignalIngestionController.php) owns candidate review rules; [`DrivingForceService`](file:///c:/laragon/www/foresight-radar/app/Services/DrivingForceService.php) owns driving force creation rules.
* **3. Data Consumer:** Stage 1 Driving Force Register consumes promoted signals.
* **4. Explicit vs Hidden:** Explicit in [`SignalIngestionController::review()`](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/SignalIngestionController.php#L77-L122) via `DrivingForceService::create()`. Hidden coupling in the `signal_driving_force` pivot table, which is not surfaced in the Driving Force UI.
* **5. Enforcement Mechanism:** Database foreign keys (`signal_driving_force` cascades) and transactional controller logic.
* **6. Effect of Source Change:** Updating signal text after promotion does not update the spawned driving force (independent copies).
* **7. Effect of Source Deletion:** Deleting a signal cascades deletion of pivot entries, leaving the driving force intact.
* **8. Stale/Inconsistent Risk:** Low. However, analysts viewing the driving force cannot easily trace back to the originating signal without inspecting raw database tables.
* **9. Circular Coupling Risk:** None. The flow is strictly unidirectional from Stage 0 to Stage 1.
* **10. Test Verification:** Tested in [`tests/Feature/SignalIngestionTest.php`](file:///c:/laragon/www/foresight-radar/tests/Feature/SignalIngestionTest.php).

### 2.2 FEAT-003 (Driving Force Register) -> FEAT-004 (Time Horizon Assessment)
* **Dependency Type:** Data & Workflow Dependency
* **1. Source of Truth:** `driving_forces` for core identity; `driving_force_ratings` for the foreign key `time_horizon_id`.
* **2. Business Rule Owner:** [`TimeHorizonController`](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/TimeHorizonController.php) controls horizon assignment.
* **3. Data Consumer:** Foresight Radar visualization (determines concentric ring distance).
* **4. Explicit vs Hidden:** Explicit through Eloquent relationship `DrivingForce::hasOne(DrivingForceRating)`.
* **5. Enforcement Mechanism:** Backend controller validation (`exists:time_horizons,id`) and database foreign key.
* **6. Effect of Source Change:** Changing a driving force name or environment does not invalidate the time horizon.
* **7. Effect of Source Deletion:** Soft deleting a driving force hides it from active time horizon assignment views.
* **8. Stale/Inconsistent Risk:** Low. If a time horizon is changed, downstream radar displays update immediately upon page reload.
* **9. Circular Coupling Risk:** None.
* **10. Test Verification:** Covered by feature tests.

### 2.3 FEAT-003 & FEAT-004 -> FEAT-005 (Rating of Urgency)
* **Dependency Type:** Data & Workflow Dependency
* **1. Source of Truth:** `driving_force_ratings` (`impact_analysis`, `uncertainty_analysis`, `priority_id`).
* **2. Business Rule Owner:** [`DrivingForceService::calculatePriority()`](file:///c:/laragon/www/foresight-radar/app/Services/DrivingForceService.php#L68-L82).
* **3. Data Consumer:** Status of Action (Stage 4), Prioritizing Matrix (Stage 7), Foresight Radar (Stage 7).
* **4. Explicit vs Hidden:** Explicit in [`RatingUrgencyController`](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/RatingUrgencyController.php).
* **5. Enforcement Mechanism:** Backend validation (`integer|min:1|max:10`) and service computation.
* **6. Effect of Source Change:** Updating numerical scores automatically recomputes and overwrites `priority_id`.
* **7. Effect of Source Deletion:** Not applicable; rating is tied 1-to-1 to driving force.
* **8. Stale/Inconsistent Risk:** Very low. Centralized computation in `DrivingForceService` prevents arithmetic divergence.
* **9. Circular Coupling Risk:** None.
* **10. Test Verification:** Covered in [`tests/Feature/RatingUrgencyTest.php`](file:///c:/laragon/www/foresight-radar/tests/Feature/RatingUrgencyTest.php).

### 2.4 FEAT-005 (Rating of Urgency) -> FEAT-006 (Status of Action)
* **Dependency Type:** Workflow & Data Dependency
* **1. Source of Truth:** `driving_force_ratings` for status action pointer; `action_reasons` for historical justification.
* **2. Business Rule Owner:** [`StatusActionController`](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/StatusActionController.php) enforces prerequisite gate (`impact_analysis` and `uncertainty_analysis` must not be null).
* **3. Data Consumer:** Executive Approval (Stage 5), Radar and Registered List (Stage 7).
* **4. Explicit vs Hidden:** Explicit precondition check in controller update handler.
* **5. Enforcement Mechanism:** Backend validation rule and database transaction logging into `action_reasons`.
* **6. Effect of Source Change:** Re-scoring impact/uncertainty does not invalidate already assigned status action, but may trigger re-justification in business workflows.
* **7. Effect of Source Deletion:** Inactive driving forces are excluded from status action assignment table.
* **8. Stale/Inconsistent Risk:** Low. Audit records in `action_reasons` are append-only.
* **9. Circular Coupling Risk:** None.
* **10. Test Verification:** Covered in automated feature test suite.

### 2.5 FEAT-006 (Status of Action) -> FEAT-007 (Executive Approval Governance)
* **Dependency Type:** Workflow & Authorization Dependency
* **1. Source of Truth:** `driving_forces.status` (`PENDING`, `APPROVED`, `REJECTED`, `CLOSED`), `approved_at`, `closed_at`, `approved_by`.
* **2. Business Rule Owner:** [`ApprovalController`](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/ApprovalController.php).
* **3. Data Consumer:** All Stage 7 Visualizations (Foresight Radar, Prioritizing, Registered List) and Closed Items (Stage 6).
* **4. Explicit vs Hidden:** Explicit queue filter: `whereHas('rating', fn($q) => $q->whereNotNull('status_action_id'))`.
* **5. Enforcement Mechanism:** Controller query scope and authorization policy (`can:create-approval-items`).
* **6. Effect of Source Change:** When an item is transitioned to `APPROVED`, it immediately becomes visible across all visualizations. When `CLOSED`, it immediately moves to Closed Items and vanishes from visualizations.
* **7. Effect of Source Deletion:** Deleting an item removes it from the approval pipeline.
* **8. Stale/Inconsistent Risk:** Low. Transition is atomic.
* **9. Circular Coupling Risk:** None.
* **10. Test Verification:** Verified in feature tests.

### 2.6 FEAT-007 (Approval) -> FEAT-009, FEAT-010, FEAT-011 (Stage 7 Visualizations)
* **Dependency Type:** Data, Workflow, & Presentation Dependency
* **1. Source of Truth:** `driving_forces` status and `driving_force_ratings` dimensional coordinates.
* **2. Business Rule Owner:** [`DrivingForceRating`](file:///c:/laragon/www/foresight-radar/app/Models/DrivingForceRating.php) static query scopes (`foresight_radar()`, `prioritizing()`, `registered_list()`).
* **3. Data Consumer:** Visual canvas components and reporting tables.
* **4. Explicit vs Hidden:** Semi-hidden: Embedded in static query methods on the Eloquent model instead of a dedicated reporting service.
* **5. Enforcement Mechanism:** SQL `WHERE` clauses filtering `driving_forces.status = 'APPROVED'` and non-null foreign keys.
* **6. Effect of Source Change:** If an approved item has its status reverted or closed, it instantly disappears from radar and prioritizing grids on next fetch.
* **7. Effect of Source Deletion:** Soft-deleted or deleted records are omitted from reporting queries.
* **8. Stale/Inconsistent Risk:** Low for data freshness; Medium for query maintainability due to duplicate eager-loading definitions across three separate static methods.
* **9. Circular Coupling Risk:** None. Visualizations are pure consumers.
* **10. Test Verification:** Verified via automated tests and browser testing via Chrome DevTools.

### 2.7 FEAT-001 (Auth & RBAC) -> All Business Features (FEAT-002 through FEAT-013)
* **Dependency Type:** Authorization Dependency
* **1. Source of Truth:** `users`, Spatie `roles`, `permissions` tables.
* **2. Business Rule Owner:** Laravel auth gates and Spatie middleware.
* **3. Data Consumer:** All routes and controllers via `$this->authorize()` or route middleware `can:...`.
* **4. Explicit vs Hidden:** Explicit in `routes/web.php` and controller constructors/methods.
* **5. Enforcement Mechanism:** Laravel authorization pipeline and database role assignments.
* **6. Effect of Source Change:** Revoking a role or permission takes effect on the next HTTP request.
* **7. Effect of Source Deletion:** Deleting a user invalidates active sessions and prevents further actions.
* **8. Stale/Inconsistent Risk:** None.
* **9. Circular Coupling Risk:** None.
* **10. Test Verification:** Covered in authentication and policy tests.

---

## 3. Structural Coupling & Fragility Summary

| Dependency Axis | Coupling Level | Fragility Rating | Identified Fragility Factor |
|---|---|---|---|
| Signal Ingestion -> Driving Force Register | Moderate | Low | Promoted driving forces lack an interactive UI link back to originating signals. |
| Driving Force Register -> Rating Profile | Tight (1-to-1) | Low | Guaranteed by transactional creation in `DrivingForceService::create()`. |
| Rating of Urgency -> Priority Calculation | Moderate | Low | Centralized in `DrivingForceService::calculatePriority()`. |
| Status of Action -> Approval Queue | Tight | Medium | Enforced by controller query filters rather than an explicit state machine engine. |
| Approval State -> Visualizations | Tight | Low | Pure SQL filtering ensures unapproved or closed items never leak into public visualizers. |
| Static Model Queries -> Visualizations | Moderate | High (Code quality) | Three separate static methods in `DrivingForceRating` duplicate eager-loading contracts. |
