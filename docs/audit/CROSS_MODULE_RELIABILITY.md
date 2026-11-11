# Cross-Module Reliability & Failure Resilience Audit - Foresight Radar

This document evaluates system reliability, failure handling, and state resilience across module boundaries in Foresight Radar. It catalogs specific cross-module failure scenarios, analyzes transactional protections, and outlines recommended mitigations.

---

## 1. Cross-Module Failure Scenario Analysis

### SCEN-REL-01: Multi-Step Weak Signal Promotion & Driving Force Creation
* **Scenario Description:** An analyst clicks "Promote to New Driving Force" on a candidate signal in Stage 0. The system must create a `DrivingForce`, create an initial `DrivingForceRating`, insert a record in the `signal_driving_force` pivot table, and update the signal status to `ACCEPTED`.
* **Modules Involved:** FEAT-013 (Signal Ingestion), FEAT-003 (Driving Force Register).
* **Expected Failure Behavior:** If any step fails (e.g., database timeout on pivot insert or validation failure), all writes must roll back atomically, leaving the signal in `PENDING` status with no orphaned driving force created.
* **Actual Failure Behavior:** Code in [`SignalIngestionController::review()`](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/SignalIngestionController.php#L77-L122) wraps all four operations inside `DB::transaction()`. In case of failure, an exception is caught, transaction is rolled back, and an error flash message is returned.
* **Risk Level:** Low.
* **Recommended Mitigation:** Existing transactional protection is sound. Add a regression test specifically asserting rollback behavior when pivot creation throws an exception.

### SCEN-REL-02: External Web Scraping Network Timeout During Signal Ingestion
* **Scenario Description:** An analyst inputs a slow or unresponsive external URL in Stage 0 for signal parsing.
* **Modules Involved:** FEAT-013 (Signal Ingestion), External Network / Web Host.
* **Expected Failure Behavior:** The HTTP client must respect a strict timeout (e.g., 5-10 seconds), capture the connection failure gracefully, mark the source record as `FAILED`, and present a clear user-facing error message without hanging the PHP process.
* **Actual Failure Behavior:** [`SignalExtractionService::fetchUrl()`](file:///c:/laragon/www/foresight-radar/app/Services/SignalExtractionService.php#L45-L65) uses `Http::timeout(10)->get($url)`. If a timeout or DNS error occurs, it catches `ConnectionException`, marks `sources.status = 'FAILED'`, and returns an empty signal set with an informative error flash.
* **Risk Level:** Low.
* **Recommended Mitigation:** Ensure timeout configuration is externalized to `config/services.php` for environment tuning.

### SCEN-REL-03: Status of Action Assignment with Concurrent Scoring Mutation
* **Scenario Description:** User A updates Impact/Uncertainty scores in Stage 3 while User B simultaneously assigns a Status of Action in Stage 4 for the same driving force.
* **Modules Involved:** FEAT-005 (Rating of Urgency), FEAT-006 (Status of Action).
* **Expected Failure Behavior:** Optimistic concurrency locking or atomic prerequisite re-verification ensures that Status of Action is assigned against the latest verified scores.
* **Actual Failure Behavior:** Neither `driving_forces` nor `driving_force_ratings` implements optimistic concurrency locking (no `lockVersion` or `updated_at` check). Last write wins. If User A sets impact/uncertainty back to null while User B is submitting Stage 4, User B might bypass the gate or encounter race condition errors.
* **Risk Level:** Medium.
* **Recommended Mitigation:** Introduce optimistic concurrency locking or explicit transaction isolation (`SELECT ... FOR UPDATE`) in `StatusActionController::update()`.

### SCEN-REL-04: Executive Approval Transition with Missing Prior Status Action
* **Scenario Description:** An API request is submitted to `POST /approval/{id}/action` for a driving force whose `status_action_id` was cleared or not yet assigned.
* **Modules Involved:** FEAT-007 (Executive Approval), FEAT-006 (Status of Action).
* **Expected Failure Behavior:** The backend rejects the request with an explicit 422 Unprocessable Content error explaining that the item has not completed Status of Action.
* **Actual Failure Behavior:** While the approval query view filters out items without `status_action_id`, the action handler [`ApprovalController::action()`](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/ApprovalController.php#L62-L105) does not explicitly re-verify that `rating.status_action_id` is non-null before transitioning `status = 'APPROVED'`.
* **Risk Level:** High.
* **Recommended Mitigation:** Add an explicit domain assertion in `ApprovalController::action()`:
  ```php
  if (is_null($drivingForce->rating?->status_action_id)) {
      return back()->withErrors(['error' => 'Cannot approve a driving force without an assigned Status of Action.']);
  }
  ```

### SCEN-REL-05: Cascading Soft Deletions and Relational Orphan Risks
* **Scenario Description:** An administrator soft-deletes a `DrivingForce` in Stage 1.
* **Modules Involved:** FEAT-003 (Driving Force Register), FEAT-004, FEAT-005, FEAT-006, FEAT-007.
* **Expected Failure Behavior:** The associated `DrivingForceRating` and historical `ActionReason` rows should either be cascade soft-deleted or automatically excluded from all background aggregations.
* **Actual Failure Behavior:** Because `driving_force_ratings` lacks a `deleted_at` column, raw database queries on `driving_force_ratings` that omit `whereHas('drivingForce')` will include ratings belonging to deleted driving forces. Dashboard summary queries that query `DrivingForceRating` directly risk counting ghost records.
* **Risk Level:** Medium.
* **Recommended Mitigation:** Add `SoftDeletes` to `driving_force_ratings` and enforce cascading soft deletes using Eloquent model observers.

---

## 2. Summary of Transactional Integrity Across Controllers

| Controller / Action | DB Transaction Used? | Atomic Rollback Verified? | Concurrency Protection |
|---|---|---|---|
| `SignalIngestionController::ingest()` | Yes | Yes | None |
| `SignalIngestionController::review()` | Yes | Yes | None |
| `DrivingForceController::store()` | Yes (`DrivingForceService`) | Yes | None |
| `DrivingForceController::destroy()` | No (Single Model SoftDelete) | N/A | None |
| `TimeHorizonController::update()` | No (Single Model Update) | N/A | None |
| `RatingUrgencyController::update()` | No (Single Model Update) | N/A | None |
| `StatusActionController::update()` | Yes (Rating Update + ActionReason Insert) | Yes | None |
| `ApprovalController::action()` | No (Single Model Update) | N/A | None |
