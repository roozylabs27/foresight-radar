# Integration Gaps Analysis - Foresight Radar

This document identifies and categorizes existing integration gaps across Foresight Radar. Each gap is analyzed across five rigorous engineering dimensions: current behavior, expected behavior, business impact, technical root cause, and recommended solution.

---

## 1. Data Integration Gaps

### GAP-DATA-01: Asymmetric Visibility Between Weak Signals and Spawned Driving Forces
* **Current Behavior:** When an analyst accepts a weak signal in Stage 0 (`/signals`) via `accept_new` or `accept_link`, a record is inserted into `signal_driving_force`. However, the Driving Force Register (`/driving-forces`), Stage 4 Status of Action, and Stage 7 Visualizations never display or link back to the originating signal sources.
* **Expected Behavior:** Users viewing a Driving Force in the Register or Radar should be able to inspect originating weak signals, source URLs, and extraction dates.
* **Business Impact:** Loss of intelligence provenance. Executive decision-makers cannot audit the primary evidence supporting why a driving force was initiated.
* **Technical Root Cause:** The `DrivingForce` model defines a `signals()` BelongsToMany relationship, but none of the driving force controllers ([`DrivingForceController`](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/DrivingForceController.php), [`ForesightRadarController`](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/ForesightRadarController.php)) eager load or serialize `signals` to the Inertia frontend.
* **Recommended Solution:** Eager-load `signals:id,title,url` in `DrivingForceController::index()` and `DrivingForceRating::foresight_radar()`, rendering an "Evidence Sources" badge or tab inside the detail modal.

