# Scenario Planning Feature Audit

**Date:** 2026-10-30
**Branch / commit:** `development` @ `25888b8` (plus uncommitted tenant `exists:` rule work, which this audit did not touch)
**Method:** Static inspection of routes, controllers, services, prompts, jobs, models, migrations, seed data, React pages and tests. No application code was changed and no runtime session was driven for this audit; runtime-only claims are marked **Needs Verification**.
**Reference method:** `docs/product/SCENARIO_PLANNING_METHODOLOGY.md` (nine steps from the Era Jaya deck).

Gap IDs (`G01`…`G26`) are shared with the feature matrix, the data model gap analysis and the roadmap.

---

## 1. Executive Summary

Foresight Radar was built as a **driving-force radar and issue-management tool** (register a force → set time horizon → rate impact/uncertainty → set Monitoring / Decided Plan → approve → close) and later gained **signal ingestion with verified evidence**, a **cross-impact matrix**, and a **single-screen 2×2 scenario generator** with cited AI narratives.

Against the nine-step method:

| Step | Overall | One-line verdict |
|---|---|---|
| 1 Key Focal Issue | **Missing** | Only a nullable `scenarios.focal_question` text; no exercise, horizon year, scope or purpose |
| 2 Driving Forces | **Partial / Misaligned taxonomy** | Strong signal → force evidence chain; taxonomy has 8 of 11 categories under different names; forces are not scoped to a focal issue |
| 3 Critical Uncertainties | **Partial** | 1–10 scores and an uncertainty × impact chart exist; no rationale, single shared score, no explicit "two critical uncertainties" decision |
| 4 Scenario Framework | **Partial** | Two axes with pole labels and four positions exist; axes are single forces, quadrants appear only after narrative generation, no approval |
| 5 Develop Scenarios | **Missing** | No structured conditions; the narrative is the only description |
| 6 Narratives Imagination | **Largely implemented** | Best-aligned step: cited, verified, async, editable, provenance kept |
| 7 Challenges & Opportunities | **Partial (minimal)** | One free-text `implications` field per quadrant |
| 8 Strategic Response | **Missing** | Nothing at scenario level. Force-level "Decided Plan" is a different concept |
| 9 Early Warning Signal | **Partial (minimal)** | `early_indicators` string array per quadrant; no metric, threshold, observation or link to signals |

**Overall alignment: partial (roughly steps 2, 3, 4, 6 usable; 1, 5, 8 missing; 7, 9 token).** The most important structural gap is that there is **no scenario planning exercise** to which forces, scores, frameworks and scenarios belong (G01, G02). The most reusable assets are the evidence chain (sources → verified signals → forces), the LLM layer with structured output and citation enforcement, and the human-review patterns (`review_status`, `is_ai_generated`, `edited_at`).

---

## 2. Module Inventory

### 2.1 Navigation (`resources/js/Layouts/AuthenticatedLayout.jsx:54-68`)

Flat sidebar, 15 items, in legacy issue-management order:

Dashboard · Signal Ingestion · Driving Force · Time Horizon · Rating of Urgency · Status of Action · Approval Items · Closed Items · Prioritizing · Registered List · Foresight Radar · Cross-Impact · Knowledge Graph · Scenarios · Settings

No item corresponds to steps 1, 5, 7, 8 or 9. There is no exercise context, no step progress, and Scenarios sits after the reports.

### 2.2 Backend modules

