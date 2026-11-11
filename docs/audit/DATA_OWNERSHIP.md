# Data Ownership & Source of Truth Audit - Foresight Radar

This document defines the authoritative data ownership map for Foresight Radar. It establishes the single source of truth for all business entities, audits write boundaries, documents derived value computations, and highlights split or ambiguous ownership risks.

---

## 1. Master Data Ownership Map

| Entity / Field | Authoritative Owner | Write Permissions / Features | Read Consumers | Storage / Table | Invalidation / Lifecycle Rules |
|---|---|---|---|---|---|
| **User Account & Identity** (`name`, `email`, `password`) | FEAT-002 User Management & Self Auth | Admin (`manage-users`), User Self-Profile | All features via session | `users` | Passwords hashed (bcrypt); soft or hard delete revokes authentication immediately. |
| **Role & Permission Mappings** | FEAT-001 / FEAT-002 RBAC | Admin (`manage-users`) | All route gates and authorization policies | `model_has_roles`, `role_has_permissions` | Cached in Spatie permission cache; flushed on role assignment update. |
| **Source Metadata** (`title`, `url`, `raw_content`, `status`) | FEAT-013 Signal Ingestion | Analysts (`ingest-signals`) | Signal Ingestion review workspace | `sources` | Immutable after ingestion; status transitions (`PENDING` -> `PROCESSED` / `FAILED`). |
| **Signal Candidates** (`title`, `summary`, `status`) | FEAT-013 Signal Ingestion | Analysts (`ingest-signals`, `review-signals`) | Signal Ingestion workspace | `signals` | Status transitions from `PENDING` to `ACCEPTED` or `REJECTED`. |
| **Signal to Driving Force Link** | FEAT-013 Signal Ingestion | Analysts (`review-signals`) | Signals workspace | `signal_driving_force` | Foreign key cascaded on signal or driving force deletion. |
| **Driving Force Identity** (`name`, `explanation`, `dimension_id`, `environment_id`) | FEAT-003 Driving Force Register | Analysts (`create-driving-forces`, `edit-driving-forces`) | All stages and visualizations | `driving_forces` | SoftDeletes enabled; changes propagate immediately to all downstream views. |
| **Governance Status & Timestamps** (`status`, `approved_at`, `closed_at`, `approved_by`, `remark`) | FEAT-007 Executive Approval | Executives / Admin (`create-approval-items`) | All stages, Closed Items, Visualizations, Dashboard | `driving_forces` | State machine transitions: `PENDING` -> `APPROVED` / `REJECTED` / `CLOSED`. |
| **Time Horizon Foreign Key** (`time_horizon_id`) | FEAT-004 Time Horizon Assessment | Analysts (`assign-time-horizon`) | Radar (ring radius), Registered List, Dashboard | `driving_force_ratings` | 1-to-1 with driving force; updated in-place. |
| **Impact & Uncertainty Scores** (`impact_analysis`, `uncertainty_analysis`) | FEAT-005 Rating of Urgency | Analysts (`edit-rating-urgency`) | Status of Action, Prioritizing grid, Radar, Dashboard | `driving_force_ratings` | Numerical bounds 1-10; overwriting recalculates priority. |
| **Priority Classification** (`priority_id`) | FEAT-005 Rating of Urgency *(Split Ownership Risk)* | FEAT-005 (`RatingUrgencyController`), FEAT-010 (`PrioritizingController`) | All visualizations and reports | `driving_force_ratings` | Derived deterministically by formula in FEAT-005, but manually writable in FEAT-010. |
| **Status of Action Pointer** (`status_action_id`) | FEAT-006 Status of Action | Analysts (`assign-status-action`) | Approval, Radar, Registered List | `driving_force_ratings` | Prerequisite: impact and uncertainty must be scored. |
| **Historical Justification Log** (`reason`, `user_id`, `created_at`) | FEAT-006 Status of Action | Analysts (`assign-status-action`) | Status of Action history drawer, Approval queue | `action_reasons` | Append-only; immutable historical log entries. |
| **Reference Taxonomies** (`dimensions`, `environments`, `priorities`, `status_actions`, `time_horizons`) | FEAT-014 Reference Taxonomy | Database Seeders / Migrations | All features for select dropdowns and legends | Respective dictionary tables | Static lookup data; modified exclusively via database administration. |

---

## 2. Derived Values & Computation Analysis

### 2.1 Priority (`priority_id`)
* **Computation Logic:** Implemented in [`DrivingForceService::calculatePriority($impact, $uncertainty)`](file:///c:/laragon/www/foresight-radar/app/Services/DrivingForceService.php#L68-L82):
  * **High (ID 1):** `impact_analysis >= 7` AND `uncertainty_analysis >= 7`.
  * **Low (ID 3):** `impact_analysis <= 3` AND `uncertainty_analysis <= 3`.
  * **Medium (ID 2):** Any score pairing not satisfying High or Low.
* **Storage Mode:** Persisted in `driving_force_ratings.priority_id`.
* **Staleness / Drift Risk:**
  * When updated via Stage 3 (`RatingUrgencyController`), the value is recalculate synchronously and saved atomically.
  * *Split Ownership Vulnerability:* If updated via Stage 7 Prioritizing (`PrioritizingController`), `priority_id` is mutated directly without modifying or re-verifying `impact_analysis` and `uncertainty_analysis`. This introduces a data integrity hazard where the persisted priority disagrees with the coordinate math.

### 2.2 Approval Eligibility Flag (Computed at Runtime)
* **Eligibility Rule:** A driving force is eligible for Executive Approval if and only if `status_action_id IS NOT NULL` and `status IN ('PENDING', 'APPROVED')`.
* **Storage Mode:** Computed on-the-fly via Eloquent `whereNotNull('status_action_id')` query filters.
* **Staleness Risk:** None. The query reads live database state.

### 2.3 Polar Coordinates for Foresight Radar
* **Computation Logic:**
  * **Angle (theta):** Derived from `environment_id` (Social = Quadrant 1, Technological = Quadrant 2, Economic = Quadrant 3, Environmental/Political = Quadrant 4).
  * **Radius (r):** Derived from `time_horizon_id` ordinal position (Short Term = inner ring, Ultra Long = outer perimeter).
  * **Jitter / Offset:** Random deterministic dispersion calculated by item ID hash to prevent dot stacking.
* **Storage Mode:** Calculated dynamically at request/render time; coordinates are not persisted in the database.
* **Staleness Risk:** None. Pure idempotent transformation of primary entities.

---

## 3. Ambiguous and Split Ownership Vulnerabilities

### VULN-OWN-01: Split Ownership of Priority Classification
* **Description:** Both Stage 3 (`RatingUrgencyController`) and Stage 7 (`PrioritizingController`) write to `driving_force_ratings.priority_id`. Stage 3 treats it as an algorithmic derivation; Stage 7 treats it as an arbitrary user input.
* **Remediation:** Remove the arbitrary update route in `PrioritizingController`, or enforce that changing priority in the Prioritizing view also updates the underlying centroid coordinates.

### VULN-OWN-02: Asymmetric Deletion Between Parent Driving Force and Child Ratings
* **Description:** Soft-deleting a `DrivingForce` sets `deleted_at` on the `driving_forces` table. However, `driving_force_ratings` and `action_reasons` remain active rows without a `deleted_at` column.
* **Remediation:** Add `SoftDeletes` trait to `DrivingForceRating` and register an Eloquent deleting model event on `DrivingForce` to cascade soft deletion to child rating records.