### GAP-DATA-02: Duplicated Query Definitions Across Visualization Methods
* **Current Behavior:** Data queries for the three Stage 7 visualizations are duplicated in static methods on [`DrivingForceRating.php`](file:///c:/laragon/www/foresight-radar/app/Models/DrivingForceRating.php) (`foresight_radar()`, `prioritizing()`, `registered_list()`), duplicating eager-loading trees and status filtering logic.
* **Expected Behavior:** Centralized query scope (e.g., `scopeApprovedForReporting()`) on the `DrivingForceRating` model or a dedicated `ReportingQueryService`.
* **Business Impact:** High maintenance overhead; risk that a change in reporting filters (e.g., excluding archived items) is applied to Radar but accidentally omitted from Registered List.
* **Technical Root Cause:** Rapid prototyping led to three separate static helper methods inside the Eloquent model instead of shared scopes or a service class.
* **Recommended Solution:** Refactor query logic into reusable Eloquent local scopes on `DrivingForceRating`:
  ```php
  public function scopeApprovedForVisualization(Builder $query): Builder
  {
      return $query->whereHas('drivingForce', fn($q) => $q->where('status', 'APPROVED'))
          ->whereNotNull('status_action_id')
          ->with(['drivingForce.dimension', 'drivingForce.environment', 'timeHorizon', 'priority', 'statusAction']);
  }
  ```

---

## 2. Workflow Integration Gaps

### GAP-WORK-01: Terminal State Lockout on Closed Items with No Reopening Capability
* **Current Behavior:** When an executive transitions a driving force to `CLOSED` in Stage 5 Approval, the record enters [`ClosedItemsController`](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/ClosedItemsController.php). There is no user action, button, or endpoint to reopen, revise, or reactivate a closed item.
* **Expected Behavior:** In real-world strategic foresight, external macro-environmental shifts may cause previously dismissed or closed trends to re-emerge. Executives or authorized managers should be able to submit a "Reopen for Re-evaluation" request with mandatory justification.
* **Business Impact:** High friction; analysts are forced to manually recreate identical driving forces from scratch, losing historical rating data and rationale logs.
* **Technical Root Cause:** No route or controller method exists in `ClosedItemsController` or `ApprovalController` to transition `status = 'CLOSED'` back to `PENDING`.
* **Recommended Solution:** Implement a `POST /closed-items/{id}/reopen` route gated by `can:create-approval-items` or `manage-driving-forces`, which resets status to `PENDING` and logs a reopening rationale into `action_reasons`.

### GAP-WORK-02: BOD Role Permission Asymmetry in Approval Governance
* **Current Behavior:** In [`database/data/role.json`](file:///c:/laragon/www/foresight-radar/database/data/role.json#L28-L43), the `bod` (Board of Directors) role is granted `view-approval-items`, but is NOT granted `create-approval-items`. Meanwhile, the `admin` role possesses `create-approval-items`.
* **Expected Behavior:** Members of the Board of Directors or Senior Management should possess the authority to approve, reject, or close items in the governance queue.
* **Business Impact:** Board members can log in and view the approval list, but when they attempt to click "Approve", the request is rejected with a 403 Forbidden error because policy checks `can:create-approval-items`.
* **Technical Root Cause:** Permission naming inconsistency: the action button in the approval queue requires `create-approval-items` instead of a semantic permission like `approve-driving-forces` or granting `create-approval-items` to `bod`.
* **Recommended Solution:** Either grant `create-approval-items` to the `bod` role in `role.json` / seeders, or introduce a dedicated `decide-approval-items` permission granted to both `admin` and `bod`.

---

## 3. Business-Rule Consistency Gaps

### GAP-RULE-01: Overlapping Priority Setting Between Rating of Urgency and Prioritizing Grid
* **Current Behavior:**
  * In Stage 3 [`RatingUrgencyController`](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/RatingUrgencyController.php), priority is calculated deterministically via `DrivingForceService::calculatePriority($impact, $uncertainty)`.
  * However, [`PrioritizingController`](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/PrioritizingController.php#L37-L50) has a route `POST /visualization/prioritizing/{id}` that allows manually setting `priority_id` without updating the underlying impact and uncertainty numerical scores.
* **Expected Behavior:** The strategic relationship between Impact/Uncertainty scores and Priority must be consistent. Either:
  1. Priority is strictly derived from the 2D Cartesian coordinates (no manual override); OR
  2. Manual override is explicitly marked as an "Executive Override" with an audit reason, so scores and priority do not mysteriously contradict each other.
* **Business Impact:** An item could have Impact=10 and Uncertainty=10 (naturally High), but be manually reassigned to Low in Prioritizing, causing confusion across executive reports.
* **Technical Root Cause:** Dual entry points for mutating `driving_force_ratings.priority_id` without shared synchronization rules.
* **Recommended Solution:** Clarify business requirements. If manual priority overriding is desired, require a rationale note and display an "Overridden" badge; otherwise, deprecate direct priority modification in `PrioritizingController`.

### GAP-RULE-02: Soft Deletes vs Reporting Integrity
* **Current Behavior:** [`DrivingForce`](file:///c:/laragon/www/foresight-radar/app/Models/DrivingForce.php) uses Laravel's `SoftDeletes` trait, but [`DrivingForceRating`](file:///c:/laragon/www/foresight-radar/app/Models/DrivingForceRating.php) and [`ActionReason`](file:///c:/laragon/www/foresight-radar/app/Models/ActionReason.php) do not use `SoftDeletes`.
* **Expected Behavior:** When a driving force is soft-deleted, its ratings and historical reasons should remain archived or soft-deleted in lockstep.
* **Business Impact:** If a soft-deleted driving force is later force-deleted or restored, associated rating and reason records could become orphaned or out of sync.
* **Technical Root Cause:** Asymmetric application of `SoftDeletes` trait across child relational models.
* **Recommended Solution:** Add `SoftDeletes` to `DrivingForceRating` and ensure model events handle cascading soft deletion.

---

## 4. UX and Navigation Integration Gaps

### GAP-UX-01: Lack of Forward-Navigation Links Across Sequential Stages
* **Current Behavior:** After an analyst creates a Driving Force in Stage 1 (`/driving-forces`), the success notification displays a static message. The user must manually navigate via the sidebar to Stage 2 (`/time-horizon`), find the newly created item, and assign a horizon.
* **Expected Behavior:** Sequential workflow guidance: Upon saving a Driving Force, the confirmation modal should provide a quick action button: "Proceed to Stage 2: Assign Time Horizon".
* **Business Impact:** High cognitive load and unnecessary friction, especially for new analysts onboarding onto the corporate foresight methodology.
* **Technical Root Cause:** Stages are developed as autonomous CRUD screens rather than a guided multi-step pipeline.
* **Recommended Solution:** Add intelligent workflow banner alerts or action links pointing to the next incomplete stage for each driving force.

### GAP-UX-02: Absence of Incomplete Stage Indicator in Driving Force Register
* **Current Behavior:** The Driving Force Register table lists names, dimensions, and environments, but does not display which stages have been completed (Time Horizon assigned? Scored in Urgency? Status Action assigned? Approved?).
* **Expected Behavior:** A pipeline progress stepper or status pill (e.g., "Stage 2 of 5 Complete") in the main register table.
* **Business Impact:** Analysts cannot easily discern which driving forces are stuck midway through the evaluation process without checking each individual screen.
* **Technical Root Cause:** `DrivingForceController::index()` does not compute a stage progression completion percentage.
* **Recommended Solution:** Add a lightweight accessor `stage_progress` on the `DrivingForce` model that evaluates completed stages and renders a visual progress indicator.
