# Cross-Module Integration Test Plan - Foresight Radar

This document evaluates existing test coverage across module boundaries in Foresight Radar and outlines a comprehensive suite of recommended integration and end-to-end tests to guarantee behavioral integrity across lifecycle transitions.

---

## 1. Current Cross-Module Test Coverage Assessment

### 1.1 Existing Coverage Strengths
* **Complete Linear Lifecycle Tested:** [`tests/Feature/Workflow/ForesightLifecycleTest.php`](file:///c:/laragon/www/foresight-radar/tests/Feature/Workflow/ForesightLifecycleTest.php) verifies the happy path from Driving Force creation through Time Horizon assignment, Urgency scoring, Status of Action assignment, Approval, and Closed Items archival.
* **Granular Priority Calculation Tested:** [`tests/Unit/PriorityCalculationTest.php`](file:///c:/laragon/www/foresight-radar/tests/Unit/PriorityCalculationTest.php) exhausts edge cases (boundary scores of 1, 3, 7, 10) for mathematical priority determination.
* **Security & Authorization Boundaries Tested:** [`tests/Feature/Security/VisualizationAuthorizationTest.php`](file:///c:/laragon/www/foresight-radar/tests/Feature/Security/VisualizationAuthorizationTest.php) and [`tests/Feature/Security/RoleEscalationTest.php`](file:///c:/laragon/www/foresight-radar/tests/Feature/Security/RoleEscalationTest.php) verify route authorization barriers.

### 1.2 Identified Coverage Blindspots
1. **Stage 0 to Stage 1 Transition:** No integration test verifies that accepting a signal via `accept_new` correctly instantiates a `DrivingForce`, sets initial `DrivingForceRating` with `status = 'PENDING'`, populates `signal_driving_force`, and verifies rollback when invalid data is provided.
2. **Approval-to-Visualization Filter Guarantees:** No test asserts the negative and positive filtering rules:
   * That an item with `status = 'PENDING'` is omitted from Foresight Radar, Prioritizing, and Registered List.
   * That upon transition to `status = 'APPROVED'`, the item immediately surfaces with matching coordinates across all three reporting endpoints.
   * That upon transition to `status = 'CLOSED'`, the item immediately vanishes from all three reporting endpoints.
3. **Prerequisite Gating Enforcement:** No test explicitly tests that attempting to approve a driving force whose `status_action_id` is null fails or is rejected.
4. **Soft-Delete Cascade & Ghost Record Prevention:** No test verifies that soft-deleting a driving force excludes its ratings from dashboard KPI aggregations.

---

## 2. Recommended Integration Test Specifications

### TEST-INT-01: Weak Signal Promotion to Driving Force Integration
* **Test Name:** `test_signal_promotion_creates_driving_force_and_pivot_link_atomically`
* **Purpose:** Proves that promoting a candidate signal seamlessly creates the Driving Force, initializes the rating, and creates the pivot link in a single atomic transaction.
* **Modules Covered:** FEAT-013 (Signal Ingestion), FEAT-003 (Driving Force Register).
* **Test Type:** Feature / Integration Test.
* **Target File:** `tests/Feature/Workflow/SignalToDrivingForceWorkflowTest.php`
* **Preconditions:** Authenticated user with `ingest-signals` and `review-signals` permissions; pre-existing `Source` and pending `Signal`.
* **Steps:**
  1. Post to `/signals/{signal}/review` with action `accept_new`, providing `name`, `explanation`, `dimension_id`, and `environment_id`.
  2. Verify HTTP 302 redirect with success flash.
* **Assertions:**
  1. `assertDatabaseHas('signals', ['id' => $signal->id, 'status' => 'ACCEPTED'])`.
  2. `assertDatabaseHas('driving_forces', ['name' => 'Promoted Signal Title'])`.
  3. `assertDatabaseHas('driving_force_ratings', ['status' => 'PENDING'])`.
  4. `assertDatabaseHas('signal_driving_force', ['signal_id' => $signal->id])`.

### TEST-INT-02: Approval State Transitions Instantly Propagate to Reporting Endpoints
* **Test Name:** `test_approval_lifecycle_governs_visibility_across_all_three_visualizations`
* **Purpose:** Verifies that unapproved items never leak to reports, approved items appear with accurate coordinates, and closed items immediately disappear.
* **Modules Covered:** FEAT-007 (Approval Governance), FEAT-009 (Foresight Radar), FEAT-010 (Prioritizing), FEAT-011 (Registered List).
* **Test Type:** Integration Test.
* **Target File:** `tests/Feature/Workflow/ApprovalVisualizationPropagationTest.php`
* **Preconditions:** Fully scored driving force with Time Horizon, Impact=8, Uncertainty=8, Status of Action assigned, initial `status = 'PENDING'`.
* **Steps & Assertions:**
  * **Phase A (Pending State):**
    * Request `GET /visualization/foresight-radar` -> Assert item ID NOT present in payload.
    * Request `GET /visualization/prioritizing` -> Assert item ID NOT present in payload.
    * Request `GET /visualization/registered-list` -> Assert item ID NOT present in payload.
  * **Phase B (Transition to APPROVED):**
    * Post approval action `status = 'APPROVED'`.
    * Request `GET /visualization/foresight-radar` -> Assert item present with correct angle/radius metadata.
    * Request `GET /visualization/prioritizing` -> Assert item present in High priority coordinate cluster.
    * Request `GET /visualization/registered-list` -> Assert item present in tabular payload.
  * **Phase C (Transition to CLOSED):**
    * Post approval action `status = 'CLOSED'`.
    * Request `GET /visualization/foresight-radar` -> Assert item ID NOT present.
    * Request `GET /visualization/prioritizing` -> Assert item ID NOT present.
    * Request `GET /visualization/registered-list` -> Assert item ID NOT present.
    * Request `GET /closed-items` -> Assert item present with closure timestamp.

### TEST-INT-03: Prerequisite Gating Enforcement for Status of Action
* **Test Name:** `test_cannot_assign_status_action_without_prior_urgency_scores`
* **Purpose:** Guarantees that an analyst cannot jump from Stage 2 directly to Stage 4 without scoring Impact and Uncertainty.
* **Modules Covered:** FEAT-005 (Rating of Urgency), FEAT-006 (Status of Action).
* **Test Type:** Integration Test.
* **Preconditions:** Driving force with Time Horizon assigned, but `impact_analysis` and `uncertainty_analysis` remain `NULL`.
* **Steps:** Submit `POST /status-action` with valid `status_action_id` and justification.
* **Assertions:** Assert response status is 422 or redirect with validation error indicating prerequisite scoring is missing; assert `status_action_id` remains `NULL` in database.

### TEST-INT-04: Soft Deletion Does Not Pollute Dashboard KPI Counters
* **Test Name:** `test_soft_deleted_driving_force_is_excluded_from_dashboard_aggregates`
* **Purpose:** Proves that deleting a driving force immediately decrements dashboard counters and excludes its rating from stage distributions.
* **Modules Covered:** FEAT-003 (Driving Force Register), FEAT-012 (Dashboard).
* **Test Type:** Integration Test.
* **Preconditions:** Seed 5 active driving forces; capture initial `/dashboard` KPI count.
* **Steps:** Soft-delete 2 driving forces via `DELETE /driving-forces/{id}`.
* **Assertions:** Request `/dashboard` -> Assert total driving forces count equals exactly 3; assert stage distribution bar charts reflect only the 3 active records.

---

## 3. Implementation Roadmap for Cross-Module Tests

| Test Priority | Test Identifier | Module Target | Implementation Effort |
|---|---|---|---|
| P0 | `TEST-INT-02` | Approval -> Visualization Propagation | 1 day |
| P0 | `TEST-INT-01` | Signal Ingestion -> Driving Force Creation | 1 day |
| P1 | `TEST-INT-03` | Prerequisite Gating Validation | 0.5 day |
| P1 | `TEST-INT-04` | Soft Delete Cascade Verification | 0.5 day |
