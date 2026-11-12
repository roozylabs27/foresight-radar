# Feature Dependency Matrix - Foresight Radar

This document establishes the formal Feature Dependency Matrix for Foresight Radar. It models pairwise interactions between all 14 application features and subsystems, identifying data reads, writes, workflow triggers, authorization gates, and shared domain services.

---

## 1. Matrix Notation & Relationship Codes

* **R (Read):** Feature reads data produced or owned by the target feature.
* **W (Write):** Feature creates, mutates, or deletes data owned by the target feature.
* **T (Trigger):** Feature transitions state, advances lifecycles, or triggers downstream workflows.
* **A (Authorization):** Feature depends on roles, permissions, or access control defined in the target feature.
* **S (Shared Logic):** Features share domain services, calculation algorithms, or validation schemas.
* **- (None):** No verified dependency exists between the two features.
* **? (Potential):** Latent or candidate dependency requiring architectural alignment.

---

## 2. Master Feature Dependency Matrix

The table below maps each feature (row: source feature) to target features (columns: referenced feature).

| Feature ID / Name | 001 Auth | 002 User | 003 DF Reg | 004 Time | 005 Urgency | 006 Status | 007 Appr | 008 Closed | 009 Radar | 010 Prio | 011 RegList | 012 Dash | 013 Signal | 014 Taxon |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| **FEAT-001 Auth & RBAC** | - | R | - | - | - | - | - | - | - | - | - | - | - | - |
| **FEAT-002 User Mgmt** | A | - | - | - | - | - | - | - | - | - | - | - | - | - |
| **FEAT-003 DF Register** | A | R | - | - | - | - | - | - | - | - | - | - | R | R |
| **FEAT-004 Time Horizon** | A | - | R/W | - | - | - | - | - | - | - | - | - | - | R |
| **FEAT-005 Rating Urgency**| A | - | R/W | - | - | - | - | - | - | - | - | - | - | R/S |
| **FEAT-006 Status Action** | A | R | R/W | - | R | - | T | - | - | - | - | - | - | R/S |
| **FEAT-007 Approval** | A | R/W | R/W | R | R | R | - | T | T | T | T | T | - | R |
| **FEAT-008 Closed Items** | A | R | R | - | - | R | R | - | - | - | - | - | - | R |
| **FEAT-009 Foresight Radar**| A | - | R | R | R | R | R | - | - | - | - | - | - | R |
| **FEAT-010 Prioritizing** | A | - | R | - | R | R | R | - | - | - | - | - | - | R |
| **FEAT-011 Registered List**| A | - | R | R | R | R | R | - | - | - | - | - | - | R |
| **FEAT-012 Dashboard** | A | - | R | R | R | R | R | R | - | - | - | - | R | R |
| **FEAT-013 Signal Ingest** | A | R | W/T | - | - | - | - | - | - | - | - | - | - | R |
| **FEAT-014 Taxonomy Dict** | - | - | - | - | - | - | - | - | - | - | - | - | - | - |

---

## 3. Evidence and Code Verification Register

### 3.1 Row FEAT-001 (Auth & RBAC)
* `[001 -> 002]`: Reads `users` table and Spatie role mappings during login and permission checks in [`AuthenticatedSessionController`](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/Auth/AuthenticatedSessionController.php).

