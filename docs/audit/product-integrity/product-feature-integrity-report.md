# Product Feature Integrity Report — Foresight Radar

Audit of 2026-11-13 on `main` `46e22d4` (= `development` `82a26a6`). Audit only: no application code was changed. Evidence was collected by source inspection, by checking existing test names, and from browser runs in earlier sessions (production E2E of 2026-11-12; restored-copy page sweep of 2026-11-13). Each finding gives its verification type. The key claims (PI-01, PI-02, PI-03, PI-04, PI-06, PI-07, PI-09, PI-16, the coordinator role) were re-checked directly in the code during this audit.

## 1. Summary by status

| Status | Capabilities ([inventory](product-capability-inventory.md)) |
|---|---|
| **Implemented** | A-02, A-03, A-04, A-05, A-06, A-07, A-08, A-11, A-12, A-13, B-01, B-06, B-12, C-01, C-03, C-07, C-09, C-10, D-01, D-02, D-03, AI-01..AI-10, P-01, P-03, P-04, P-06, P-07, P-08 |
| **Partial** | A-01, A-09, A-10, A-14, B-02, B-03, B-04, B-05, B-07, B-08, B-09, B-10, B-11, B-13, B-14, B-15, C-06, C-08, D-04, D-05, AI-11, AI-12 |
| **Disconnected** | C-02 what-if simulation (not reachable from the exercise; never saved) |
| **Redundant** | B-16 legacy `/scenarios` (writable), C-04/C-05 Dashboard tabs duplicating the report pages, P-05 platform user management vs Members |
| **Missing** | D-06 human notifications, D-07 monitoring cadence, AI-13 stuck AI-job recovery, P-02 organisation creation, response execution tracking (part of B-10) |
| **Unverified** | live AI output on production (provider unavailable on 2026-11-12); production cross-organisation isolation; runtime of the AI-13 stuck state |

What is genuinely strong:
- the evidence controls (verbatim quote check, extraction recovery);
- the exercise workspace and its server-side gates;
- threshold evaluation on the server;
- AI proposals that never apply without a person;
- tenant isolation;
- the operational recovery tooling.

The weaknesses come in three kinds. Some gates can be passed without the deliberation they stand for (PI-02, PI-03, PI-09). Some links break between the firm library and the exercise, and between narrative and implication (PI-05, PI-06). And the monitoring loop has no way to reach a person (PI-04).

## 2. Findings

Each finding: ID, priority, capability, workflow, evidence (file:line, tests), verification, actual vs expected behaviour, impact.

### PI-01 — P1 — Exercise lifecycle rules can be bypassed (B-15)
- **Workflow:** B, Complete Scenario Planning → closing.
- **Evidence:**
  - `ScenarioPlanningProjectRequest.php:34` accepts `status` in STATUSES.
  - `ScenarioPlanningProjectService::update` :167-169 assigns it.
  - The PRD-06 rules in `changeStatus` (monitoring only when step 9 is done; draft only by withdrawing G1) are therefore skipped by the edit form's PUT endpoint.
  - No test covers the bypass. Verification: S. The rules were added on 2026-11-12, but this older field was left in place.
- **Actual:** anyone allowed to edit can put an exercise in `monitoring` or `draft` directly.
- **Expected:** status changes go only through `changeStatus`.
- **Impact:** the closing state stops meaning anything; monitoring can start on an unfinished exercise. Small fix.

### PI-02 — P1 — G3 can pass on library-copied scores (B-04)
- **Evidence:**
  - `ProjectDrivingForceService::copyOrganizationRating` (:124-140) creates **consensus** rows with `origin=library`.
  - `ProjectAssessmentService::confirm` counts any consensus row.
  - The journey test reaches G3 without any exercise scoring (`ForesightExerciseJourneyTest:64-69, 136`).
  - The UI badge "Disalin… tinjau" (ScoreList.jsx:30) is advisory only.
  - Verification: S, T.
- **Actual:** the firm's ratings become the exercise's "consensus" without any discussion. A wide spread among assessors is shown but neither blocks G3 nor is recorded.
- **Expected (methodology):** consensus is agreed per focal issue.
- **Impact:** the exercise can inherit the firm view unexamined, which undermines its credibility.