| Module | Entry points | Purpose today |
|---|---|---|
| Signal ingestion | `SignalIngestionController`, `SignalExtractionService`, `Extraction/LlmSignalExtractor`, `Support/EvidenceVerifier`, `Jobs/ExtractSignals`, `Prompts/SignalExtractionPrompt` (`extract-v1`) | Paste a source, extract ≤8 signals (LLM or heuristic), verify quotes verbatim, human review (accept-create / accept-link / reject / update) |
| Driving force register | `DrivingForceController`, `DrivingForceService`, `DrivingForceRequest`, `DrivingForcePolicy`, `Enums/DrivingForceStatus` | CRUD with PIC, status lifecycle PENDING/APPROVED/REJECTED/CLOSED |
| Rating stages | `TimeHorizonController`, `RatingUrgencyController`, `StatusActionController`, `ApprovalController`, `ClosedItemsController` | One `driving_force_ratings` row per force: horizon, impact, uncertainty, derived priority, Monitoring/Decided Plan with reasons |
| Reports | `PrioritizingController`, `RegisteredListController`, `ForesightRadarController`, `ForesightReportingService` | Uncertainty × impact chart, register export, radar by horizon |
| Systems analysis | `CrossImpactController`, `CrossImpactService`, `CausalLoopService`, `KnowledgeGraphService` | −3..+3 influence matrix, Vester roles, reinforcing/balancing loops, graph |
| Reasoning / simulation | `DashboardController`, `ReasoningEngineService`, `ScenarioSimulationService`, `RatingHistoryAnalyticsService` | Growth / resilience indices, what-if overrides on ratings, score trends |
| Scenarios | `ScenarioController`, `ScenarioService`, `ScenarioNarrativeService`, `Jobs/GenerateScenarioNarrative`, `Prompts/ScenarioNarrativePrompt` (`scenario-v1`), `ScenarioPolicy` | 2×2 matrix from two approved forces, four cited narratives |
| LLM gateway | `Llm/StructuredLlmClient`, `AnthropicStructuredClient`, `OpenAiCompatibleClient`, `LlmClientResolver`, `NullLlmClient`, `UserLlmSettingService` | Per-user provider (anthropic, openai, gemini, openai_compatible) with JSON-schema output |
| Tenancy | `TenantContext`, `BelongsToTenant` trait, `Support/TenantRule` | Row-level tenant isolation on all domain tables |
| Harness (offline) | `Services/Harness/*` (KMeans, PCA, silhouette, theory reconciliation) | Benchmarks only; not wired to user flows |

---

## 3. Step-by-Step Findings

### Step 1 — Key Focal Issue

| Question | Finding | Evidence |
|---|---|---|
| Exists? | Only as free text on each scenario | `scenarios.focal_question` text nullable (`2026_10_16_000004_create_scenarios_tables.php`); `ScenarioRequest` `nullable|max:2000`; label "Pertanyaan fokus" in `Report/Scenarios.jsx:120` |
| Exercise / project entity | **None.** No table, model, route or page | `app/Models/` has no project/exercise model; `grep focal` hits only scenario files |
| Horizon year, scope, org context, purpose, background, objectives | **None** | — |
| Used downstream | Passed into the narrative prompt as "Focal question" | `ScenarioNarrativePrompt::request()` |
| Tests | No test covers focal issue semantics | `tests/Feature/Llm/ScenarioTest.php` |

**Verdict: Missing.** Each 2×2 matrix carries its own optional question; two scenarios built on the same forces can have different questions and nothing ties forces or scores to either (G01).

### Step 2 — Driving Forces

**What exists**

- `driving_forces`: `keyword` (≤4 words, `WordCountRule(4)`), `description` (≤20 words on create, ≤30 on update — `app/Http/Requests/DrivingForceRequest.php`, `app/Rules/WordCountRule.php:31` counts with `str_word_count` and fails above the max), `dimension_id`, `pic`, `status`, `remark`, `approved_at`, `closed_at`, soft deletes, tenant.
- Two-level taxonomy: `environments` (Macro, Mezzo) → `dimensions` (`database/data/environment.json`, `dimension.json`). Global, not per tenant.
- Evidence chain: `sources` (raw content, hash, extraction meta) → `signals` (verbatim `evidence_quote`, `quote_verified`, confidence, preliminary scores, provenance) → `signal_driving_force` (M:N with notes). `signals.created_driving_force_id` records the signal that originated a force; `suggested_driving_force_id` holds the LLM's suggested existing force.
- Review: `SignalIngestionController::review` (`:205-344`) — lock row, refuse if not PENDING, re-verify edited quotes, refuse acceptance of unverified quotes, then `accept_link` (attach to existing force) or `accept_create` (create a PENDING force from the signal, carrying horizon and preliminary scores into a rating).
- UI: `Pages/Signals/Index.jsx`, `IngestForm.jsx`, `ReviewForm.jsx`; `Pages/DrivingForce/Table.jsx`, `Form.jsx`.
- Tests: `SignalIngestionTest`, `Llm/LlmSignalExtractionTest`, `SignalExtractionRecoveryTest`, `SignalExtractionBenchmarkTest`, `DrivingForceTest`, `Security/DrivingForcePicTenantScopeTest`.

