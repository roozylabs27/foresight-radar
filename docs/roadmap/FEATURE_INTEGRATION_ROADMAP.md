# Feature Integration Roadmap - Foresight Radar

This document establishes the strategic, engineering-grade Feature Integration Roadmap for Foresight Radar. It prioritizes identified architectural and workflow gaps into five actionable priority tiers: P0 (Critical Fixes), P1 (Core Workflow), P2 (Architectural Decoupling), P3 (UX & Discoverability), and P4 (Strategic Capabilities).

---

## 1. Executive Summary & Prioritization Matrix

| Identifier | Priority | Title | Affected Features | Complexity | Effort |
|---|---|---|---|---|---|
| **INT-001** | P0 | BOD Role Approval Authorization Resolution | FEAT-001, FEAT-007 | Low | 0.5 day |
| **INT-002** | P0 | Strict Prerequisite Guard on Approval Action Handler | FEAT-006, FEAT-007 | Low | 0.5 day |
| **INT-003** | P0 | Reconcile Split Priority Mutation Between Stage 3 & 7 | FEAT-005, FEAT-010 | Medium | 1 day |
| **INT-004** | P1 | Closed Items Reopening & Re-evaluation Workflow | FEAT-007, FEAT-008 | Medium | 2 days |
| **INT-005** | P1 | Bidirectional Signal-to-Driving Force Traceability | FEAT-003, FEAT-013 | Medium | 2 days |
| **INT-006** | P1 | Relational Soft-Delete Cascade Synchronization | FEAT-003, FEAT-004 to 006 | Low | 1 day |
| **INT-007** | P2 | Extract God Model Reporting Queries to Dedicated Service | FEAT-009, 010, 011 | Medium | 2 days |
| **INT-008** | P2 | Formalize Driving Force State Machine Engine | FEAT-003, 007, 008 | Medium | 3 days |
| **INT-009** | P2 | Extract Dashboard KPI Aggregations into Dedicated Controller | FEAT-012 | Low | 1 day |
| **INT-010** | P3 | Guided Multi-Step Pipeline Stepper & Progress Indicators | FEAT-003 through 006 | Medium | 2 days |
| **INT-011** | P3 | Deep-Link Provenance Drawers from Visualization Canvases | FEAT-009, 010, 011 | Medium | 2 days |
| **INT-012** | P4 | Automated Feed Ingestion & AI Background Extraction Pipeline | FEAT-013 | High | 5 days |
| **INT-013** | P4 | Cross-Quadrant Strategic Impact Correlation Engine | FEAT-009, 010, 012 | High | 5 days |

---

## 2. Detailed Roadmap Item Specifications