### PI-03 — P1 — Approvals survive upstream change (B-08, B-10, B-12)
- **Evidence:**
  - `ScenarioPlanningProject::withdrawFrameworkApproval` (:268-288) resets only the framework and uncertainties. G6 (approved quadrants) and G7 (approved responses) stay approved.
  - `StrategicResponseTest:426` expects exactly this.
  - Review and approval ignore `stale_since` (ScenarioNarrativeService:235-252; StrategicResponseService:75-99).
  - Step status never shows "stale"; `nextAction` ignores flags.
  - Verification: S, T.
- **Actual:** after the framework is reopened and re-approved, steps 6 and 8 show "done" again, with only stale banners.
- **Expected:** stale work cannot be approved and does not count as done.
- **Impact:** decisions can rest on narratives and responses written for an earlier framework.

### PI-04 — P1 — Monitoring does not reach a person (D-04, D-06, D-07)
- **Evidence:**
  - `app/Notifications` holds only `TenantInvitationNotification`, and there is no `app/Mail`.
  - `NotificationBell` reads ingestion events from localStorage (IngestionContext.jsx).
  - `frequency` is never evaluated (grep).
  - World Bank values are stored as **proposed** (ExternalIndicatorService:151), and thresholds skip proposals (IndicatorThresholdService:41).
  - Verification: S, T.
- **Actual:**
  - Threshold crossings, overdue observations, pending proposals and stale work become visible only when someone opens the exercise.
  - A fetched value that would cross a threshold waits silently for a confirmation nobody is asked to give.
- **Expected:** a monitoring loop with an owner who is told what needs attention.
- **Impact:** "continuous monitoring" is in practice a register.

### PI-05 — P1 — Evidence chain breaks at narrative → implication (B-08, B-09)
- **Evidence:**
  - Any edit drops `narrative_statements` and un-types the citations (ScenarioNarrativeService:215-218); the citations stay without re-verification.
  - Implications link only to the quadrant and optionally to forces or conditions (ScenarioImplicationService:205-218). There is no statement or citation link.
  - No artefact-level navigation exists in `Components/ScenarioPlanning` (links are step-level only).
  - Verification: S.
- **Impact:**
  - After the first human edit, "which evidence supports this statement" is lost.
  - "Which statement does this challenge answer" was never stored.
  - Executives cannot trace a response back to signals without reconstructing it by hand (see [traceability](product-workflow-traceability.md) §Evidence chain).

### PI-06 — P1 — The firm library and the exercise drift apart silently (A-08, A-09, B-03)
- **Evidence:**
  - Ratings are copied once (ProjectDrivingForceService:124-140).
  - Firm edits, status changes to REJECTED/CLOSED, and soft deletes raise no staleness. Only merge does (DrivingForceMergeService:21).
  - The exercise library offers forces in **any** status (ProjectDrivingForceService:164-172, no status filter).
  - Exercise-scope reports mix exercise impact/uncertainty with the firm's horizon and status action (ForesightReportingService:196-240).
  - Verification: S.
- **Impact:**
  - A rejected or closed firm force can enter an exercise.
  - A firm correction never reaches running exercises.
  - Scoped reports are a hybrid that is not explained to users.

### PI-07 — P1 (needs a product decision) — FIRM reports drop old ratings
- **Evidence:** `ForesightReportingService::baseApprovedRatingQuery` filters `driving_force_ratings.created_at` to the selected period. The default covers about the last 12 months (:30-60). Verification: S.
- **Actual:** an APPROVED force whose rating was created more than 12 months ago disappears from the Dashboard, Radar, Prioritizing and Registered List until the period is widened.
- **Decision needed:** should the period mean "rated in this period" or "active in this period"?
- **Impact:** the firm view silently shrinks over time.
- **Decision (product owner, 2026-11-13): "rated in the period".** The period means "assessed during the period", not "active during the period". Implementation note: a re-rating updates the same `driving_force_ratings` row (`RatingUrgencyController::create` :63-71, `save()`), so `created_at` keeps the first rating date. A force re-rated this month but first rated more than 12 months ago still drops out. The filter must use the latest rating activity (`updated_at`, or the newest `driving_force_rating_histories` entry), and the page must state the rule.

### PI-08 — P1 — A second scenario path bypasses the method (B-16)
- **Evidence:**
  - `/scenarios` (Report/Scenarios.jsx, 540 lines; ScenarioController::store :84) is hidden from the sidebar (AuthenticatedLayout.jsx:57) but still creates project-less scenarios from two firm forces and generates AI narratives.
  - `tests/e2e/scenarios.e2e.ts` still exercises this flow.
  - Verification: S, T.
