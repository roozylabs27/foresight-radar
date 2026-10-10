# Foresight Radar: Capability Matrix

**Inspection date:** 2026-10-09

---

## Status Definitions

| Status | Meaning |
|---|---|
| **Implemented and verified** | Feature exists in code, has clear controller/model/view support, and can be traced through the request path. |
| **Partially implemented** | Some code exists but is incomplete, has known defects, or lacks full coverage. |
| **Not found in the inspected code** | No model, controller, route, migration, or UI component supports this capability. |
| **Requires runtime verification** | Code exists but behavior depends on configuration, seed data, or runtime state that cannot be confirmed by static analysis alone. |

---

## Capability Matrix

### 1. Driving Force Management

| Capability | Status | Evidence |
|---|---|---|
| Create driving force | Implemented and verified | `DrivingForceController::create`, `DrivingForceRequest` validation, `DrivingForce/Form.jsx` UI. Route: `POST /driving-force` with `can:create-driving-force`. |
| View driving forces (paginated, filtered) | Implemented and verified | `DrivingForceController::fetch_data` delegates to `DrivingForceService::filter()` with date, dimension, status, search params. Paginated via Eloquent. |
| Update driving force | Implemented and verified | `DrivingForceController::update` with `DrivingForcePolicy::update()` ownership check (creator, PIC, or admin roles). |
| Delete driving force (soft) | Implemented and verified | `DrivingForceController::delete` with `DrivingForcePolicy::delete()`. Uses `SoftDeletes` trait. |
| Restore deleted driving force | Not found in the inspected code | No restore route, controller method, or UI element. |
| Driving force history/audit log | Partially implemented | `ActionReason` logs status action changes with date and reason. No audit trail for other field changes (keyword, description, rating scores). |
| Bulk import/create | Not found in the inspected code | No import functionality. |

### 2. Taxonomy and Categorization

| Capability | Status | Evidence |
|---|---|---|
| Environment hierarchy | Implemented and verified | `environments` -> `dimensions` parent-child relationship via `environment_id` FK. |
| Dimension categorization | Implemented and verified | Every driving force belongs to a `Dimension`. Filter by dimension available on all data views. |
| Custom taxonomy creation | Requires runtime verification | `dimensions` and `environments` tables exist with seed data. No CRUD UI for managing them was found. Adding/editing dimensions requires direct DB access or seed modification. |
| Hardcoded radar dimensions | Partially implemented | Frontend `Radar.jsx` maps 8 specific dimension names to polar angles. Adding new dimensions requires code changes in the angle mapping. |

### 3. Rating and Assessment

| Capability | Status | Evidence |
|---|---|---|
| Impact rating (1-10) | Implemented and verified | `RatingUrgencyController::create` stores `impact_analysis` on `DrivingForceRating`. UI: inline tag selector in `RatingUrgency/Table.jsx`. |
| Uncertainty rating (1-10) | Implemented and verified | Same controller, stored as `uncertainty_analysis`. |
| Automatic priority calculation | Implemented and verified | Threshold-based: sum >= 12 (High), >= 6 (Medium), else Low. Priority looked up from `priorities` table. |
| Multiple assessors | Not found in the inspected code | `DrivingForceRating` has a one-to-one relationship with `DrivingForce`. Only one rating record per driving force. |
| Assessment revision history | Not found in the inspected code | Rating updates overwrite previous values. No versioning table or changelog for scores. |
| Assessment methodology documentation | Not found in the inspected code | The 1-10 scale and threshold formula exist only in controller code. No documentation, methodology description, or calibration guidance. |

### 4. Impact and Uncertainty Evaluation

| Capability | Status | Evidence |
|---|---|---|
| Two-axis evaluation (Impact x Uncertainty) | Implemented and verified | Stored in `driving_force_ratings`. Visualized in Prioritizing Matrix chart. |
| Priority zones | Implemented and verified | Three zones (High/Medium/Low) with threshold-based assignment and color coding. |
| Weighted scoring | Not found in the inspected code | Simple sum threshold, no weighting by dimension, confidence, or other factors. |
| Confidence level | Not found in the inspected code | No confidence field on driving forces or ratings. |

### 5. Time Horizons

| Capability | Status | Evidence |
|---|---|---|
| Time horizon assignment | Implemented and verified | `TimeHorizonController::create` links `time_horizon_id` to `DrivingForceRating`. |
| Predefined horizons (Short/Medium/Long) | Requires runtime verification | `time_horizons` table seeded from `database/data/time_horizon.json`. Actual values depend on seed data. |
| Time horizon in radar visualization | Partially implemented | Radar chart uses time horizon data to position dots radially. Exact mapping logic is in `Radar.jsx` data transformation. |
| Custom horizon definitions | Not found in the inspected code | No CRUD UI for time horizons. Managed via seed data only. |

### 6. Radar Visualization

| Capability | Status | Evidence |
|---|---|---|
| Polar scatter chart | Implemented and verified | `Radar.jsx` renders ECharts polar scatter with 8 fixed dimension angles. |
| Anti-collision algorithm | Implemented and verified | `computeAntiCollisionPoints` function at `Radar.jsx` L82 uses Euclidean distance to shift overlapping data points. |
| Interactive hover/highlight | Implemented and verified | Hovering table rows triggers ECharts `dispatchAction('highlight')` on corresponding dots. |
| Dimension-based angular mapping | Implemented and verified | 8 dimensions mapped to fixed angles (0, 45, 90, 135, 180, 225, 270, 315 degrees). |
| Dynamic dimension support | Not found in the inspected code | Angular mapping is hardcoded. New dimensions would render at angle 0 (default). |
| Radar filtering | Implemented and verified | Date range and dimension filters available on the radar page. |