**Taxonomy comparison**

| Deck category | Seeded dimension | Status |
|---|---|---|
| Macro · Political | — (folded into "Regulation") | Missing |
| Macro · Economic | Economy | Aligned (name differs) |
| Macro · Social | — | **Missing** |
| Macro · Technology | Technology | Aligned |
| Macro · Ecology | Ecology | Aligned |
| Macro · Legislative | Regulation | Partial (also absorbs Political) |
| Mezzo · Consumer | Customer | Aligned (name differs) |
| Mezzo · Supply Chain | Supplier | Aligned (name differs) |
| Mezzo · Competitor | Competitor | Aligned |
| Mezzo · New Entrant | — | **Missing** |
| Mezzo · Substitute | Substitute | Aligned |

The LLM extractor can only choose from seeded dimension names (schema enum in `SignalExtractionPrompt::schema`), so social and new-entrant signals are forced into the wrong category (G03).

**Gaps**

- **Not scoped to a focal issue.** Forces, signals and ratings carry only `tenant_id`. A force such as "TKDN Regulation" is rated once for the whole tenant, whatever the question (G02).
- **Default time window hides forces.** Every list in `DrivingForceService` (`filter`, `timeHorizon`, `ratingUrgency`, `statusAction`, `approvalItems`, `closedItems`) filters `created_at` to the current month unless a date range is passed (`resolveDateRange`), and the pages send the current month by default (`useAnalysisFilters` initial state `currentMonth` in `Components/AnalysisFilters.jsx:22`). For a multi-month exercise, older forces vanish from the default view (G02).
- **Description too short for the method.** 20 words cannot hold a description plus its influence on the focal issue; there is no "influence" field (G04).
- **One signal → one force shortcut.** `accept_create` promotes a single signal into a force with the signal's title truncated to four words. The UI offers this beside `accept_link`; nothing encourages aggregation. No merge or de-duplication for forces exists (grep for merge/duplicate finds only signal-level de-duplication in `LlmSignalExtractor`). Clustering code exists only in `Services/Harness` benchmarks (G05).
- **Lifecycle couples validation with action tracking.** Approving a force requires a Status of Action (`ApprovalController::create` returns 422 without `status_action_id`). Status of Action is Monitoring / Decided Plan (`database/data/status_action.json`) — an operational posture, not a scenario-planning judgement. Scenario axes require `APPROVED` forces (`ScenarioService::resolveAxes`), so a force must be given an operational action before it can be a critical uncertainty (G06).
- **Collaboration.** One PIC per force; no participants, comments or consensus record (G21).

**Verdict: Partial; taxonomy Misaligned.**

### Step 3 — Critical Uncertainties

**What exists**

- Scores: `driving_force_ratings.impact_analysis`, `uncertainty_analysis` (tinyint, validated 1–10 in `RatingUrgencyController::create`). UI `Pages/RatingUrgency/Table.jsx`.
- Derived priority: `DrivingForceRating::calculatePriority()` — High when both ≥6, Medium when one ≥6, Low otherwise.
- History: `driving_force_rating_histories` written by `DrivingForceRatingObserver` with `changed_by` and `changed_at`; analysed by `RatingHistoryAnalyticsService`.
- Matrix: `Components/PrioritizingChart.jsx` ("Uncertainty × impact matrix of approved driving forces", threshold 6) on `/visualization/prioritizing`.
- Axis suggestion: `ScenarioService::suggestAxes()` ranks approved forces by impact × uncertainty (top 8), pairs those in **different dimensions** whose mutual cross-impact is below |2|, returns the top 5 pairs. Tested in `ScenarioTest::test_suggested_axes_are_strong_independent_and_from_different_dimensions`.
- AI: signal extraction proposes preliminary impact/uncertainty per signal with a rubric (`SignalExtractionPrompt::SYSTEM`); these seed the rating on `accept_create`.

**Gaps**