- **Impact:** scenarios outside G1–G5 can exist next to exercise scenarios in the same model.

### PI-09 — P1 — Step 9 completion and relevance are weak (B-11, C-08)
- **Evidence:**
  - Step 9 is done with **one** accepted active indicator that has four directions (EarlyWarningIndicatorService:295-303). It need not discriminate between quadrants, and needs no owner or frequency.
  - `relevance()` filters indicator `status=active` but not indicator `review_status` or `discriminates` (:211-225).
  - There is no G8 (thresholds confirmed) although the PRD foresees one.
  - Verification: S, T.
- **Impact:** monitoring and the lifecycle "monitoring" state can start on indicators that cannot tell the futures apart; the relevance readings then mislead.

### PI-10 — P1 — Exercise AI jobs can stay "queued" forever (AI-13)
- **Evidence:**
  - Stale/lost recovery (`StaleJob`) exists only for extraction and external fetch (grep: SignalExtractionService, ExternalIndicatorService, ExtractSignals, FetchIndicatorSource).
  - The 9 `*_suggestion_status` columns have none.
  - `SuggestionControl.jsx` keeps the button disabled while queued or processing.
  - Verification: S. The runtime case is unverified.
- **Impact:** if a worker dies, the assist cannot be retried from the UI. The same defect was fixed for extraction in U2.

### PI-11 — P2 — Strategic responses stop at approval (B-10, C-09)
- **Evidence:**
  - `StrategicResponse` has owner, priority, initiatives text and `status ∈ {proposed, approved, rejected}`. There is no due date, KPI, execution status or review date (model :36).
  - Execution exists only through backcasting milestones (year, owner, status), reached from a text link.
  - Verification: S, T.
- **Impact:** foresight does not connect to action.
- **Smallest closure:** execution status, due date, a KPI linked to an existing indicator, and a last-reviewed date on the response.

### PI-12 — P2 — Governance is thinner than the roles suggest (B-13, B-14)
- **Evidence:**
  - Participant roles coordinator / contributor / expert behave identically. Rights come only from `ScenarioPlanningProjectPolicy::update` (creator, owner or admin; :15-20).
  - Gate decisions store no rationale and no artefact id (GateDecisionObserver).
  - Exercise status changes are not logged.
  - Comments are hard-deleted and cover only four artefact types.
  - Archived exercises stay editable.
  - BOD cannot approve exercise gates.
  - Verification: S, T.
- **Impact:** deliberation and accountability are not recorded in a form an executive can audit.

### PI-13 — P2 — AI provenance is incomplete (AI-02..AI-12)
- **Evidence:**
  - `generation_run_id` is stored for signals, force candidates, score suggestions and narratives only.
  - Conditions, implications, responses, indicators, matches, focal and framework suggestions carry `origin=ai` but no run.
  - The score link is lost on acceptance (ScoreSuggestionService:135-149).
  - `ai_generation_runs` is never shown in the UI.
  - Verification: S.

### PI-14 — P2 — Duplicate inputs are not caught (A-01, AI-03, AI-09, AI-10)
- **Evidence:**
  - `sources.content_hash` is not used.
  - Accepting a force candidate always creates a new library force (ForceCandidateService:114).
  - Proposed responses and indicators are not checked against existing ones.
  - Signal deduplication happens within one document only.
  - Verification: S.
- **Impact:** the library and the exercises accumulate near-duplicates.

### PI-15 — P2 — Navigation and information architecture
- **Evidence:**
  - Dashboard tabs duplicate three report pages (Dashboard.jsx:124-138), with different permissions.
  - The firm lifecycle is spread over 5 governance pages plus the force table.
  - Exercises never link to their own scoped reports (`?scope=` appears nowhere in ScenarioPlanning pages).
  - Compare, Backcasting and Report are text links at the bottom of the overview.
  - Sidebar labels are English in the Indonesian locale (`nav` objects identical in en.json and id.json).
  - Cross-Impact and Knowledge Graph are FIRM-only without saying so.
  - Pages over 150 lines: KnowledgeGraph 959, Signals/Index 932, CrossImpact 631.
  - Verification: S.
- **Impact:** a first-time practitioner meets two products — a firm register and an exercise — without a map between them.