### 7. Evidence and Source Tracking

| Capability | Status | Evidence |
|---|---|---|
| Source URL attachment | Not found in the inspected code | No `source_url`, `reference`, or `evidence` field on any model. |
| Document upload | Not found in the inspected code | No file upload controller, storage configuration, or upload UI. |
| Publication date tracking | Not found in the inspected code | Only `created_at` (record creation) exists. No field for the source publication date. |
| Citation management | Not found in the inspected code | No citation or reference model. |
| Evidence-to-insight linkage | Not found in the inspected code | No relational structure connecting sources to assessments. |

### 8. Trend and Signal Management

| Capability | Status | Evidence |
|---|---|---|
| Signal entity | Not found in the inspected code | No `Signal` model, migration, or controller. The "driving force" serves as both the input signal and the assessed force. |
| Trend entity | Not found in the inspected code | No `Trend` model or relationship. |
| Signal-to-trend linking | Not found in the inspected code | No many-to-many relationship between signals and trends. |
| Signal-to-driving-force linking | Not found in the inspected code | Signals and driving forces are not distinguished. |
| Weak signal detection | Not found in the inspected code | No automated or semi-automated signal identification. |

### 9. Approval Workflows

| Capability | Status | Evidence |
|---|---|---|
| Submit for approval | Implemented and verified | Driving forces with completed ratings appear in Approval queue automatically (filtered by `whereHas('rating', ...)` and `whereNotNull('status_action_id')`). |
| Approve/reject actions | Implemented and verified | `ApprovalController::create` handles approve (CLOSED) and reject (PENDING with remark). |
| Approval with remarks | Implemented and verified | Remark field stored on `driving_forces.remark` on rejection. |
| Approval timestamps | Implemented and verified | `approved_at` and `closed_at` set on approval. |
| Multi-level approval | Not found in the inspected code | Single-level approval only. No approval chains, quorum requirements, or escalation. |
| Approval notification | Not found in the inspected code | No email or in-app notification when items are submitted or decided. |
| Approval audit trail | Partially implemented | `approved_at` and `remark` are stored, but no dedicated approval history table. Overwritten on re-submission after rejection. |

### 10. Scenario Planning

| Capability | Status | Evidence |
|---|---|---|
| Scenario creation | Not found in the inspected code | No `Scenario` model, migration, or UI. |
| Scenario-driving force linking | Not found in the inspected code | No relationship between scenarios and driving forces. |
| Scenario comparison | Not found in the inspected code | No comparison tooling. |
| "What-if" analysis | Not found in the inspected code | No simulation or what-if capabilities. |

### 11. Alerts and Monitoring

| Capability | Status | Evidence |
|---|---|---|
| Email notifications | Not found in the inspected code | Mail configuration exists in `.env.example` but no notification classes or mail triggers in application code. |
| In-app notifications | Not found in the inspected code | No notification bell, toast, or notification model. |
| Status change alerts | Not found in the inspected code | No event listeners or observers for driving force status changes. |
| Scheduled monitoring | Not found in the inspected code | `app/Console/Kernel.php` exists but no scheduled commands are registered. |
| Dashboard alerts/widgets | Not found in the inspected code | Dashboard shows charts only, no alert widgets. |

### 12. Strategic Implications and Action Tracking

| Capability | Status | Evidence |
|---|---|---|
| Strategic implication recording | Not found in the inspected code | No model for risks, opportunities, or consequences. |
| Action item management | Partially implemented | `StatusAction` assigns Act/Monitor/Park with a reason, but no follow-up task management, deadlines, or responsibility assignment beyond the status label. |
| Action tracking/progress | Not found in the inspected code | No task completion tracking or status updates for follow-up actions. |
| Decision linkage | Not found in the inspected code | No way to connect a driving force to a business decision it influenced. |

### 13. AI-Assisted Analysis

| Capability | Status | Evidence |
|---|---|---|
| Content extraction (NLP) | Not found in the inspected code | No text extraction, NLP, or content parsing. |
| Signal identification | Not found in the inspected code | No automated signal detection from documents or URLs. |
| Summary generation | Not found in the inspected code | No LLM integration, API keys, or prompt templates. |
| Category suggestion | Not found in the inspected code | No ML-based categorization. |
| Confidence scoring | Not found in the inspected code | No AI confidence metrics. |
| Human-AI content distinction | Not found in the inspected code | No field or UI element distinguishing AI-generated from human-written content. |

---

## Summary

| Category | Implemented | Partial | Missing |
|---|---|---|---|
| Driving force management | 4 | 2 | 1 |
| Taxonomy/categorization | 2 | 1 | 1 |
| Rating/assessment | 3 | 0 | 3 |
| Impact/uncertainty | 2 | 0 | 2 |
| Time horizons | 1 | 1 | 1 |
| Radar visualization | 4 | 0 | 1 |
| Evidence/source tracking | 0 | 0 | 5 |
| Trend/signal management | 0 | 0 | 5 |
| Approval workflows | 4 | 1 | 2 |
| Scenario planning | 0 | 0 | 4 |
| Alerts/monitoring | 0 | 0 | 5 |
| Strategic implications | 0 | 1 | 3 |
| AI-assisted analysis | 0 | 0 | 6 |
| **Totals** | **20** | **6** | **39** |

The application has a solid working core for the driving force lifecycle (creation through approval to visualization). The main gaps are in evidence tracking, signal/trend management, scenario planning, notifications, and AI-assisted analysis. These represent the foresight domain capabilities required for Stages 3-5 of the evolution plan.
