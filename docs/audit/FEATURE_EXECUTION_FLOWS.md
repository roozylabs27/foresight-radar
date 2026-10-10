# End-to-End Feature Execution Flows - Foresight Radar

This document details the end-to-end execution paths for all critical features across Foresight Radar. It traces execution from initial user interaction through HTTP routing, controllers, domain services, database transactions, downstream triggers, frontend state updates, and failure recovery.

---

## 1. Flow 1: Weak Signal Ingestion & AI Candidate Extraction (Stage 0)

### 1.1 Step-by-Step Execution Path
* **Step 1 (UI Interaction):** Analyst opens `/signals` ([`resources/js/Pages/Signals/Index.jsx`](file:///c:/laragon/www/foresight-radar/resources/js/Pages/Signals/Index.jsx)) and clicks "Ingest Source" modal or pastes raw content.
* **Step 2 (Form Validation):** React Ant Design form verifies `title` presence and either valid `url` or non-empty `raw_content`.
* **Step 3 (HTTP Request):** `POST /signals/ingest` sent with CSRF token and JSON payload.
* **Step 4 (Routing & Middleware):** Handled in [`routes/web.php`](file:///c:/laragon/www/foresight-radar/routes/web.php#L69-L73) protected by `['auth', 'verified']` middleware and Spatie permission gate `can:ingest-signals`.
* **Step 5 (Request Validation):** [`SignalIngestionController::ingest()`](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/SignalIngestionController.php#L48-L62) validates input using `$request->validate([...])`.
* **Step 6 (Service & Business Logic):** Dispatches to [`SignalExtractionService::extract()`](file:///c:/laragon/www/foresight-radar/app/Services/SignalExtractionService.php). If URL is provided, fetches markup, extracts readable text, and parses potential weak signals (heuristically or via LLM).
* **Step 7 (Model & Persistence):** Creates record in `sources`, iterates parsed signal candidates, and inserts records into `signals` with default `status = 'PENDING'`. Wrapped inside `DB::transaction()`.
* **Step 8 (Downstream Triggers):** None triggered automatically. Signals remain candidate items until human review.
* **Step 9 (Response & State Update):** Redirects back with flash notification; Inertia reloads `sources` and `signals` props, updating the UI table.

### 1.2 Multi-Axis Assessment
1. **Beginning of Workflow:** Analyst submission via web UI.
2. **Data Read:** External HTTP endpoints or raw text inputs.
3. **Data Created/Updated:** New row in `sources`, multiple rows in `signals`.
4. **Business Rules:** URL or text must not be empty; duplicate URL warning logged; signals default to `PENDING`.
5. **Modules Affected:** Signal workspace; indirectly affects Driving Forces when reviewed.
6. **Downstream Behavior:** Candidates become available in review tab.
7. **Frontend State Reflection:** Accurately re-renders list via Inertia server-driven props.
8. **Failure Handling:** HTTP fetch failure catches `RequestException`, sets source `status = 'FAILED'`, and returns error message without crashing.
9. **Data Consistency:** Atomically isolated within transaction.
10. **Test Coverage:** Covered in [`tests/Feature/SignalIngestionTest.php`](file:///c:/laragon/www/foresight-radar/tests/Feature/SignalIngestionTest.php).

---

## 2. Flow 2: Signal Review to Driving Force Handoff (Stage 0 to Stage 1)

### 2.1 Step-by-Step Execution Path
* **Step 1 (UI Interaction):** Analyst reviews pending signal in `/signals` table and selects either "Promote to New Driving Force" (`accept_new`) or "Link to Existing Driving Force" (`accept_link`).
* **Step 2 (Form Validation):** For `accept_new`, requires `name`, `explanation`, `dimension_id`, `environment_id`. For `accept_link`, requires selecting an existing `driving_force_id`.
* **Step 3 (HTTP Request):** `POST /signals/{signal}/review` dispatched.
* **Step 4 (Routing & Middleware):** Validated against `can:review-signals` permission.
* **Step 5 (Controller & Service Logic):** Handled in [`SignalIngestionController::review()`](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/SignalIngestionController.php#L77-L122):
  * If action is `accept_new`: Calls [`DrivingForceService::create()`](file:///c:/laragon/www/foresight-radar/app/Services/DrivingForceService.php#L19-L38), creating `DrivingForce` and initializing `DrivingForceRating`. Then inserts pivot record in `signal_driving_force` and sets signal `status = 'ACCEPTED'`.
  * If action is `accept_link`: Verifies driving force existence, inserts pivot record into `signal_driving_force`, and updates signal `status = 'ACCEPTED'`.
  * If action is `reject`: Sets signal `status = 'REJECTED'` with optional rationale.
* **Step 6 (Response):** Redirects with flash banner.

### 2.2 Multi-Axis Assessment
1. **Beginning of Workflow:** Signal review action in `/signals`.
2. **Data Read:** `signals`, `driving_forces`.
3. **Data Created/Updated:** `signals.status`, `signal_driving_force` pivot, optional new `driving_forces` and `driving_force_ratings`.
4. **Business Rules:** Signal must be in `PENDING` state; linked driving force must exist.
5. **Modules Affected:** Signals and Driving Force Register.
6. **Downstream Behavior:** New driving forces immediately appear in Stage 1 and Stage 2 queues.
7. **Frontend State Reflection:** Signal card updates status badge to "Accepted".
8. **Failure Handling:** Wrapped in database transaction; rolls back both driving force creation and pivot linkage if any error occurs.
9. **Data Consistency:** Fully consistent.
10. **Test Coverage:** Covered in automated feature test suite.

---

## 3. Flow 3: Driving Force Registration & Automatic Rating Initialization (Stage 1)

### 3.1 Step-by-Step Execution Path
* **Step 1 (UI Interaction):** Analyst navigates to `/driving-forces` ([`resources/js/Pages/DrivingForces/Index.jsx`](file:///c:/laragon/www/foresight-radar/resources/js/Pages/DrivingForces/Index.jsx)) and clicks "Create Driving Force".
* **Step 2 (Form Validation):** Client checks required fields: `name` (max 255), `explanation`, `dimension_id`, `environment_id`.
* **Step 3 (HTTP Request):** `POST /driving-forces` with Inertia form helper.
* **Step 4 (Routing & Middleware):** Authenticated, verified, and authorized via [`DrivingForcePolicy::create()`](file:///c:/laragon/www/foresight-radar/app/Policies/DrivingForcePolicy.php#L28-L31) (`can:create-driving-forces`).
* **Step 5 (Request Validation):** [`StoreDrivingForceRequest`](file:///c:/laragon/www/foresight-radar/app/Http/Requests/StoreDrivingForceRequest.php) validates types and foreign key existence (`exists:dimensions,id`, `exists:environments,id`).
* **Step 6 (Service & Database Logic):** [`DrivingForceController::store()`](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/DrivingForceController.php#L38-L50) delegates to [`DrivingForceService::create()`](file:///c:/laragon/www/foresight-radar/app/Services/DrivingForceService.php#L19-L38):
  ```php
  DB::transaction(function () use ($data) {
      $df = DrivingForce::create([...]);
      $df->rating()->create([
          'status' => 'PENDING',
      ]);
      return $df;
  });
  ```
* **Step 7 (Response & Frontend State):** Redirects to `/driving-forces` with flash message. Inertia replaces list data without full page reload.

### 3.2 Multi-Axis Assessment
1. **Beginning of Workflow:** User submission on Driving Force Register.
2. **Data Read:** Form input and taxonomy foreign keys (`dimensions`, `environments`).
3. **Data Created/Updated:** 1 row in `driving_forces`, 1 row in `driving_force_ratings` (`status = 'PENDING'`).
4. **Business Rules:** Every `DrivingForce` must possess exactly one associated `DrivingForceRating` record from creation.
5. **Modules Affected:** Driving Forces, Time Horizon, Rating of Urgency, Dashboard.
6. **Downstream Behavior:** Record immediately surfaces on Time Horizon (`/time-horizon`) and Rating of Urgency (`/rating-urgency`) screens.
7. **Frontend State Reflection:** Modal closes, table inserts new row, success notification shown.
8. **Failure Handling:** Managed by database transaction; partial creation is impossible.
9. **Data Consistency:** Guaranteed 1-to-1 relationship integrity.
10. **Test Coverage:** Covered in [`tests/Feature/DrivingForceTest.php`](file:///c:/laragon/www/foresight-radar/tests/Feature/DrivingForceTest.php).

---

## 4. Flow 4: Time Horizon Temporal Assignment (Stage 2)

### 4.1 Step-by-Step Execution Path
* **Step 1 (UI Interaction):** Analyst opens `/time-horizon` ([`resources/js/Pages/TimeHorizon/Index.jsx`](file:///c:/laragon/www/foresight-radar/resources/js/Pages/TimeHorizon/Index.jsx)), clicks "Assign" or "Edit" on a driving force row.
* **Step 2 (Form Validation):** Selects one radio/select option: Short Term, Medium Term, Long Term, or Ultra Long Term.
* **Step 3 (HTTP Request):** `POST /time-horizon` or `PUT /time-horizon/{id}`.
* **Step 4 (Routing & Controller):** [`TimeHorizonController::update()`](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/TimeHorizonController.php) validates `time_horizon_id` (`required|exists:time_horizons,id`).
* **Step 5 (Database Update):** Updates `driving_force_ratings.time_horizon_id` where `driving_force_id = $id`.
* **Step 6 (Response):** Redirects back with flash; updated time horizon badge renders.

### 4.2 Multi-Axis Assessment
1. **Beginning of Workflow:** Time Horizon queue view.
2. **Data Read:** `driving_forces` with relationships (`rating.timeHorizon`).
3. **Data Created/Updated:** `driving_force_ratings.time_horizon_id`.
4. **Business Rules:** `time_horizon_id` must match valid reference taxonomy ID.
5. **Modules Affected:** Time Horizon, Foresight Radar (quadrant rings), Registered List.
6. **Downstream Behavior:** Positions the item within one of the four concentric rings on the Foresight Radar once approved.
7. **Frontend State Reflection:** Status updates from "Unassigned" to target horizon badge.
8. **Failure Handling:** Validation exception returns 422 with inline form errors.
9. **Data Consistency:** Consistent; updates rating directly.
10. **Test Coverage:** Covered in automated controller tests.

---

## 5. Flow 5: Urgency Rating & Automatic Priority Calculation (Stage 3)

### 5.1 Step-by-Step Execution Path
* **Step 1 (UI Interaction):** Analyst opens `/rating-urgency` ([`resources/js/Pages/RatingUrgency/Index.jsx`](file:///c:/laragon/www/foresight-radar/resources/js/Pages/RatingUrgency/Index.jsx)) and clicks "Score" modal.
* **Step 2 (Form Validation):** Validates Impact Analysis (integer between 1 and 10) and Uncertainty Analysis (integer between 1 and 10).
* **Step 3 (HTTP Request):** `PUT /rating-urgency/{id}` sent.
* **Step 4 (Controller & Service Logic):** Handled in [`RatingUrgencyController::update()`](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/RatingUrgencyController.php):
  * Invokes [`DrivingForceService::calculatePriority($impact, $uncertainty)`](file:///c:/laragon/www/foresight-radar/app/Services/DrivingForceService.php#L68-L82):
    * High Priority: Impact >= 7 AND Uncertainty >= 7.
    * Low Priority: Impact <= 3 AND Uncertainty <= 3.
    * Medium Priority: All other score permutations.
  * Updates `driving_force_ratings` with `impact_analysis`, `uncertainty_analysis`, and determined `priority_id`.
* **Step 5 (Response):** Returns success flash, table re-renders with priority badge.

### 5.2 Multi-Axis Assessment
1. **Beginning of Workflow:** Rating of Urgency view.
2. **Data Read:** `driving_force_ratings` existing scores.
3. **Data Created/Updated:** `impact_analysis`, `uncertainty_analysis`, `priority_id`.
4. **Business Rules:** Scores strictly restricted to integers 1-10; priority derived deterministically.
5. **Modules Affected:** Rating of Urgency, Status of Action (unblocks Stage 4), Prioritizing Matrix, Radar.
6. **Downstream Behavior:** Unblocks Stage 4 (Status of Action) assignment; defines coordinates for Cartesian Prioritizing grid.
7. **Frontend State Reflection:** Score bars and priority badge update dynamically.
8. **Failure Handling:** Values out of range 1-10 rejected with 422 Unprocessable Content.
9. **Data Consistency:** Guaranteed deterministic mapping through centralized domain service method.
10. **Test Coverage:** Tested in [`tests/Feature/RatingUrgencyTest.php`](file:///c:/laragon/www/foresight-radar/tests/Feature/RatingUrgencyTest.php).

---

## 6. Flow 6: Status of Action Assignment & Rationale Audit Logging (Stage 4)

### 6.1 Step-by-Step Execution Path
* **Step 1 (UI Interaction):** Analyst visits `/status-action` ([`resources/js/Pages/StatusAction/Index.jsx`](file:///c:/laragon/www/foresight-radar/resources/js/Pages/StatusAction/Index.jsx)) and clicks "Assign Status Action".
* **Step 2 (Form Validation):** Selects `status_action_id` (Act, Prepare, Watch, Dismiss) and enters mandatory justification `reason` text.
* **Step 3 (HTTP Request):** `POST /status-action` or `PUT /status-action/{id}`.
* **Step 4 (Controller Validation & Business Gates):** [`StatusActionController::update()`](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/StatusActionController.php#L54-L88):
  * Gate check: Verifies that both `impact_analysis` and `uncertainty_analysis` are non-null. If null, aborts or rejects with error indicating item must be scored in Stage 3 first.
  * Database transaction:
    * Updates `driving_force_ratings.status_action_id`.
    * Inserts new audit record in `action_reasons`: `['driving_force_id' => $df->id, 'status_action_id' => $actionId, 'reason' => $reason, 'user_id' => auth()->id()]`.
* **Step 5 (Response):** Redirects with flash banner; UI reflects new status action and rationale history.

### 6.2 Multi-Axis Assessment
1. **Beginning of Workflow:** Status of Action screen.
2. **Data Read:** `driving_force_ratings`, `status_actions`.
3. **Data Created/Updated:** `driving_force_ratings.status_action_id`, new immutable row in `action_reasons`.
4. **Business Rules:** Cannot assign status of action without prior impact and uncertainty scores; rationale text cannot be blank.
5. **Modules Affected:** Status of Action, Approval Governance (unblocks Stage 5 queue).
6. **Downstream Behavior:** Qualifying item now appears on the Executive Approval queue (`/approval`).
7. **Frontend State Reflection:** Assigned action badge and historical rationale drawer updated.
8. **Failure Handling:** Atomic transaction prevents rating assignment without accompanying rationale audit row.
9. **Data Consistency:** Preserves immutable historical audit log in `action_reasons`.
10. **Test Coverage:** Covered in automated feature test suite.

---

## 7. Flow 7: Executive Approval Governance, Rejection, and Closure (Stage 5)

### 7.1 Step-by-Step Execution Path
* **Step 1 (UI Interaction):** Executive (BOD / Management) navigates to `/approval` ([`resources/js/Pages/Approval/Index.jsx`](file:///c:/laragon/www/foresight-radar/resources/js/Pages/Approval/Index.jsx)).
* **Step 2 (Queue Evaluation):** The approval controller filters driving forces where:
  `has('rating')` AND `whereHas('rating', fn($q) => $q->whereNotNull('status_action_id'))` AND `whereIn('status', ['PENDING', 'APPROVED'])`.
* **Step 3 (Executive Action):** User selects one of three actions:
  * **Approve:** Sets status to `APPROVED`, records `approved_at = now()`, `approved_by = auth()->id()`.
  * **Reject:** Sets status to `REJECTED`, records `remark = $remark`, clears `approved_at`.
  * **Close:** Sets status to `CLOSED`, records `closed_at = now()`, clears active monitoring.
* **Step 4 (HTTP Request):** `POST /approval/{id}/action` sent with action payload.
* **Step 5 (Controller Execution):** Handled in [`ApprovalController::action()`](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/ApprovalController.php#L62-L105).
* **Step 6 (Response):** Success banner shown. Item status updated or item removed from pending approval list.

### 7.2 Multi-Axis Assessment
1. **Beginning of Workflow:** Executive Approval screen.
2. **Data Read:** `driving_forces` with ratings and action reasons.
3. **Data Created/Updated:** `driving_forces.status`, `approved_at`, `closed_at`, `approved_by`, `remark`.
4. **Business Rules:** Only items with completed Status of Action are eligible; rejection requires a mandatory remark.
5. **Modules Affected:** Approval, Closed Items, Foresight Radar, Prioritizing, Registered List, Dashboard.
6. **Downstream Behavior:**
   * If `APPROVED`: Driving force becomes visible on Foresight Radar, Prioritizing Grid, and Registered List.
   * If `CLOSED`: Driving force transitions to Closed Items archive and disappears from active visualizers.
   * If `REJECTED`: Status changes to `REJECTED` and item leaves active pipeline until revised.
7. **Frontend State Reflection:** Item leaves pending approval queue and feedback alert displayed.
8. **Failure Handling:** Unauthorized role returns 403 Forbidden; missing remark on rejection returns 422.
9. **Data Consistency:** Timestamps and user IDs track exact executive accountability.
10. **Test Coverage:** Fully verified in feature tests.

---

## 8. Flow 8: Closed Items Terminal Archive & Audit Inspection (Stage 6)

### 8.1 Step-by-Step Execution Path
* **Step 1 (UI Interaction):** User navigates to `/closed-items` ([`resources/js/Pages/ClosedItems/Index.jsx`](file:///c:/laragon/www/foresight-radar/resources/js/Pages/ClosedItems/Index.jsx)).
* **Step 2 (HTTP Request):** `GET /closed-items` with pagination/filter query params.
* **Step 3 (Controller Execution):** [`ClosedItemsController::index()`](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/ClosedItemsController.php#L15-L35) queries `DrivingForce::where('status', 'CLOSED')->with(['rating.statusAction', 'dimension', 'environment', 'approvedBy'])`.
* **Step 4 (View Rendering):** Renders read-only archive table with closed date and rationale.

### 8.2 Multi-Axis Assessment
1. **Beginning of Workflow:** Closed items menu entry.
2. **Data Read:** Historical `driving_forces` where `status = 'CLOSED'`.
3. **Data Created/Updated:** Read-only (no mutations).
4. **Business Rules:** Displays exclusively decommissioned items.
5. **Modules Affected:** Closed items reporting.
6. **Downstream Behavior:** None (terminal state).
7. **Frontend State Reflection:** Read-only tabular presentation.
8. **Failure Handling:** Empty state card rendered if no items are closed.
9. **Data Consistency:** Consistent with approval status transitions.
10. **Test Coverage:** Verified in controller tests.

---

## 9. Flow 9: Foresight Radar Multi-Quadrant Visualization Data Pipeline (Stage 7)

### 9.1 Step-by-Step Execution Path
* **Step 1 (UI Interaction):** User opens `/visualization/foresight-radar` ([`resources/js/Pages/Visualizations/ForesightRadar.jsx`](file:///c:/laragon/www/foresight-radar/resources/js/Pages/Visualizations/ForesightRadar.jsx)).
* **Step 2 (HTTP Request):** `GET /visualization/foresight-radar`.
* **Step 3 (Controller & Query Execution):** [`ForesightRadarController::index()`](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/ForesightRadarController.php#L16-L45) calls [`DrivingForceRating::foresight_radar()`](file:///c:/laragon/www/foresight-radar/app/Models/DrivingForceRating.php#L68-L91).
  * SQL Query criteria:
    * `whereHas('drivingForce', fn($q) => $q->where('status', 'APPROVED'))`
    * `whereNotNull('status_action_id')`
    * `whereNotNull('time_horizon_id')`
    * Eager loads: `['drivingForce.dimension', 'drivingForce.environment', 'timeHorizon', 'priority', 'statusAction']`.
* **Step 4 (Coordinate Projection):** Backend or frontend transforms data into polar coordinates:
  * Quadrant angle (theta) mapped from `driving_force.environment_id` (Social: 0-90 deg, Technological: 90-180 deg, Economic: 180-270 deg, Environmental/Political: 270-360 deg).
  * Radius (r) mapped from `time_horizon_id` concentric ring index.
  * Point color mapped from `priority_id` (High = Red, Medium = Orange, Low = Green).
* **Step 5 (View Rendering):** Interactive SVG canvas renders radar rings, axis dividers, plotted scatter nodes, hover tooltips, and click-to-inspect drawers.

### 9.2 Multi-Axis Assessment
1. **Beginning of Workflow:** Visualization navigation.
2. **Data Read:** Approved `driving_force_ratings` and complete relational graph.
3. **Data Created/Updated:** Read-only data presentation.
4. **Business Rules:** Strictly items with `status = 'APPROVED'`, non-null `time_horizon_id`, and non-null `status_action_id`.
5. **Modules Affected:** Executive reporting canvas.
6. **Downstream Behavior:** None.
7. **Frontend State Reflection:** Real-time visual scatter plotting.
8. **Failure Handling:** Renders empty radar grid if no approved items match filters.
9. **Data Consistency:** Consistent with upstream approval state.
10. **Test Coverage:** Verified via live browser tests and feature tests.

---

## 10. Flow 10: Strategic Prioritizing Cartesian Grid Data Pipeline (Stage 7)

### 10.1 Step-by-Step Execution Path
* **Step 1 (UI Interaction):** User navigates to `/visualization/prioritizing` ([`resources/js/Pages/Visualizations/Prioritizing.jsx`](file:///c:/laragon/www/foresight-radar/resources/js/Pages/Visualizations/Prioritizing.jsx)).
* **Step 2 (Controller Execution):** [`PrioritizingController::index()`](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/PrioritizingController.php#L16-L35) queries [`DrivingForceRating::prioritizing()`](file:///c:/laragon/www/foresight-radar/app/Models/DrivingForceRating.php#L40-L48).
  * Enforces `status = 'APPROVED'` and non-null `impact_analysis` / `uncertainty_analysis`.
* **Step 3 (Cartesian Grid Rendering):** Maps items to a 10x10 matrix (X: Impact 1-10, Y: Uncertainty 1-10), grouping by Priority (High / Medium / Low).

### 10.2 Multi-Axis Assessment
1. **Beginning of Workflow:** Prioritizing menu link.
2. **Data Read:** Approved driving forces with numerical ratings.
3. **Data Created/Updated:** Read-only.
4. **Business Rules:** Must be approved and possess valid 1-10 numerical ratings.
5. **Modules Affected:** Visual strategy dashboard.
6. **Downstream Behavior:** None.
7. **Frontend State Reflection:** Scatter coordinates render correctly in 2D grid.
8. **Failure Handling:** Empty state display.
9. **Data Consistency:** Matches numerical values in Stage 3.
10. **Test Coverage:** Verified in automated test suite.

---

## 11. Flow 11: Registered List Matrix & Export Pipeline (Stage 7)

### 11.1 Step-by-Step Execution Path
* **Step 1 (UI Interaction):** User opens `/visualization/registered-list` ([`resources/js/Pages/Visualizations/RegisteredList.jsx`](file:///c:/laragon/www/foresight-radar/resources/js/Pages/Visualizations/RegisteredList.jsx)).
* **Step 2 (Controller Execution):** [`RegisteredListController::index()`](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/RegisteredListController.php) executes [`DrivingForceRating::registered_list()`](file:///c:/laragon/www/foresight-radar/app/Models/DrivingForceRating.php#L50-L66) with applied search and filter criteria.
* **Step 3 (Tabular Display):** Renders table containing complete metadata: Driving Force name, Dimension, Environment, Time Horizon, Impact, Uncertainty, Priority, Status of Action, and Latest Reason.
* **Step 4 (Export Action):** When user triggers Excel or PDF export, controller serializes queried records into formatted report stream.

### 11.2 Multi-Axis Assessment
1. **Beginning of Workflow:** Registered List view.
2. **Data Read:** All approved driving forces with all relationships.
3. **Data Created/Updated:** Read-only tabular and export stream.
4. **Business Rules:** Displays all approved driving forces matching filter criteria.
5. **Modules Affected:** Reporting and audit compliance.
6. **Downstream Behavior:** None.
7. **Frontend State Reflection:** Full table rendering with sortable/filterable columns.
8. **Failure Handling:** Clean empty table state with clear filter reset button.
9. **Data Consistency:** Complete data fidelity with underlying relational tables.
10. **Test Coverage:** Covered in test suite.

---

## 12. Flow 12: Executive KPI Dashboard Metric Aggregation

### 12.1 Step-by-Step Execution Path
* **Step 1 (UI Interaction):** User logs in or navigates to `/dashboard` ([`resources/js/Pages/Dashboard.jsx`](file:///c:/laragon/www/foresight-radar/resources/js/Pages/Dashboard.jsx)).
* **Step 2 (Backend Execution):** Route closure in [`routes/web.php`](file:///c:/laragon/www/foresight-radar/routes/web.php#L35-L55) computes summary aggregates:
  * Total driving forces count.
  * Driving forces count grouped by status (`PENDING`, `APPROVED`, `REJECTED`, `CLOSED`).
  * Counts per Environmental category (`Social`, `Technological`, `Economic`, `Environmental/Political`).
  * Counts per Priority (`High`, `Medium`, `Low`).
  * Recent activity feed: Latest 5 updated driving forces.
* **Step 3 (View Presentation):** Displays KPI summary cards, progress bars, donut chart, and interactive recent items table.

### 12.2 Multi-Axis Assessment
1. **Beginning of Workflow:** App home / Dashboard navigation.
2. **Data Read:** Aggregate queries on `driving_forces` and `driving_force_ratings`.
3. **Data Created/Updated:** Read-only metrics.
4. **Business Rules:** Accurately reflects real-time status across entire pipeline.
5. **Modules Affected:** Executive visibility.
6. **Downstream Behavior:** Direct navigation links to relevant workflow stages.
7. **Frontend State Reflection:** Real-time KPI counters render immediately.
8. **Failure Handling:** Zero-state handles empty database gracefully.
9. **Data Consistency:** Reflects exact database state.
10. **Test Coverage:** Tested in authenticated dashboard tests.
