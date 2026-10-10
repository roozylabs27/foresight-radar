# Feature Inventory - Foresight Radar

This document establishes the exhaustive feature inventory for the Foresight Radar application (`https://github.com/roozylabs27/foresight-radar`), cataloging every user-facing capability, backend subsystem, data entity, and interface boundary across the system.

---

## 1. Inventory Summary

| Metric | Count | Details |
|---|---|---|
| Total Features Cataloged | 14 | 12 active business/workflow modules, 1 upstream ingestion module, 1 foundation taxonomy module |
| Verified Active Features | 13 | End-to-end verified with routes, controllers, models, and UI views |
| Partially Verified / Unmanaged | 1 | Foundation taxonomy (FEAT-014) seeded via database migrations without dedicated administrative UI |
| Core Workflow Stages | 8 | Stage 0 (Ingestion) through Stage 7 (Visualizations and Reports) |
| Total Models Inspected | 11 | [`DrivingForce`](file:///c:/laragon/www/foresight-radar/app/Models/DrivingForce.php), [`DrivingForceRating`](file:///c:/laragon/www/foresight-radar/app/Models/DrivingForceRating.php), [`ActionReason`](file:///c:/laragon/www/foresight-radar/app/Models/ActionReason.php), [`Dimension`](file:///c:/laragon/www/foresight-radar/app/Models/Dimension.php), [`Environment`](file:///c:/laragon/www/foresight-radar/app/Models/Environment.php), [`Priority`](file:///c:/laragon/www/foresight-radar/app/Models/Priority.php), [`Signal`](file:///c:/laragon/www/foresight-radar/app/Models/Signal.php), [`Source`](file:///c:/laragon/www/foresight-radar/app/Models/Source.php), [`StatusAction`](file:///c:/laragon/www/foresight-radar/app/Models/StatusAction.php), [`TimeHorizon`](file:///c:/laragon/www/foresight-radar/app/Models/TimeHorizon.php), [`User`](file:///c:/laragon/www/foresight-radar/app/Models/User.php) |

---

## 2. Comprehensive Feature Register

### FEAT-001: Authentication, Session Management, and Role-Based Access Control (RBAC)
* **Feature Name:** Authentication & RBAC Gatekeeper
* **Purpose:** Provides user registration, password authentication, session lifecycle, authorization policies, and Spatie permission resolution across all routes and API actions.
* **Frontend Entry Point:** [`resources/js/Pages/Auth/Login.jsx`](file:///c:/laragon/www/foresight-radar/resources/js/Pages/Auth/Login.jsx), [`resources/js/Pages/Auth/Register.jsx`](file:///c:/laragon/www/foresight-radar/resources/js/Pages/Auth/Register.jsx)
* **Backend Entry Point:** [`routes/auth.php`](file:///c:/laragon/www/foresight-radar/routes/auth.php), [`app/Http/Controllers/Auth/AuthenticatedSessionController.php`](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/Auth/AuthenticatedSessionController.php), [`app/Http/Middleware/HandleInertiaRequests.php`](file:///c:/laragon/www/foresight-radar/app/Http/Middleware/HandleInertiaRequests.php)
* **Data Entities:** `users`, `roles`, `permissions`, `model_has_roles`, `role_has_permissions`
* **Inputs:** Credentials (email, password), session tokens, permission queries.
* **Outputs:** Authenticated user session, Inertia shared props (`auth.user`, `auth.permissions`, `auth.roles`), session cookies.
* **Dependencies:** Database connection, session store, Spatie Permission package.
* **Consumers:** All application features (FEAT-002 through FEAT-013).
* **Status:** Verified.

### FEAT-002: User Management and Role Provisioning
* **Feature Name:** User Administration & Role Assignment
* **Purpose:** Allows administrators to inspect user accounts, modify role assignments (Admin, BOD, Management, Team), update credentials, and manage active system access.
* **Frontend Entry Point:** [`resources/js/Pages/UserManagement/Index.jsx`](file:///c:/laragon/www/foresight-radar/resources/js/Pages/UserManagement/Index.jsx)
* **Backend Entry Point:** [`app/Http/Controllers/UserController.php`](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/UserController.php), [`app/Policies/UserPolicy.php`](file:///c:/laragon/www/foresight-radar/app/Policies/UserPolicy.php)
* **Data Entities:** `users`, `roles`, `model_has_roles`
* **Inputs:** User profile data (name, email, password), role identifier.
* **Outputs:** Created or updated user records, synchronized Spatie roles.
* **Dependencies:** FEAT-001 (RBAC authorization gate `manage-users`).
* **Consumers:** FEAT-001 (system login), FEAT-007 (approval attribution via `approved_by`).
* **Status:** Verified.

### FEAT-003: Driving Force Register (Stage 1 Creation & Cataloging)
* **Feature Name:** Driving Force Register & Lifecycle Initiator
* **Purpose:** Allows strategic analysts to create, view, search, edit, and soft-delete driving forces. Initializes the 1-to-1 rating profile with default status `PENDING`.
* **Frontend Entry Point:** [`resources/js/Pages/DrivingForces/Index.jsx`](file:///c:/laragon/www/foresight-radar/resources/js/Pages/DrivingForces/Index.jsx), [`resources/js/Components/DrivingForce/DrivingForceModal.jsx`](file:///c:/laragon/www/foresight-radar/resources/js/Components/DrivingForce/DrivingForceModal.jsx)
* **Backend Entry Point:** [`app/Http/Controllers/DrivingForceController.php`](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/DrivingForceController.php), [`app/Services/DrivingForceService.php`](file:///c:/laragon/www/foresight-radar/app/Services/DrivingForceService.php), [`app/Policies/DrivingForcePolicy.php`](file:///c:/laragon/www/foresight-radar/app/Policies/DrivingForcePolicy.php)
* **Data Entities:** `driving_forces`, `dimensions`, `environments`, `driving_force_ratings`
* **Inputs:** `name`, `explanation`, `dimension_id`, `environment_id`, pagination filters.
* **Outputs:** Persisted `DrivingForce` record, auto-initialized `DrivingForceRating` record with status `PENDING`.
* **Dependencies:** FEAT-001 (RBAC gates `view-driving-forces`, `create-driving-forces`, `edit-driving-forces`, `delete-driving-forces`), FEAT-014 (Taxonomy dictionaries).
* **Consumers:** FEAT-004, FEAT-005, FEAT-006, FEAT-007, FEAT-008, FEAT-009, FEAT-010, FEAT-011, FEAT-012, FEAT-013.
* **Status:** Verified.

### FEAT-004: Time Horizon Assessment (Stage 2)
* **Feature Name:** Time Horizon Assessment
* **Purpose:** Evaluates the temporal impact window for driving forces by assigning a time horizon category (Short Term: less than 1 yr, Medium Term: 1 to 5 yrs, Long Term: 5 to 10 yrs, Ultra Long: greater than 10 yrs).
* **Frontend Entry Point:** [`resources/js/Pages/TimeHorizon/Index.jsx`](file:///c:/laragon/www/foresight-radar/resources/js/Pages/TimeHorizon/Index.jsx)
* **Backend Entry Point:** [`app/Http/Controllers/TimeHorizonController.php`](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/TimeHorizonController.php)
* **Data Entities:** `driving_forces`, `driving_force_ratings`, `time_horizons`
* **Inputs:** `driving_force_id`, `time_horizon_id`.
* **Outputs:** Updated `driving_force_ratings.time_horizon_id`.
* **Dependencies:** FEAT-001 (RBAC gates `view-time-horizon`, `assign-time-horizon`), FEAT-003 (`driving_forces` record), FEAT-014 (`time_horizons` taxonomy).
* **Consumers:** FEAT-009 (Foresight Radar concentric ring grouping), FEAT-011 (Registered List tabular export), FEAT-012 (Dashboard metrics).
* **Status:** Verified.

### FEAT-005: Urgency & Impact/Uncertainty Evaluation (Stage 3)
* **Feature Name:** Rating of Urgency
* **Purpose:** Evaluates qualitative threat/opportunity severity by capturing numerical scores for Impact Analysis (1 to 10) and Uncertainty Analysis (1 to 10), automatically computing the strategic priority classification (High, Medium, Low).
* **Frontend Entry Point:** [`resources/js/Pages/RatingUrgency/Index.jsx`](file:///c:/laragon/www/foresight-radar/resources/js/Pages/RatingUrgency/Index.jsx)
* **Backend Entry Point:** [`app/Http/Controllers/RatingUrgencyController.php`](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/RatingUrgencyController.php), [`app/Services/DrivingForceService.php`](file:///c:/laragon/www/foresight-radar/app/Services/DrivingForceService.php)
* **Data Entities:** `driving_forces`, `driving_force_ratings`, `priorities`
* **Inputs:** `impact_analysis` (integer 1-10), `uncertainty_analysis` (integer 1-10).
* **Outputs:** Updated `driving_force_ratings` scores and derived `priority_id`.
* **Dependencies:** FEAT-001 (RBAC gates `view-rating-urgency`, `edit-rating-urgency`), FEAT-003 (`driving_forces`), FEAT-014 (`priorities`).
* **Consumers:** FEAT-006 (Stage 4 gating condition: non-null impact and uncertainty), FEAT-009 (Radar dot coordinates), FEAT-010 (Prioritizing matrix positioning).
* **Status:** Verified.

### FEAT-006: Status of Action Assignment & Rationale Audit Trail (Stage 4)
* **Feature Name:** Status of Action & Strategic Intent Assignment
* **Purpose:** Designates the operational posture (Act, Prepare, Watch, Dismiss) for an evaluated driving force, logs mandatory corporate rationales into `action_reasons`, and recalibrates priority if required.
* **Frontend Entry Point:** [`resources/js/Pages/StatusAction/Index.jsx`](file:///c:/laragon/www/foresight-radar/resources/js/Pages/StatusAction/Index.jsx)
* **Backend Entry Point:** [`app/Http/Controllers/StatusActionController.php`](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/StatusActionController.php), [`app/Services/DrivingForceService.php`](file:///c:/laragon/www/foresight-radar/app/Services/DrivingForceService.php)
* **Data Entities:** `driving_forces`, `driving_force_ratings`, `status_actions`, `action_reasons`, `priorities`
* **Inputs:** `status_action_id`, `reason` (rationale text string).
* **Outputs:** Updated `driving_force_ratings.status_action_id`, inserted `action_reasons` record.
* **Dependencies:** FEAT-001 (RBAC gates `view-status-action`, `assign-status-action`), FEAT-003 (`driving_forces`), FEAT-005 (Requires non-null rating analysis), FEAT-014 (`status_actions`).
* **Consumers:** FEAT-007 (Stage 5 Approval queue gating condition: `status_action_id IS NOT NULL`), FEAT-009, FEAT-010, FEAT-011.
* **Status:** Verified.

### FEAT-007: Executive Approval Governance & Rejection Workflow (Stage 5)
* **Feature Name:** Executive Approval Governance
* **Purpose:** Provides a governance gate for executive leadership (BOD / Management) to approve, reject with feedback, or close proposed driving forces. Transitions driving force status to `APPROVED`, `REJECTED`, or `CLOSED`.
* **Frontend Entry Point:** [`resources/js/Pages/Approval/Index.jsx`](file:///c:/laragon/www/foresight-radar/resources/js/Pages/Approval/Index.jsx)
* **Backend Entry Point:** [`app/Http/Controllers/ApprovalController.php`](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/ApprovalController.php)
* **Data Entities:** `driving_forces`, `driving_force_ratings`, `status_actions`, `action_reasons`, `users`
* **Inputs:** `status` (`APPROVED`, `REJECTED`, `CLOSED`), `remark` (mandatory when rejecting).
* **Outputs:** Transitioned `driving_forces.status`, timestamps (`approved_at`, `closed_at`), `approved_by` user ID, `remark` text.
* **Dependencies:** FEAT-001 (RBAC gates `view-approval-items`, `create-approval-items`), FEAT-006 (Gated by non-null `status_action_id`).
* **Consumers:** FEAT-008 (receives closed items), FEAT-009, FEAT-010, FEAT-011 (visualizations strictly display `APPROVED` items), FEAT-012 (Dashboard KPIs).
* **Status:** Verified.

### FEAT-008: Closed Items Terminal Archive (Stage 6)
* **Feature Name:** Closed Items Archive
* **Purpose:** Displays driving forces that have been decommissioned or rejected from active foresight monitoring (`status = 'CLOSED'`). Provides read-only auditing of historical closure timestamps and reasons.
* **Frontend Entry Point:** [`resources/js/Pages/ClosedItems/Index.jsx`](file:///c:/laragon/www/foresight-radar/resources/js/Pages/ClosedItems/Index.jsx)
* **Backend Entry Point:** [`app/Http/Controllers/ClosedItemsController.php`](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/ClosedItemsController.php)
* **Data Entities:** `driving_forces`, `driving_force_ratings`, `status_actions`, `users`
* **Inputs:** Search and filtering parameters.
* **Outputs:** Tabular view of archived driving forces and closure metadata.
* **Dependencies:** FEAT-001 (RBAC gate `view-closed-items`), FEAT-007 (driving forces transitioned to `CLOSED`).
* **Consumers:** Executive auditing, compliance reporting.
* **Status:** Verified.

### FEAT-009: Foresight Radar Visualization (Stage 7 Visualization)
* **Feature Name:** Foresight Radar Interactive Canvas
* **Purpose:** Renders an interactive polar radar chart segmenting approved driving forces across four environmental quadrants (Social, Technological, Economic, Environmental/Political) and four concentric Time Horizon rings.
* **Frontend Entry Point:** [`resources/js/Pages/Visualizations/ForesightRadar.jsx`](file:///c:/laragon/www/foresight-radar/resources/js/Pages/Visualizations/ForesightRadar.jsx), [`resources/js/Components/Radar/RadarChart.jsx`](file:///c:/laragon/www/foresight-radar/resources/js/Components/Radar/RadarChart.jsx)
* **Backend Entry Point:** [`app/Http/Controllers/ForesightRadarController.php`](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/ForesightRadarController.php), [`app/Models/DrivingForceRating.php::foresight_radar()`](file:///c:/laragon/www/foresight-radar/app/Models/DrivingForceRating.php#L68-L91)
* **Data Entities:** `driving_forces`, `driving_force_ratings`, `time_horizons`, `environments`, `priorities`, `status_actions`
* **Inputs:** Environmental quadrant filters, status action filters.
* **Outputs:** JSON payload of polar plot coordinates, radar scatter rendering, tooltips, detail modals.
* **Dependencies:** FEAT-001 (RBAC gate `view-foresight-radar`), FEAT-003, FEAT-004, FEAT-005, FEAT-006, FEAT-007 (Strictly filters `status = 'APPROVED'`).
* **Consumers:** Executive strategic planning, board presentations.
* **Status:** Verified.

### FEAT-010: Prioritizing Grid Visualization (Stage 7 Visualization)
* **Feature Name:** Strategic Prioritizing Matrix
* **Purpose:** Displays approved driving forces mapped across a 2D Cartesian scatter matrix comparing Impact Analysis (X-axis, 1-10) against Uncertainty Analysis (Y-axis, 1-10) with priority color clustering.
* **Frontend Entry Point:** [`resources/js/Pages/Visualizations/Prioritizing.jsx`](file:///c:/laragon/www/foresight-radar/resources/js/Pages/Visualizations/Prioritizing.jsx)
* **Backend Entry Point:** [`app/Http/Controllers/PrioritizingController.php`](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/PrioritizingController.php), [`app/Models/DrivingForceRating.php::prioritizing()`](file:///c:/laragon/www/foresight-radar/app/Models/DrivingForceRating.php#L40-L48)
* **Data Entities:** `driving_forces`, `driving_force_ratings`, `priorities`, `status_actions`
* **Inputs:** Priority filters, dimension filters.
* **Outputs:** Cartesian coordinate plot, priority breakdown table, interactive inspect modals.
* **Dependencies:** FEAT-001 (RBAC gate `view-prioritizing`), FEAT-005, FEAT-006, FEAT-007 (Filters `status = 'APPROVED'`).
* **Consumers:** Strategy committees, resource allocation teams.
* **Status:** Verified.

### FEAT-011: Registered List Matrix & PDF/Excel Export (Stage 7 Report)
* **Feature Name:** Registered List & Export Center
* **Purpose:** Provides a comprehensive tabular ledger of all approved driving forces with complete multidimensional metadata (Environment, Dimension, Time Horizon, Impact, Uncertainty, Priority, Status Action, Reasons), and client-side or server-side document exports.
* **Frontend Entry Point:** [`resources/js/Pages/Visualizations/RegisteredList.jsx`](file:///c:/laragon/www/foresight-radar/resources/js/Pages/Visualizations/RegisteredList.jsx)
* **Backend Entry Point:** [`app/Http/Controllers/RegisteredListController.php`](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/RegisteredListController.php), [`app/Models/DrivingForceRating.php::registered_list()`](file:///c:/laragon/www/foresight-radar/app/Models/DrivingForceRating.php#L50-L66)
* **Data Entities:** `driving_forces`, `driving_force_ratings`, `time_horizons`, `dimensions`, `environments`, `priorities`, `status_actions`, `action_reasons`
* **Inputs:** Multi-parameter search, pagination, filter by environment/priority/status.
* **Outputs:** Paginated table view, PDF/Excel export records.
* **Dependencies:** FEAT-001 (RBAC gate `view-registered-list`), FEAT-003 through FEAT-007 (Filters `status = 'APPROVED'`).
* **Consumers:** Operational planning teams, auditors, external stakeholders.
* **Status:** Verified.

### FEAT-012: Executive KPI Dashboard
* **Feature Name:** Executive Overview Dashboard
* **Purpose:** Consolidates real-time organizational KPIs: total driving forces, distribution across workflow stages, environmental category counts, priority breakdown charts, and recent activity logs.
* **Frontend Entry Point:** [`resources/js/Pages/Dashboard.jsx`](file:///c:/laragon/www/foresight-radar/resources/js/Pages/Dashboard.jsx)
* **Backend Entry Point:** [`routes/web.php`](file:///c:/laragon/www/foresight-radar/routes/web.php#L35-L55) (Inline route handler aggregating metrics)
* **Data Entities:** `driving_forces`, `driving_force_ratings`, `signals`, `users`
* **Inputs:** Authenticated session context.
* **Outputs:** KPI metric cards, stage distribution bar charts, environment donut chart, recent driving force list.
* **Dependencies:** FEAT-001, FEAT-003 through FEAT-007.
* **Consumers:** All authenticated users on system login.
* **Status:** Verified.

### FEAT-013: Signal Ingestion, Web Scraping & Semantic Extraction (Stage 0 Ingestion)
* **Feature Name:** Weak Signal Ingestion & AI Candidate Extraction
* **Purpose:** Ingests external intelligence from URLs or raw text into `sources`, parses potential weak signals into `signals` (status `PENDING`), and provides an analyst review workspace to accept signals into new or existing Driving Forces via `signal_driving_force` pivot associations.
* **Frontend Entry Point:** [`resources/js/Pages/Signals/Index.jsx`](file:///c:/laragon/www/foresight-radar/resources/js/Pages/Signals/Index.jsx)
* **Backend Entry Point:** [`app/Http/Controllers/SignalIngestionController.php`](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/SignalIngestionController.php), [`app/Services/SignalExtractionService.php`](file:///c:/laragon/www/foresight-radar/app/Services/SignalExtractionService.php)
* **Data Entities:** `sources`, `signals`, `signal_driving_force`, `driving_forces`
* **Inputs:** URL or text payload, source title, manual signal edits, review actions (`accept_new`, `accept_link`, `reject`).
* **Outputs:** Persisted `sources` records, extracted `signals` records, new `driving_forces` entries or pivot links.
* **Dependencies:** FEAT-001 (RBAC gates `view-signals`, `ingest-signals`, `review-signals`), FEAT-003 (Driving Force creation interface).
* **Consumers:** FEAT-003 (Feeds new driving forces into the foresight pipeline).
* **Status:** Verified.

### FEAT-014: Reference Taxonomy Administration & System Dictionaries
* **Feature Name:** Reference Taxonomy Dictionaries
* **Purpose:** Stores the normalized categorical taxonomy underpinning all foresight scoring: Dimensions, Environments, Priorities, Status Actions, and Time Horizons.
* **Frontend Entry Point:** Implicit: rendered as dropdown options across FEAT-003, FEAT-004, FEAT-005, FEAT-006, FEAT-009, FEAT-010, FEAT-011. No standalone admin CRUD interface exists.
* **Backend Entry Point:** Models: [`Dimension`](file:///c:/laragon/www/foresight-radar/app/Models/Dimension.php), [`Environment`](file:///c:/laragon/www/foresight-radar/app/Models/Environment.php), [`Priority`](file:///c:/laragon/www/foresight-radar/app/Models/Priority.php), [`StatusAction`](file:///c:/laragon/www/foresight-radar/app/Models/StatusAction.php), [`TimeHorizon`](file:///c:/laragon/www/foresight-radar/app/Models/TimeHorizon.php); Seeders: [`database/seeders/DefaultSystemSeeder.php`](file:///c:/laragon/www/foresight-radar/database/seeders/DefaultSystemSeeder.php)
* **Data Entities:** `dimensions`, `environments`, `priorities`, `status_actions`, `time_horizons`
* **Inputs:** Database seeds and raw database migrations.
* **Outputs:** Pre-populated lookup records consumed by scoring forms.
* **Dependencies:** Database migrations.
* **Consumers:** FEAT-003, FEAT-004, FEAT-005, FEAT-006, FEAT-009, FEAT-010, FEAT-011.
* **Status:** Partially Verified (Functional in database and forms, but lacks administrative maintenance UI).

---

## 3. Inventory Cross-Reference Table

| Feature ID | Feature Name | Primary Controller | Primary React View | Primary Model(s) | Status |
|---|---|---|---|---|---|
| FEAT-001 | Auth & RBAC | [`AuthenticatedSessionController`](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/Auth/AuthenticatedSessionController.php) | [`Login.jsx`](file:///c:/laragon/www/foresight-radar/resources/js/Pages/Auth/Login.jsx) | `User`, Spatie Roles | Verified |
| FEAT-002 | User Management | [`UserController`](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/UserController.php) | [`UserManagement/Index.jsx`](file:///c:/laragon/www/foresight-radar/resources/js/Pages/UserManagement/Index.jsx) | `User` | Verified |
| FEAT-003 | Driving Force Register | [`DrivingForceController`](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/DrivingForceController.php) | [`DrivingForces/Index.jsx`](file:///c:/laragon/www/foresight-radar/resources/js/Pages/DrivingForces/Index.jsx) | `DrivingForce`, `DrivingForceRating` | Verified |
| FEAT-004 | Time Horizon Assessment | [`TimeHorizonController`](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/TimeHorizonController.php) | [`TimeHorizon/Index.jsx`](file:///c:/laragon/www/foresight-radar/resources/js/Pages/TimeHorizon/Index.jsx) | `DrivingForceRating`, `TimeHorizon` | Verified |
| FEAT-005 | Rating of Urgency | [`RatingUrgencyController`](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/RatingUrgencyController.php) | [`RatingUrgency/Index.jsx`](file:///c:/laragon/www/foresight-radar/resources/js/Pages/RatingUrgency/Index.jsx) | `DrivingForceRating`, `Priority` | Verified |
| FEAT-006 | Status of Action | [`StatusActionController`](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/StatusActionController.php) | [`StatusAction/Index.jsx`](file:///c:/laragon/www/foresight-radar/resources/js/Pages/StatusAction/Index.jsx) | `DrivingForceRating`, `ActionReason` | Verified |
| FEAT-007 | Executive Approval | [`ApprovalController`](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/ApprovalController.php) | [`Approval/Index.jsx`](file:///c:/laragon/www/foresight-radar/resources/js/Pages/Approval/Index.jsx) | `DrivingForce`, `DrivingForceRating` | Verified |
| FEAT-008 | Closed Items Archive | [`ClosedItemsController`](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/ClosedItemsController.php) | [`ClosedItems/Index.jsx`](file:///c:/laragon/www/foresight-radar/resources/js/Pages/ClosedItems/Index.jsx) | `DrivingForce` | Verified |
| FEAT-009 | Foresight Radar | [`ForesightRadarController`](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/ForesightRadarController.php) | [`Visualizations/ForesightRadar.jsx`](file:///c:/laragon/www/foresight-radar/resources/js/Pages/Visualizations/ForesightRadar.jsx) | `DrivingForceRating` | Verified |
| FEAT-010 | Prioritizing Matrix | [`PrioritizingController`](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/PrioritizingController.php) | [`Visualizations/Prioritizing.jsx`](file:///c:/laragon/www/foresight-radar/resources/js/Pages/Visualizations/Prioritizing.jsx) | `DrivingForceRating` | Verified |
| FEAT-011 | Registered List & Export | [`RegisteredListController`](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/RegisteredListController.php) | [`Visualizations/RegisteredList.jsx`](file:///c:/laragon/www/foresight-radar/resources/js/Pages/Visualizations/RegisteredList.jsx) | `DrivingForceRating` | Verified |
| FEAT-012 | Executive Dashboard | [`routes/web.php`](file:///c:/laragon/www/foresight-radar/routes/web.php) | [`Dashboard.jsx`](file:///c:/laragon/www/foresight-radar/resources/js/Pages/Dashboard.jsx) | `DrivingForce`, `Signal` | Verified |
| FEAT-013 | Signal Ingestion | [`SignalIngestionController`](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/SignalIngestionController.php) | [`Signals/Index.jsx`](file:///c:/laragon/www/foresight-radar/resources/js/Pages/Signals/Index.jsx) | `Source`, `Signal`, Pivot | Verified |
| FEAT-014 | Taxonomy Dictionaries | Seeders / Schema | Implicit in Form Dropdowns | `Dimension`, `Environment`, etc. | Partially Verified |