### PI-16 — P2 — Organisation provisioning and tenant roles
- **Evidence:**
  - `Tenant::firstOrCreate` exists only in `UserSeeder.php:41`; there is no controller or command.
  - Invitations allow only `admin` and `bod` (TenantMemberController:125).
  - Verification: S, T.
- **Impact:** onboarding a second organisation needs a developer. Anyone who scores or ingests must be a full admin.

### PI-17 — P2 — Method order and propagation gaps (B-03, B-07)
- **Evidence:**
  - Steps 5–9 open together at G5, and G6 does not require step 5 (`steps()` in ScenarioPlanningProjectService).
  - A condition change does not flag the implications linked to it (ScenarioConditionService:58-74).
  - Toggling `is_predetermined` on an axis force propagates nothing.
  - Verification: S, T.

### PI-18 — P2 — Critical uncertainty selection guardrails
- **Evidence:**
  - Candidates are every included non-predetermined force; there is no impact ≥ 6 / uncertainty ≥ 6 warning (CriticalUncertaintyService:142-153).
  - Independence reads the firm cross-impact matrix, and an empty matrix counts as 0, i.e. "independent" (:185-194).
  - Verification: S, T.

### PI-19 — P2 — Firm governance history
- **Evidence:**
  - No `approved_by` or `closed_by`, and no status transition log (ApprovalController:79-89). A reject or reopen overwrites `remark`.
  - Signal review is irreversible.
  - `action_reasons` has no `tenant_id` (it is isolated only indirectly).
  - Verification: S.

### PI-20 — P3 — Smaller items
- G5 accepts default quadrant names.
- Robustness labels a response with 0 fitting quadrants "conditional".
- The report has no stale state, relevance or decision rationale, and is print-only.
- AI matches are dated with the match date, not the signal date (SignalMatchService:107).
- The framework's `focal_question` copy is never re-synced.
- Preferred future and milestones are not flagged on a framework change.
- 7 unused permissions; `welcome.blade.php` and `api/user` are unused.
- `STEPS[].module` is dead config.
- The docs name `llm:compliance-report`, but the command is `llm:compliance`.
- The README still lists TypeScript, Redis and a typecheck step.
- Login pages poll an authenticated endpoint (two 401s per guest page).
- Accepting a signal as a new force falls back to `Dimension::first()`.
- DrivingForce success messages say "signal".

## 3. AI capability integrity

All 11 AI capabilities that produce content have a defined consumer and a human acceptance step. All except extraction write proposals, never accepted records. Each has `tries=1` and a `failed()` handler. None duplicates deterministic logic, and none creates records without tenant ownership. Dashboard, the "reasoning engine", simulation and the consistency checks are rule-based, not AI.

| ID | Problem solved | Output check | Accept / edit / reject | Provenance | Value vs a rule | Concern |
|---|---|---|---|---|---|---|
| AI-01 extraction | read long documents | quote verified, deduplicated within the document, capped | review queue | run id per signal | High (grounded) | no deduplication across sources |
| AI-02 focal critique | sharpen the question | `problem()` validator | "Gunakan" pre-fills; reject | run recorded, id not stored | Medium | — |
| AI-03 candidate forces | cluster signals into forces | 1–4 words, known category, refs offered | accept / reject | run id + signal ids | High | creates duplicates of existing library forces |
| AI-04 score suggestions | first-pass scores | each force once, 1–10, citations limited to that force's signals | accept into consensus / reject | lost on accept | Medium (anchoring risk) | adds to PI-02: AI or library scores can pass G3 without deliberation |
| AI-05 poles and names | make the axes vivid | validator | accept / reject | none | Medium | — |
| AI-06 narratives | draft four futures | all 4 quadrants; citations must exist; typed statements | edit / review / revisions | run id + revisions | High | trace lost on edit (PI-05) |
| AI-07 conditions | draft conditions | kind, axis, force refs | accept / edit | origin only | Medium | — |
| AI-08 implications | draft challenges and opportunities | condition refs in the same quadrant | accept / edit | origin only | Medium-High | — |
| AI-09 responses | propose responses with fit | priority, fit ×4, implication refs | approve / reject / edit | origin only | Medium | no duplicate check |
| AI-10 indicators | propose discriminating indicators | must discriminate | accept / edit | origin only | Medium-High | no duplicate check, no evidence citation |
| AI-11 matching | link new signals to indicators | refs checked | confirm → threshold | signal id kept | High | manual only; wrong `observed_at` |