### INT-001: BOD Role Approval Authorization Resolution
* **Identifier:** INT-001
* **Title:** Fix Board of Directors (BOD) Approval Action Authorization Gate
* **Priority:** P0 (Critical Fix)
* **Problem Statement:** In [`database/data/role.json`](file:///c:/laragon/www/foresight-radar/database/data/role.json#L28-L43), the `bod` role is assigned `view-approval-items` but lacks `create-approval-items`. Clicking "Approve" triggers an unexpected 403 Forbidden error for executive board members.
* **Proposed Solution:** Grant `create-approval-items` (or an aliased `decide-approval-items`) permission to the `bod` role in seeders and database role synchronization scripts.
* **Features Affected:** FEAT-001 (Auth/RBAC), FEAT-007 (Executive Approval).
* **Dependencies:** None.
* **Implementation Complexity:** Low.
* **Estimated Effort:** 0.5 engineering day.
* **Success Criteria:** A user with role `bod` can load `/approval` and execute "Approve", "Reject", and "Close" actions successfully with HTTP 200/302 responses.
* **Test Strategy:** Automated test in `tests/Feature/Security/VisualizationAuthorizationTest.php` authenticating as BOD user and submitting approval action.

### INT-002: Strict Prerequisite Guard on Approval Action Handler
* **Identifier:** INT-002
* **Title:** Prevent Bypass of Status of Action in Approval Controller
* **Priority:** P0 (Critical Fix)
* **Problem Statement:** [`ApprovalController::action()`](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/ApprovalController.php#L62-L105) does not explicitly verify that the target driving force has a non-null `rating.status_action_id` before transitioning status to `APPROVED`. An external HTTP POST could approve an un-scored driving force.
* **Proposed Solution:** Add an explicit domain assertion in the controller action checking `if (is_null($df->rating?->status_action_id)) abort(422, 'Cannot approve driving force without Status of Action');`.
* **Features Affected:** FEAT-006 (Status of Action), FEAT-007 (Executive Approval).
* **Dependencies:** None.
* **Implementation Complexity:** Low.
* **Estimated Effort:** 0.5 engineering day.
* **Success Criteria:** Any approval attempt for a driving force lacking a status action is immediately aborted with HTTP 422.
* **Test Strategy:** Automated test `test_cannot_approve_driving_force_without_status_action` in `tests/Feature/Reliability/ApprovalValidationTest.php`.

### INT-003: Reconcile Split Priority Mutation Between Stage 3 & 7
* **Identifier:** INT-003
* **Title:** Align Priority Modification Boundaries Between Rating of Urgency and Prioritizing
* **Priority:** P0 (Critical Fix)
* **Problem Statement:** Stage 3 derives Priority algorithmically from Impact and Uncertainty scores, while [`PrioritizingController`](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/PrioritizingController.php#L37-L50) allows arbitrary manual overriding of `priority_id` without updating or validating the Cartesian scores, creating split data ownership.
* **Proposed Solution:** Deprecate unvalidated direct mutation of `priority_id`. If manual executive overriding is required by business stakeholders, enforce that an override logs an audit rationale note and marks the rating with an `is_overridden = true` flag.
* **Features Affected:** FEAT-005 (Rating of Urgency), FEAT-010 (Prioritizing Grid).
* **Dependencies:** None.
* **Implementation Complexity:** Medium.
* **Estimated Effort:** 1 engineering day.
* **Success Criteria:** Numerical coordinates and priority categories cannot silently contradict each other without an explicit audit trail.
* **Test Strategy:** Unit and feature tests verifying priority consistency under both standard evaluation and override workflows.

### INT-004: Closed Items Reopening & Re-evaluation Workflow
* **Identifier:** INT-004
* **Title:** Implement Reopen Action for Archived and Decommissioned Driving Forces
* **Priority:** P1 (Core Workflow)
* **Problem Statement:** Once marked `CLOSED`, items are trapped in a terminal read-only state in [`ClosedItemsController`](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/ClosedItemsController.php) with no ability to reactivate them if macro-environmental trends shift.
* **Proposed Solution:** Introduce `POST /closed-items/{id}/reopen` endpoint. Transition `status` back to `PENDING`, reset `closed_at`, clear old approval timestamps, and record the reactivation reason in `action_reasons`.
* **Features Affected:** FEAT-007 (Approval), FEAT-008 (Closed Items), FEAT-003 (Driving Force Register).
* **Dependencies:** INT-008 (State machine).
* **Implementation Complexity:** Medium.
* **Estimated Effort:** 2 engineering days.
* **Success Criteria:** Authorized managers can click "Reopen for Evaluation" on closed items, returning them to the active analysis pipeline with complete historical provenance intact.
* **Test Strategy:** Feature test asserting status change from `CLOSED` to `PENDING` and entry created in `action_reasons`.

### INT-005: Bidirectional Signal-to-Driving Force Traceability
* **Identifier:** INT-005
* **Title:** Expose Originating Signal Sources in Driving Force Register and Radar
* **Priority:** P1 (Core Workflow)
* **Problem Statement:** The `signal_driving_force` pivot links candidate signals to driving forces, but this linkage is completely invisible in the Driving Force Register, Radar modals, and Registered List.
* **Proposed Solution:** Eager-load `signals` relation on `DrivingForce`, render an "Originating Intelligence" badge and modal tab displaying source URLs, titles, and creation dates.
* **Features Affected:** FEAT-003 (Driving Force Register), FEAT-009 (Radar), FEAT-013 (Signal Ingestion).
* **Dependencies:** None.
* **Implementation Complexity:** Medium.
* **Estimated Effort:** 2 engineering days.
* **Success Criteria:** Users clicking any Driving Force card or radar dot can inspect the external sources and weak signals that triggered its initiation.
* **Test Strategy:** Feature test asserting `signals` are serialized in `DrivingForceController::index()` and `ForesightRadarController::index()`.

### INT-006: Relational Soft-Delete Cascade Synchronization
* **Identifier:** INT-006
* **Title:** Synchronize Soft Deletes Across Driving Force Ratings and Action Reasons
* **Priority:** P1 (Core Workflow)
* **Status:** Verified & Completed
* **Problem Statement:** Soft-deleting a driving force leaves active rating rows in `driving_force_ratings` and reasons in `action_reasons`, risking ghost counts in background queries.
* **Implemented Solution:** Added `softDeletes()` migration to `driving_force_ratings`, added `SoftDeletes` trait to `DrivingForceRating`, and configured model lifecycle event listeners in [`DrivingForce.php`](file:///c:/laragon/www/foresight-radar/app/Models/DrivingForce.php) cascading soft-deletes and restorations.
* **Features Affected:** FEAT-003, FEAT-004, FEAT-005, FEAT-006, FEAT-012.
* **Verification:** Verified by `test_soft_deleting_driving_force_cascades_to_rating` in `tests/Feature/DrivingForceTest.php`.

### INT-007: Extract God Model Reporting Queries to Dedicated Service
* **Identifier:** INT-007
* **Title:** Refactor Static View Queries Out of `DrivingForceRating` Model
* **Priority:** P2 (Architectural Decoupling)
* **Status:** Verified & Completed
* **Problem Statement:** [`DrivingForceRating.php#L40-L91`](file:///c:/laragon/www/foresight-radar/app/Models/DrivingForceRating.php#L40-L91) contains three static query methods specifically hardcoded to frontend views.
* **Implemented Solution:** Extracted [`ForesightReportingService.php`](file:///c:/laragon/www/foresight-radar/app/Services/ForesightReportingService.php) with unified methods (`getRadarDataset()`, `getPrioritizingDataset()`, `getRegisteredListQuery()`) sharing common query scopes. Injected service into [`ForesightRadarController.php`](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/ForesightRadarController.php), [`PrioritizingController.php`](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/PrioritizingController.php), and [`RegisteredListController.php`](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/RegisteredListController.php).
* **Features Affected:** FEAT-009 (Radar), FEAT-010 (Prioritizing), FEAT-011 (Registered List).
* **Verification:** Verified by `tests/Feature/Reliability/ForesightReportingServiceTest.php` and regression tests (4 passed).

### INT-008: Formalize Driving Force Lifecycle State Machine Engine
* **Identifier:** INT-008
* **Title:** Implement Explicit State Machine for Driving Force Lifecycle
* **Priority:** P2 (Architectural Decoupling)
* **Status:** Verified & Completed
* **Problem Statement:** Status transitions (`PENDING`, `APPROVED`, `REJECTED`, `CLOSED`) relied on ad-hoc string literals across multiple controllers without transition guard validation.
* **Implemented Solution:** Implemented PHP 8.1 backed Enum [`DrivingForceStatus.php`](file:///c:/laragon/www/foresight-radar/app/Enums/DrivingForceStatus.php) and [`InvalidStateTransitionException.php`](file:///c:/laragon/www/foresight-radar/app/Exceptions/InvalidStateTransitionException.php). Integrated `transitionTo()` into `DrivingForce`, `ApprovalController`, and `ClosedItemsController`.
* **Features Affected:** FEAT-003, FEAT-007, FEAT-008.
* **Verification:** Verified by `tests/Unit/DrivingForceStatusTest.php`, `ApprovalValidationTest.php`, and `ClosedItemsReopenTest.php` (15 passed).

### INT-009: Extract Dashboard KPI Aggregations into Dedicated Controller
* **Identifier:** INT-009
* **Title:** Migrate Dashboard Route Closure into `DashboardController`
* **Priority:** P2 (Architectural Decoupling)
* **Status:** Verified & Completed
* **Problem Statement:** Ensure routes are cleanly bound to dedicated controllers with cacheable route definitions.
* **Implemented Solution:** Verified [`DashboardController.php`](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/DashboardController.php) handling `/dashboard` and confirmed `php artisan route:cache` executes cleanly without serialization warnings.
* **Features Affected:** FEAT-012 (Dashboard).
* **Verification:** Verified with `php artisan route:cache` and `route:list`.

### INT-010: Guided Multi-Step Pipeline Stepper & Progress Indicators
* **Identifier:** INT-010
* **Title:** Add Workflow Stepper and Incomplete Stage Badges to UI
* **Priority:** P3 (UX & Discoverability)
* **Status:** Verified & Completed
* **Problem Statement:** Analysts must guess which stages have been completed for a driving force in the register table.
* **Implemented Solution:** Added `pipeline` calculation metadata to [`DrivingForceResource.php`](file:///c:/laragon/www/foresight-radar/app/Http/Resources/DrivingForceResource.php) and added a "Pipeline Stage" column in [`DrivingForce/Table.jsx`](file:///c:/laragon/www/foresight-radar/resources/js/Pages/DrivingForce/Table.jsx) showing stage badges (e.g., "Step 2/5: Time Horizon") and direct navigation shortcuts ("Lanjut ke Time Horizon").
* **Features Affected:** FEAT-003, FEAT-004, FEAT-005, FEAT-006.
* **Verification:** Verified by `test_driving_force_fetch_data_returns_pipeline_stepper_metadata` in `DrivingForceTest.php` and Chrome DevTools visual testing.

### INT-011: Deep-Link Provenance Drawers from Visualization Canvases
* **Identifier:** INT-011
* **Title:** Interactive Detail Drawer on Radar Nodes Linking to History & Signals
* **Priority:** P3 (UX & Discoverability)
* **Status:** Verified & Completed
* **Problem Statement:** Clicking a node on the Foresight Radar only showed basic tooltips without actionable context or historical justifications.
* **Implemented Solution:** Enriched [`ForesightRadarResource.php`](file:///c:/laragon/www/foresight-radar/app/Http/Resources/ForesightRadarResource.php) and [`PrioritizingResource.php`](file:///c:/laragon/www/foresight-radar/app/Http/Resources/PrioritizingResource.php) with full cross-module metadata (provenance, governance, audit justifications). Added `onEvents` node click and info button in [`Radar.jsx`](file:///c:/laragon/www/foresight-radar/resources/js/Components/Radar.jsx) opening an Ant Design `Drawer` with complete intelligence breakdown.
* **Features Affected:** FEAT-009 (Radar), FEAT-010 (Prioritizing).
* **Verification:** Verified by Chrome DevTools browser interaction and screenshot capture.

### INT-012: Automated Feed Ingestion & AI Background Extraction Pipeline
* **Identifier:** INT-012
* **Title:** Scheduled Background Crawling and Automated Signal Extraction
* **Priority:** P4 (Strategic Capabilities)
* **Problem Statement:** Signal ingestion currently requires manual URL pasting and human-triggered extraction.
* **Proposed Solution:** Implement scheduled Laravel Artisan commands / queues (`php artisan signals:crawl-rss`) monitoring industry foresight feeds and extracting candidate signals asynchronously into the review queue.
* **Features Affected:** FEAT-013 (Signal Ingestion).
* **Dependencies:** None.
* **Implementation Complexity:** High.
* **Estimated Effort:** 5 engineering days.
* **Success Criteria:** Queued worker crawls configured RSS feeds daily and populates candidate signals with status `PENDING`.
* **Test Strategy:** Mock HTTP feed crawler unit and integration tests.

### INT-013: Cross-Quadrant Strategic Impact Correlation Engine
* **Identifier:** INT-013
* **Title:** Cross-Driving Force Clustering and Cross-Impact Correlation Analysis
* **Priority:** P4 (Strategic Capabilities)
* **Problem Statement:** Driving forces are evaluated in isolation without cross-impact matrix modeling (e.g., how Technological breakthrough X accelerates Economic trend Y).
* **Proposed Solution:** Build a cross-impact correlation matrix module linking pairs of approved driving forces with positive/negative mutual amplification scores.
* **Features Affected:** FEAT-009 (Radar), FEAT-010 (Prioritizing), FEAT-012 (Dashboard).
* **Dependencies:** INT-007, INT-008.
* **Implementation Complexity:** High.
* **Estimated Effort:** 5 engineering days.
* **Success Criteria:** Visual correlation chords or connection lines render across quadrants on the Foresight Radar canvas.
* **Test Strategy:** Cross-impact calculation algorithm tests and frontend SVG connector testing.