- **Single shared score.** `DrivingForce::rating()` is `hasOne`; `TimeHorizonController::create` does `updateOrCreate(['driving_force_id' => …])`. Each update overwrites the previous value; the history table shows who changed it, but there is no per-participant assessment, spread of opinion, or consensus value (G07).
- **No rationale** column for scores (G08).
- **No "critical uncertainty" decision.** The two axes are picked inside the scenario form. Nothing records that these two were selected, why, by whom, or that the group approved them. Suggestions rank by product of scores, which the method warns against using alone (G09).
- **No clustering into a named uncertainty.** The deck's "Government Regulation" combines two forces; the app's axis is exactly one `driving_forces` row (`scenarios.axis_a_force_id`), and its name is the force keyword (G09).
- **Predetermined elements** (high impact, low uncertainty) are shown as "Medium" priority but not carried into scenarios (G12).

**Verdict: Partial.**

### Step 4 — Scenario Framework

**What exists**

- `scenarios`: `name`, `focal_question`, `axis_a_force_id`, `axis_b_force_id`, four pole labels (`axis_*_low_label`, `axis_*_high_label`, ≤120 chars), `status` DRAFT/FINAL, provenance columns.
- `scenario_quadrants`: `position` (HH/HL/LH/LL, unique per scenario), `title`, `narrative`, `implications`, `early_indicators`, `cited_signal_ids`, `is_ai_generated`, `provider`, `edited_by`, `edited_at`.
- Validation: both axes approved, different (`ScenarioRequest`, `ScenarioService::resolveAxes`); UI warns when the pair influences each other ≥ `MAX_AXIS_INFLUENCE` (`Scenarios.jsx:143`).
- Quadrant rename: `PUT /scenarios/{scenario}/quadrants/{quadrant}` edits title, narrative, implications, indicators (`ScenarioController::updateQuadrant`, `Components/Scenario/QuadrantCard.jsx`).
- Authorization: `ScenarioPolicy` (owner or admin roles); tests in `ScenarioHarnessTest`.

**Gaps**

- **Axes are forces, not critical uncertainties** (G09). The axis label shown is the force keyword; the deck names the dimension ("Technology Development") separately.
- **Framework is coupled to narrative generation.** Quadrant rows are only created by `ScenarioNarrativeService::generate()` (LLM or template). After `store`, a scenario has zero quadrants; users cannot name the four futures or describe them before generating text (G11).
- **No quadrant description / assumptions** distinct from the narrative (G11, G12).
- **No approval.** `status` FINAL is accepted by the API (`ScenarioRequest`) but the UI never sets it (0 occurrences of `FINAL` in `Report/Scenarios.jsx` and `QuadrantCard.jsx`). There is no approver or approval timestamp (G11).
- **No pole validation** beyond non-empty labels; no AI suggestion of poles (suggestions cover axis pairs only) (G22).
- **Upstream integrity.** Axis FKs are `cascadeOnDelete()`: a hard delete of a force deletes every scenario using it. Soft delete is handled (shown as "(Dihapus)", tested in `ScenarioHarnessTest::test_scenario_remains_usable_and_shows_deleted_state_when_axis_is_soft_deleted`). Rating changes after generation are not flagged (G16).

**Verdict: Partial.**

### Step 5 — Develop Scenarios

**What exists:** the narrative and implications text only. The template fallback writes a prompt asking analysts to fill in "what changes for customers, suppliers and the organisation" (`ScenarioNarrativeService::template`).

**Gaps:** no conditions list, no assumptions, no predetermined elements, no statement of how non-axis forces behave per scenario, no side-by-side comparison, no distinctness or consistency check beyond one sentence in the prompt (*"Make the four futures clearly different"*) (G12, G23).

**Verdict: Missing.**

### Step 6 — Narratives Imagination

**What exists (strongest step)**

- Prompt `scenario-v1` (`ScenarioNarrativePrompt`): Indonesian, 120–200 words per quadrant, inline `[S1]` citations, at least one citation per quadrant, *"Do not add sources, numbers or events that the evidence does not support; when you extrapolate, say so"*, prompt-injection guard on tagged material.
- Structured output with `cited_refs` constrained to the offered references by schema enum.
- Server validation (`ScenarioNarrativeService::validated`): strips unknown references from text, rejects the whole result if any quadrant has no valid citation, requires all four positions.
- Evidence: accepted signals linked to either axis force, max 30 per axis (`ScenarioService::evidenceFor`, `EVIDENCE_PER_AXIS`). Signals were quote-verified at review time.
- Provenance: `provider`, `model`, `prompt_version`, `input_signal_ids`, `generated_at` on the scenario; `is_ai_generated`, `provider`, `edited_by/at` on quadrants. UI badges for AI / template / edited (`QuadrantCard.jsx:21-30`).
- Safety: edited quadrants are not overwritten without confirmation (409); a failed regeneration keeps the previous AI narrative; template fallback when no LLM.
- Async: `GenerateScenarioNarrative` (`ShouldBeUnique`, `tries = 1`, timeout `queue.llm_job_timeout`), `lockForUpdate` in `queue()`, stale-job window, client polling.
- Tests: 14 in `tests/Feature/Llm/ScenarioTest.php`, 8 in `ScenarioHarnessTest.php`, plus `ScenarioNarrativeBenchmarkTest`.