**Recommendation:** keep all of them. Simplification is not needed. The gaps are run provenance (PI-13), duplicate guards (PI-14) and stuck-job recovery (PI-10). Live AI quality on production remains **unverified**.

## 4. FIRM vs Key Focal Issue coherence

The two scopes are **methodologically correct and should stay separate**. The firm library is the organisation's memory; an exercise is a time-boxed answer to one question. Today, though, they behave like two workflows that share tables rather than two views of one product.

- **Firm library (tenant-wide):** sources, signals, signal↔force links, driving forces, their single rating and history, the firm lifecycle (horizon → rating → status action → approval → closed), cross-impacts, dimensions.
- **Exercise:** the selected forces (`project_driving_forces`: inclusion, predetermined), its own scores (`force_assessments`), and everything from critical uncertainties to indicators, decisions and backcasting.
- **Shared, by reference:** signals (cited in narratives, matched to indicators), the cross-impact matrix (used for G4 independence and simulation), dimensions, and driving force identity (`driving_force_id`).
- **Copied:** the firm impact/uncertainty, once, into the exercise consensus (`origin=library`). Exercise scores never flow back.
- **When a firm force changes:** nothing in the exercise reacts, except merges and withdrawn signals (PI-06).
- **Navigation:**
  - Exercise → library: only "create new force" and the signals link.
  - Library → exercise: none; a force does not show which exercises use it.
  - Exercise → its scoped reports: none.
  - Reports → exercise: a scope selector on 4 pages only.

The domain and workflow diagram is in [product-workflow-traceability.md](product-workflow-traceability.md) §1.

**Recommended position:** keep the scopes. Make the bridge explicit:
1. Show the firm status and value next to every copied score, and flag the exercise when the firm force changes.
2. Offer only APPROVED firm forces, or require a reason for any other.
3. Link each exercise to its scoped reports, and each force to the exercises that use it.
4. Label the firm-only analyses (Cross-Impact, Knowledge Graph) as such.

## 5. Orphan, redundant and legacy candidates

| Item | Evidence | Intentional? | Recommendation |
|---|---|---|---|
| `/scenarios` legacy page and `ScenarioController` (10 routes) | hidden but writable; e2e spec still targets it | hiding was intentional (U3); creation staying live was not | **Deprecate:** read-only archive, then remove; move the e2e spec to the exercise |
| Dashboard tabs Prioritizing / Radar / Registered | same components as the report pages | accidental duplication | **Consolidate:** keep Overview and Simulation; link to the reports |
| 5 governance pages | one lifecycle across 5 pages | earlier product direction | **Simplify:** one lifecycle board with stage filters |
| Firm "Approval Items" vs exercise gates | two meanings of "approval" | intentional | **Keep, rename** ("Persetujuan Library") |
| What-if simulation | Dashboard only, unsaved | accidental disconnection | **Integrate:** open from the exercise with scope preset; optionally save named runs |
| Knowledge Graph | every status, unscoped, borrowed permission | low integration | **Simplify** (scope and status filter) or **hide** |
| `scenario_quadrants.implications` / `early_indicators` text | seeds for steps 7 and 9 | partly intentional | **Consolidate:** label as "usulan awal" or stop generating |
| Platform User Management | overlaps Members | intentional (operators) | **Keep, operators only** |
| Harness and clustering commands | no app caller | R&D | **Keep as developer tooling**, documented |
| 7 unused permissions, `welcome.blade.php`, `api/user`, `STEPS[].module` | 0 references | leftovers | **Remove** |
| `sources.content_hash` | written, never read | accidental | **Integrate** (warn on duplicate) |

## 6. Product coherence issues (cross-cutting)

1. **Gates and their deliberation.** A gate is recorded (who and when) but not what was agreed or why. Several can pass without the activity they certify (PI-02, PI-03, PI-09).
2. **Evidence chain.** Rich at the start (quote → signal → force) and at the end (indicator → observation → event). Broken in the middle (narrative statement → implication → response) and not navigable anywhere (PI-05).
3. **Two products in one shell.** The firm register with its 2025-era lifecycle and reports, and the 2026 exercise workspace, share data but not navigation, language or interaction patterns: axios/JSON pages vs Inertia pages (PI-15).
4. **Monitoring without a recipient** (PI-04).
5. **Recommendations without execution** (PI-11).