### 3.2 Row FEAT-002 (User Management)
* `[002 -> 001]`: Requires authorization check `can:manage-users` enforced via [`UserPolicy`](file:///c:/laragon/www/foresight-radar/app/Policies/UserPolicy.php).

### 3.3 Row FEAT-003 (Driving Force Register)
* `[003 -> 001]`: Authorization gates `can:view-driving-forces`, `can:create-driving-forces` via [`DrivingForcePolicy`](file:///c:/laragon/www/foresight-radar/app/Policies/DrivingForcePolicy.php).
* `[003 -> 002]`: Attributed to authenticated user via session.
* `[003 -> 013]`: Reads candidate signal data when created from an accepted signal in [`SignalIngestionController`](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/SignalIngestionController.php#L85-L100).
* `[003 -> 014]`: Reads foreign keys from `dimensions` and `environments` tables.

### 3.4 Row FEAT-004 (Time Horizon Assessment)
* `[004 -> 001]`: Protected by `can:view-time-horizon` and `can:assign-time-horizon`.
* `[004 -> 003]`: Reads `driving_forces` list; writes updates to `driving_force_ratings.time_horizon_id` in [`TimeHorizonController::update()`](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/TimeHorizonController.php).
* `[004 -> 014]`: Reads valid options from `time_horizons` lookup table.

### 3.5 Row FEAT-005 (Rating of Urgency)
* `[005 -> 001]`: Protected by `can:view-rating-urgency` and `can:edit-rating-urgency`.
* `[005 -> 003]`: Reads `driving_forces`; mutates `driving_force_ratings` scores in [`RatingUrgencyController::update()`](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/RatingUrgencyController.php).
* `[005 -> 014]`: Reads and writes foreign key pointer to `priorities`.
* `[005 -> S]`: Shares scoring heuristic with [`DrivingForceService::calculatePriority()`](file:///c:/laragon/www/foresight-radar/app/Services/DrivingForceService.php#L68-L82).

### 3.6 Row FEAT-006 (Status of Action)
* `[006 -> 001]`: Protected by `can:view-status-action` and `can:assign-status-action`.
* `[006 -> 002]`: Records `user_id` on newly created `action_reasons` rows.
* `[006 -> 003]`: Reads driving force metadata; updates `driving_force_ratings.status_action_id`.
* `[006 -> 005]`: Gated by prerequisite non-null check on `impact_analysis` and `uncertainty_analysis` from Stage 3.
* `[006 -> 007]`: Triggers eligibility for Stage 5 Approval queue (`status_action_id IS NOT NULL`).
* `[006 -> 014]`: Validates `status_action_id` foreign key against `status_actions`.

### 3.7 Row FEAT-007 (Executive Approval Governance)
* `[007 -> 001]`: Protected by `can:view-approval-items` and `can:create-approval-items`.
* `[007 -> 002]`: Records `approved_by` user ID upon approval.
* `[007 -> 003]`: Reads driving force record; writes status changes (`APPROVED`, `REJECTED`, `CLOSED`) and timestamps in [`ApprovalController::action()`](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/ApprovalController.php#L62-L105).
* `[007 -> 004, 005, 006]`: Reads full dimensional score profile and latest rationale.
* `[007 -> 008, 009, 010, 011, 012]`: Transitions driving forces into or out of active reporting and archiving workflows.

### 3.8 Row FEAT-008 (Closed Items Archive)
* `[008 -> 001]`: Protected by `can:view-closed-items`.
* `[008 -> 003, 006, 007]`: Reads driving forces filtered by `status = 'CLOSED'` with rating and closure metadata.

### 3.9 Rows FEAT-009, FEAT-010, FEAT-011 (Stage 7 Visualizations & Reports)
* Protected by respective `can:view-*` permissions.
* Read approved records via [`DrivingForceRating::foresight_radar()`](file:///c:/laragon/www/foresight-radar/app/Models/DrivingForceRating.php#L68-L91), [`prioritizing()`](file:///c:/laragon/www/foresight-radar/app/Models/DrivingForceRating.php#L40-L48), and [`registered_list()`](file:///c:/laragon/www/foresight-radar/app/Models/DrivingForceRating.php#L50-L66).
* All three enforce hard filters on `driving_forces.status = 'APPROVED'` and non-null rating attributes.

### 3.10 Row FEAT-012 (Dashboard)
* Reads cross-stage metrics and aggregates across `driving_forces`, `driving_force_ratings`, and `signals` in [`routes/web.php`](file:///c:/laragon/www/foresight-radar/routes/web.php#L35-L55).

### 3.11 Row FEAT-013 (Signal Ingestion)
* Ingests external inputs, stores `sources` and `signals`, and directly invokes [`DrivingForceService::create()`](file:///c:/laragon/www/foresight-radar/app/Services/DrivingForceService.php#L19-L38) to trigger new driving forces in Stage 1 upon acceptance.

---

## 4. Key Dependency Architectural Observations

1. **Unidirectional Upstream Flow:** Upstream stages (Signals, Register, Time Horizon, Urgency, Status of Action) feed sequentially into Approval Governance. No circular dependencies exist in the core domain workflow.
2. **Approval as the Central Clearinghouse:** Stage 5 (Executive Approval) is the central bottleneck and clearinghouse for all downstream reports. Nothing reaches the Radar or Prioritizing matrix without passing through Approval.
3. **Database-Enforced Referentials:** All core foreign keys (`dimension_id`, `environment_id`, `time_horizon_id`, `priority_id`, `status_action_id`) are backed by foreign key constraints in MySQL migrations with appropriate cascade or restrict rules.