**Gaps**

- **Evidence is narrow.** Only signals linked to the two axis forces reach the prompt; other driving forces, predetermined elements and scenario conditions do not (G13).
- **No statement typing.** Fact, evidence-supported analysis, assumption and AI interpretation are not distinguished beyond hedging words (G13).
- **No review state** beyond `edited_at`. A narrative is "AI" or "edited", never "approved" (G14).
- **Confirmed and fixed 2026-10-30 (G24):** `assertGeneratable()` checked `$this->llm->isAvailable()` (the injected default client) while `generate()` used the user's resolved client, so with no platform key but a user or organization key the evidence precondition was skipped. It now receives the same client (`clientFor($userId)`); `ScenarioTest::test_evidence_rules_follow_the_client_the_generation_uses` fails on the old code.

**Verdict: Largely implemented, Partially Aligned** (aligned in grounding and provenance; misaligned in that it is generated straight from axes, skipping Step 5).

### Step 7 — Challenges & Opportunities

**What exists:** `scenario_quadrants.implications` (text), generated as "two to four sentences on what this future would mean for the organisation's strategy", editable.

**Gaps:** no separation of opportunities vs challenges, no item list, no link to forces or conditions, no impact description, no prioritization, no generic-item detection (G15).

**Verdict: Partial (minimal).**

### Step 8 — Strategic Response

**What exists at scenario level:** nothing.

**Possible confusion:** `status_actions` (Monitoring / Decided Plan) and `action_reasons` record an operational posture **per driving force**, with dated reasons. This is response to a force, not to a scenario, and cannot express cross-scenario robustness. The deck's Step 8 is per scenario.

**Gaps:** no response entity, no link to opportunities / challenges, no scenario coverage, no robustness view, no approval (G17).

**Verdict: Missing.**

### Step 9 — Early Warning Signal

**What exists**

- `scenario_quadrants.early_indicators` JSON array of strings (AI: "two to four observable signs"; template: "development of {axis} toward {pole}"), editable as newline-separated text.
- Indirect monitoring assets: continuous signal ingestion; force-level Monitoring status; rating history trends; the radar.

**Gaps:** indicators are strings inside a quadrant — no entity, no link to forces or axes, no metric, unit, baseline, threshold, frequency or owner, no observation log, no link from newly accepted signals to indicators, no view of which scenario is gaining relevance, no review trigger (G18, G19).

**Verdict: Partial (minimal).**

---

## 4. Cross-Cutting Findings

### 4.1 End-to-end workflow

Today a user can: ingest a source → review signals → create or link forces → set horizon → rate → set status of action → approve → open Scenarios → pick two approved forces and poles → generate narratives → edit. That covers steps 2, 3 (partly), 4 (partly), 6 and token 7/9. The path is spread across eight sidebar entries, each with its own month filter, and has no exercise context, step progress or gates. Details in `docs/product/SCENARIO_PLANNING_TARGET_WORKFLOW.md` §2.

### 4.2 Upstream change handling

None. Changing a force's score, category or status after a scenario is generated does not mark the scenario stale. Unlinking or rejecting evidence leaves `input_signal_ids` pointing at it (deleted signals render as "Sinyal sudah dihapus"). Hard-deleting an axis force cascades the scenario (G16).

### 4.3 AI vs human decisions

Good patterns exist and should be reused: `signals.review_status` (PENDING/ACCEPTED/REJECTED with reviewer and reason), `is_ai_generated`, `edited_by/at`, provider/model/prompt version. They are not applied to scores, axis selection, framework approval, or any of steps 5, 7–9.

### 4.4 Collaboration

