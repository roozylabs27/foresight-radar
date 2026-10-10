# Architectural Coupling & Domain Boundary Audit - Foresight Radar

This document assesses structural coupling, domain boundary integrity, and architectural layering across Foresight Radar. It identifies God model anti-patterns, leaky abstractions, query duplication, and controller bloat, providing concrete refactoring paths with effort estimates.

---

## 1. Architectural Coupling Findings

### COUP-01: God Model and Presentation Leakage in `DrivingForceRating`
* **Pattern Observed:** The [`DrivingForceRating`](file:///c:/laragon/www/foresight-radar/app/Models/DrivingForceRating.php) model contains static methods tailored specifically to the presentation needs of three individual frontend pages: `prioritizing()`, `registered_list()`, and `foresight_radar()`.
* **Code Location:** [`app/Models/DrivingForceRating.php#L40-L91`](file:///c:/laragon/www/foresight-radar/app/Models/DrivingForceRating.php#L40-L91).
* **Why This Coupling is Problematic:** An Eloquent data model should represent persistence and domain invariants, not specific frontend view models. Embedding complex presentation query graphs inside model static methods couples the persistence layer directly to frontend page layout requirements and violates the Single Responsibility Principle.
* **Refactoring Recommendation:** Extract a dedicated `VisualizationQueryService` or encapsulate reusable query scopes (`scopeApprovedForReporting()`) on the model, allowing controllers to compose queries cleanly.
* **Effort Estimate:** Small (1-2 engineering days).

### COUP-02: Controller-Layer Embedded Business Logic in Status of Action and Approval
* **Pattern Observed:** Multi-step business rules (e.g., verifying that impact and uncertainty are non-null before allowing status action assignment, or logging reasons into `action_reasons`) are implemented directly inside controllers rather than domain services.
* **Code Location:** [`app/Http/Controllers/StatusActionController.php#L54-L88`](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/StatusActionController.php#L54-L88), [`app/Http/Controllers/ApprovalController.php#L62-L105`](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/ApprovalController.php#L62-L105).
* **Why This Coupling is Problematic:** If another entry point (such as a batch CLI command, an API endpoint, or an automated workflow) needs to assign status of action or approve items, the business rules cannot be reused without duplicating code or instantiating HTTP controllers.
* **Refactoring Recommendation:** Expand [`DrivingForceService`](file:///c:/laragon/www/foresight-radar/app/Services/DrivingForceService.php) to include `assignStatusAction(DrivingForce $df, int $statusActionId, string $reason)` and `transitionApproval(DrivingForce $df, string $action, ?string $remark)`.
* **Effort Estimate:** Medium (2-3 engineering days).

### COUP-03: Route Closure Logic for Executive Dashboard
* **Pattern Observed:** The aggregate KPI queries that power the main Executive Dashboard (`/dashboard`) are defined inside a route closure in `routes/web.php` rather than a dedicated controller or service.
* **Code Location:** [`routes/web.php#L35-L55`](file:///c:/laragon/www/foresight-radar/routes/web.php#L35-L55).
* **Why This Coupling is Problematic:** Defining query pipelines inside routing definitions bypasses Laravel route caching optimizations, violates architectural separation of concerns, and hinders automated unit testing of metric calculations.
* **Refactoring Recommendation:** Extract a dedicated `DashboardController` and inject a `DashboardMetricsService` to compile the KPI payloads.
* **Effort Estimate:** Small (half day).

### COUP-04: Asymmetric Signal-to-Driving Force Traceability
* **Pattern Observed:** Signal Ingestion knows intimately how to create a Driving Force (calling `DrivingForceService::create()`), but the Driving Force domain has zero knowledge or UI exposure of the `signal_driving_force` relationship.
* **Code Location:** [`app/Http/Controllers/SignalIngestionController.php#L85-L100`](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/SignalIngestionController.php#L85-L100) vs [`app/Http/Controllers/DrivingForceController.php`](file:///c:/laragon/www/foresight-radar/app/Http/Controllers/DrivingForceController.php).
* **Why This Coupling is Problematic:** The data linkage is one-way in practice. An analyst working on a driving force cannot see the source signals that justified its inception, breaking contextual intelligence flow.
* **Refactoring Recommendation:** Eager-load the `signals` relation on `DrivingForce` and expose a "Provenance / Signals" tab in the Driving Force modal.
* **Effort Estimate:** Small (1 day).

### COUP-05: Missing Explicit State Machine Abstraction for Driving Force Lifecycle
* **Pattern Observed:** Status transitions (`PENDING` -> `APPROVED`, `REJECTED`, `CLOSED`) are managed via ad-hoc string comparisons across multiple controllers without an explicit state machine or state transition validator.
* **Code Location:** Spread across `DrivingForceController`, `ApprovalController`, and `ClosedItemsController`.
* **Why This Coupling is Problematic:** Without a formalized state machine, invalid state transitions (such as transitioning directly from `PENDING` to `CLOSED` without going through Approval, or re-approving an already closed item) can easily be introduced when new routes or features are added.
* **Refactoring Recommendation:** Introduce an explicit State pattern or transition service (e.g., using PHP Enums for status values with allowed transition validation methods).
* **Effort Estimate:** Medium (2-3 engineering days).

---

## 2. Coupling Summary Matrix

| Issue ID | Architectural Anti-Pattern | Primary Component Affected | Coupling Severity | Refactoring Priority |
|---|---|---|---|---|
| COUP-01 | Model-to-View Leakage (God Model) | `DrivingForceRating` | High | P1 |
| COUP-02 | Controller Bloat / Missing Domain Service | `StatusActionController`, `ApprovalController` | Medium | P1 |
| COUP-03 | Route Closure Aggregation | `routes/web.php` Dashboard | Low | P2 |
| COUP-04 | Asymmetric Entity Provenance | `DrivingForce` <-> `Signal` | Medium | P2 |
| COUP-05 | Implicit String-Based State Transitions | `DrivingForce.status` Lifecycle | High | P1 |