Multi-user tenants with roles (`developer`, `super-admin`, `admin`, `bod`), invitations, PIC per force, permission-gated approval, rating history with `changed_by`. Missing: exercise participants, per-participant scoring, comments, consensus, approvals beyond forces (G07, G21). This is a single-score, single-approver CRUD model, which falls short of slide 14.

### 4.5 LLM architecture

`StructuredLlmClient::generateJson(LlmRequest)` with JSON schema, purpose, effort and max tokens; providers via `LlmClientResolver::createClient` (`anthropic`, `openai`, `gemini` through Google's OpenAI-compatible endpoint, `openai_compatible`); per-user settings (`UserLlmSetting`); `NullLlmClient`; versioned prompts in `app/Services/Llm/Prompts`; queued jobs with unique locks and status columns. **Sufficient for this phase**: every proposed AI capability is "one more prompt + schema + validator + (optional) job". **Confirmed and fixed 2026-10-30 (G24 / TENANT-008):** `TenantLlmSetting` was unused. `LlmClientResolver` now resolves user key → organization key → platform default, every client counts against the tenant's monthly token quota, and admins manage the organization key on Settings → Organization.

### 4.6 Code-hygiene observations (outside the method, recorded for completeness)

- `ScenarioController` uses `auth()->id()` in `store`, `update`, `updateQuadrant`, `generate`, contrary to AGENTS.md §10 (G25).
- `docs/product/DOMAIN_GAPS.md` and `FORESIGHT_DOMAIN_MODEL.md` (2026-10-09) state that Signal and Scenario are not modeled; both now exist. `SIGNAL_TO_INSIGHT_SPEC.md` still mentions Ant Design. These should be marked superseded, not deleted (G26).

---

## 5. Reusable Components

| Asset | Reuse for |
|---|---|
| `sources` / `signals` / `signal_driving_force` / `EvidenceVerifier` | Evidence for forces (step 2), score rationale (3), narratives (6), indicator observations (9) |
| `signals.review_status` pattern | Review state for every AI-proposed artefact |
| `driving_forces` + `environments` / `dimensions` | Step 2 after taxonomy fix and exercise scoping |
| `driving_force_rating_histories` + observer | Audit trail model for assessments |
| `PrioritizingChart.jsx` | Step 3 matrix (add exercise filter, selection) |
| `ScenarioService::suggestAxes` + `CrossImpactService` | Candidate pairs and independence check for step 3 |
| `scenarios` / `scenario_quadrants` | Step 4 framework and step 6 narratives (extend, do not replace) |
| `ScenarioNarrativeService` validation + template fallback | Pattern for every new generator |
| `GenerateScenarioNarrative` / `ExtractSignals` + `LlmJobStatus` | Pattern for every async generation |
| `StructuredLlmClient`, `LlmClientResolver`, prompt classes | All AI assists |
| `FormDialog`, `FormField`, `DataTable`, `Panel`, `QuadrantCard` | New screens |
| `TenantContext`, `BelongsToTenant`, `TenantRule` | Isolation for all new tables |

---

## 6. Gap Register

| ID | Gap | Steps | Priority |
|---|---|---|---|
| G01 | ~~No scenario planning exercise; focal issue is optional text per scenario; no horizon, scope, purpose~~ Fixed 2026-10-31 (R0.1); linking scenarios to a project follows in R0.5 | 1 | P0 |
| G02 | ~~Forces, signals, ratings not scoped to an exercise; lists default to current month~~ Forces scoped per exercise (R0.2) and scores per exercise (R1.1), 2026-10-31 | 1–3 | P0 |
| G03 | ~~Taxonomy has 8 of 11 categories; Political and Legislative merged; Social and New Entrant missing~~ Fixed 2026-10-30 (R0.3) | 2 | P0 |
| G04 | ~~Force description ≤20/30 words; no influence-on-focal-issue field~~ Fixed (R0.2 influence note per exercise; R1.2 description up to 120 words, 2026-11-02) | 2 | P1 |
| G05 | ~~One-signal-to-one-force shortcut; no merge / duplicate handling~~ Fixed 2026-11-02 (R1.3); AI clustering of signals into candidate forces remains P2 | 2 | P1 |
| G06 | ~~Approval requires Status of Action; operational lifecycle gates scenario use~~ Fixed for exercises 2026-10-31 (R0.2, R0.4); the legacy scenario form still requires APPROVED forces until R0.5 | 2–3 | P1 |
| G07 | One shared score per force; no per-participant assessment or consensus | 3 | P2 |
| G08 | ~~No score rationale~~ Fixed 2026-10-31 (R1.1) | 3 | P1 |
| G09 | ~~No critical-uncertainty entity; axis = one force; no grouping, naming, rationale, approval~~ Fixed 2026-10-31 (R0.4); scenario axes switch to uncertainties in R0.5 | 3–4 | P0 |
| G11 | ~~Quadrants exist only after generation; no description/assumptions; FINAL not exposed; no approval~~ Fixed 2026-10-31 (R0.5): quadrants on save, summary, gate G5; assumptions move to step 5 (R1.5) | 4 | P0 |
| G12 | ~~No scenario conditions, assumptions, predetermined elements~~ Fixed 2026-10-31 (R1.5); AI-drafted conditions remain P2 | 5 | P1 |
| G13 | ~~Narrative evidence limited to axis signals~~ (fixed 2026-10-31, R1.6: conditions, predetermined elements, condition-force evidence); statement typing fixed 2026-11-09 (R2.7: `scenario-v3` typed statements, `scenario_citations`) | 6 | P1 |
| G14 | ~~No review/approval state for narratives~~ Fixed 2026-10-31 (R1.6): draft / reviewed / approved per quadrant, gate G6 | 6 | P1 |
| G15 | ~~Challenges/opportunities are one free-text field~~ Fixed 2026-10-31 (R1.7): typed items with impact, priority and links; AI drafts remain P2 | 7 | P1 |
| G16 | ~~No staleness flags; hard delete of axis force cascades scenario~~ Fixed (R0.5 delete rules; R1.10 stale flags with keep action, 2026-11-01) | all | P1 |
| G17 | ~~No strategic responses~~ Fixed 2026-11-01 (R1.8): responses with fit per quadrant, robustness and approval G7; AI suggestions remain P2 | 8 | P1 |
| G18 | ~~Indicators are strings; no entity, metric, threshold, frequency, links~~ Fixed 2026-11-01 (R1.9): indicator register with direction per quadrant; observations are G19 (P2) | 9 | P1 |
| G19 | ~~No observations, signal→indicator matching, scenario relevance view~~ Fixed 2026-11-08 (R2.3): observation log with accepted-signal link, relevance view, threshold review trigger; AI signal matching added 2026-11-09 (R2.6) | 9 | P2 |
| G20 | ~~Navigation is flat, legacy order; no methodology progress~~ Fixed 2026-11-02 (R1.11): grouped sidebar, exercise stepper, overview with next action and last gate | all | P1 |
| G21 | ~~No participants, comments, consensus; approvals only for forces~~ Fixed 2026-11-08 (R2.1, R2.2): participants with roles, comments, consensus scoring, gate decision log | 2–8 | P2 |
| G22 | No AI assist for focal issue, scores, poles, conditions, responses, standalone indicators (partly fixed 2026-11-08/09, R2.4: score suggestions and candidate forces; R2.5: poles, quadrant names and conditions; R2.6: implications, responses, indicators, signal matching; R3.1: focal issue, 2026-11-09 — all fixed) | 1,3,4,5,8,9 | P2 |
| G23 | ~~No scenario comparison view~~ Fixed 2026-11-09 (R2.8 slice 1: comparison page) | 5–8 | P2 |
| G24 | ~~Tenant LLM settings unused; evidence precondition checks default client~~ Fixed 2026-10-30 (roadmap R3.2, TENANT-008) | 6 | P3 |
| G25 | `auth()` helper in `ScenarioController` | — | P3 |
| G26 | Older product docs outdated | — | P3 |

(G10 was merged into G09 during the audit; the ID is intentionally unused.)

---

## 7. Not Verified

- Runtime behaviour of the UI flows (no browser session in this audit; AGENTS.md §11 requires Chrome DevTools MCP for UI verification).
- Baseline `./init.sh` / `composer verify` were not run, because no application code changed. The last recorded baseline is 2026-10-26 (PROGRESS.md).
- Exact rendering of `PrioritizingChart` labels and whether the BOD role sees scenarios correctly per tenant.
