# Scenario Planning Target Feature Specification

**Date:** 2026-10-30
**Status:** Blueprint for development; nothing here is implemented unless labelled **[Existing]**.
**Labels:** **[Existing]** works today and is kept · **[Extend]** exists, needs change · **[New]** does not exist.
**References:** method `SCENARIO_PLANNING_METHODOLOGY.md`; workflow `SCENARIO_PLANNING_TARGET_WORKFLOW.md`; schema `docs/audit/SCENARIO_PLANNING_DATA_MODEL_GAP.md`; gaps G01–G26 in `docs/audit/SCENARIO_PLANNING_FEATURE_AUDIT.md`; roadmap IDs (R0.x, R1.x, R2.x, R3.x) in `SCENARIO_PLANNING_DEVELOPMENT_ROADMAP.md`.

## Shared conventions (apply to every step)

- **Exercise scope.** Every new record belongs to a `scenario_planning_projects` row and the current tenant (`BelongsToTenant`, `TenantRule::exists`).
- **Origin and review.** New artefacts carry `origin` (human / ai / template) and `review_status`. AI artefacts start as proposals. Reuse the badges in `Components/Scenario/QuadrantCard.jsx`.
- **AI contract.** One prompt class per capability in `app/Services/Llm/Prompts` with `VERSION`, `SYSTEM`, `request()`, `schema()`; references (signals, forces) are passed as labels and constrained by schema enum; the service validates references and drops or rejects invalid ones (pattern: `ScenarioNarrativeService::validated`). Long calls run as `ShouldBeUnique` jobs with a status column and polling (pattern: `GenerateScenarioNarrative`). Provider comes from `LlmClientResolver`; no new provider architecture.
- **Authorization.** Permission gates on routes (`can:`) plus a `ProjectPolicy` (owner, coordinator, admin roles), following `ScenarioPolicy`.
- **Backend style.** Lean controllers using `HandlesApiErrors`; logic in services; `Auth::id()` not `auth()`.
- **Frontend.** Pages under `resources/js/Pages/ScenarioPlanning/` (<150 lines each), organisms under `Components/ScenarioPlanning/`, domain calculations in `resources/js/domain/`, workflow hooks in `resources/js/hooks/`; shadcn/Radix primitives only; no Sparkles icons.

---

## Step 1 — Key Focal Issue

**Objective.** Define the exercise and the question it answers.

**User capabilities.** Create, edit, archive an exercise; state focal question, purpose, horizon, scope, context; confirm (G1).

| Feature | Label | Required? |
|---|---|---|
| Project list and create dialog | [New] | Required |
| Focal issue form: focal question, purpose, target year | [New] | Required |
| Scope and context: geography, industry, owner unit, owner, background, objectives | [New] | Optional fields |
| Confirm focal issue (G1) | [New] | Required |
| AI "sharpen focal question" | [New] | Optional (P3) |
| `scenarios.focal_question` | [Extend] | Read from project when `project_id` set; kept for legacy rows |

**Inputs.** User text. **Outputs.** Project record.
**Business rules.** One focal question per project. Target year ≥ current year + 1. Editing focal question / target year after G1 sets `stale_since` on downstream artefacts.
**Validation.** focal_question required, 10–500 chars; purpose required ≤1000; target_year integer 2000–2100; owner must be a tenant user.
**AI.** Optional rewrite suggestion; never auto-applied.
**Human review.** G1 by owner or coordinator.
**Data.** `scenario_planning_projects` (DM §5.1).

**Acceptance criteria**
1. A user with permission can create a project with focal question, purpose and target year; the project appears only in their tenant.
2. Steps 2–9 are disabled until the project has those three fields.
3. Confirming records `confirmed_by` and `confirmed_at`; editing the question afterwards shows a stale banner on later steps.
4. A cross-tenant owner id is rejected (feature test).

## Step 2 — Driving Forces

**Objective.** Build the exercise's categorized, evidence-backed force list.

**User capabilities.** Add forces from the library or create new; classify macro/mezzo and category; describe influence on the focal issue; link signals; merge duplicates; include/exclude; agree the list (G2).

| Feature | Label | Required? |
|---|---|---|
| Signal ingestion, quote verification, review | [Existing] | Required |
| Force library CRUD | [Existing] | Required |
| Eleven-category taxonomy | [Extend] (seed data + dimension `code`) | Required |
| Description limit raised to ~120 words | [Extend] `DrivingForceRequest` | Required |
| Project force list (include/exclude, influence note, predetermined flag) | [New] `project_driving_forces` | Required |
| Signal review defaults to "link to force"; "create force" asks for force-level name/description | [Extend] `Signals/ReviewForm.jsx`, `SignalIngestionController::review` | Required |
| Merge forces | [New] | Required (P1) |
| Lists filtered by project instead of current month inside the workspace | [New] | Required |
| AI "suggest forces from accepted signals" | [New] | Optional (P2) |
| Category coverage indicator (which of 11 categories have no force) | [New] | Optional |

**Inputs.** Signals, library forces, expert input. **Outputs.** Included forces for the project.
**Business rules.** A signal never becomes a force automatically. A force may be in several projects; inclusion, influence and predetermined flag are per project. Merge moves signal links, cross-impacts, project links and assessments to the surviving force and keeps a tombstone. Inclusion in a project does **not** require the operational Status of Action (G06).
**Validation.** keyword ≤4 words; description required; category from the 11; cannot include a soft-deleted force.
**AI.** Existing extraction proposes category and related force; new clustering prompt proposes candidate forces with the signal refs they group; proposals reviewed one by one.
**Human review.** G2 by coordinator.
**Data.** `driving_forces`, `dimensions`, `signal_driving_force`, `project_driving_forces`.

**Acceptance criteria**
1. The category select and the extraction schema list exactly the 11 deck categories under Macro and Mezzo.
2. A force can be included in project A and excluded from project B; each shows its own influence note.
3. Merging force X into Y moves all links; X disappears from lists; a scenario axis that used X is flagged stale, not deleted.
4. Forces created in earlier months appear in the project list without a date filter.
5. Accepting a signal with "create force" requires a force name distinct from the truncated signal title (or explicit confirmation).

## Step 3 — Critical Uncertainties

**Objective.** Score included forces on uncertainty and impact, then choose and justify two critical uncertainties.

**User capabilities.** Score 1–10 with rationale; view the matrix; see suggested candidates; create a critical uncertainty grouping one or more forces; name it; give poles (optional here, required in step 4); state rationale; approve (G3, G4).

| Feature | Label | Required? |
|---|---|---|
| Impact / uncertainty 1–10 | [Existing] scores; [Extend] to project-scoped consensus assessments | Required |
| Rationale per score | [New] | Required (P1) |
| Matrix with numbered points, threshold quadrants, predetermined zone | [Extend] `PrioritizingChart.jsx` | Required |
| Candidate pair suggestion with independence check | [Extend] `ScenarioService::suggestAxes` to read project assessments | Required |
| Critical uncertainty create/edit (name, forces, rationale) | [New] | Required (P0) |
| Approve two (G4) | [New] | Required (P0) |
| Individual assessments per participant, spread, consensus | [New] | P2 |
| AI score suggestions with rationale citing signals | [New] | P2 |

**Inputs.** Included forces, linked signals, cross-impact scores. **Outputs.** Consensus scores; two approved critical uncertainties.
**Business rules.** Candidates = consensus impact ≥6 and uncertainty ≥6 (existing threshold in `DrivingForceRating::calculatePriority`). High impact + low uncertainty are labelled predetermined and offered to step 5. Selection is a human decision; the system never auto-approves the top two. Exactly two approved uncertainties with an axis per project at a time. Pair independence: warn when any force in A and any force in B have cross-impact abs ≥2 (existing `MAX_AXIS_INFLUENCE`).
**Validation.** Scores integers 1–10; rationale ≤1000 chars; critical uncertainty requires ≥1 included force, name ≤120, selection rationale required.
**AI.** Score suggestions are `ai_suggestion` rows; accepting copies values into the consensus row with the AI rationale editable.
**Human review.** G3 (scores), G4 (selection).
**Data.** `force_assessments`, `critical_uncertainties`, `critical_uncertainty_forces`, `cross_impacts`.

**Acceptance criteria**
1. The matrix plots only forces included in the current project using their project scores.
2. A user can create "Government Regulation" grouping Political and Legislative forces.
3. Approving a third uncertainty with an axis fails with a clear message.
4. Approval stores approver, time and rationale; the matrix marks the two approved groups.
5. Changing a score of a force inside an approved uncertainty flags the framework stale.

## Step 4 — Scenario Framework

**Objective.** Turn the two uncertainties into a named 2×2.

**User capabilities.** Define poles; generate four quadrants immediately; name and summarise each; approve (G5).

| Feature | Label | Required? |
|---|---|---|
| Scenario header with two axes and four pole labels | [Existing] `scenarios` | Required |
| Axes reference critical uncertainties | [Extend] | Required (P0) |
| Four quadrant rows created on save, without narrative | [Extend] `ScenarioController::store`, `ScenarioNarrativeService` | Required (P0) |
| Quadrant name (title) edit | [Existing] | Required |
| Quadrant one-line summary | [New] | Required |
| Framework approval (G5), locks axes and poles | [Extend] (`status` FINAL exists in API only) | Required (P0) |
| AI pole and name suggestions | [New] | P2 |
| Legacy scenarios (force axes) | [Existing] | Kept read-only-compatible |

**Inputs.** Two approved uncertainties. **Outputs.** Approved framework, four named quadrants.
**Business rules.** Axes must be the project's two approved uncertainties. Poles are two end states of one uncertainty. Quadrant positions stay HH/HL/LH/LL (first letter axis A). Narrative generation no longer creates or deletes quadrant rows; it fills fields. After G5, changing poles requires re-approval.
**Validation.** Pole labels required ≤120 chars and different within an axis; quadrant names unique within a scenario.
**AI.** Optional suggestions; never saved without user action.
**Human review.** G5.
**Data.** `scenarios` (+ `project_id`, `axis_*_uncertainty_id`, `framework_status`, `approved_*`), `scenario_quadrants` (+ `summary`).

**Acceptance criteria**
1. Saving a framework produces four quadrants with default names "{pole A} · {pole B}" (current template convention), editable before any generation.
2. The matrix renders axis names from the uncertainties, e.g. "Government Regulation" × "Technology Development".
3. Approve sets `framework_status = approved`, approver and time; pole fields become read-only with a "Reopen" action.
4. Hard-deleting a force used by an approved framework is refused.

## Step 5 — Develop Scenarios

**Objective.** Describe the conditions of each future before writing narratives.

| Feature | Label | Required? |
|---|---|---|
| Conditions list per quadrant (kind: pole expression / force behaviour / assumption) | [New] | Required (P1) |
| Predetermined elements shown on all four quadrants | [New] | Required (P1) |
| Link a condition to a non-axis force | [New] | Optional |
| Comparison grid (rows = conditions themes, columns = quadrants) | [New] | P2 |
| AI "draft conditions" and distinctness check | [New] | P2 (draft), P2 (check) |

**Business rules.** ≥2 conditions per quadrant before step 6 is marked complete. A condition text identical across all four quadrants is flagged as a possible predetermined element.
**Validation.** Condition ≤500 chars; `axis` A/B/null; linked force must be included in the project.
**AI.** Prompt receives focal issue, target year, both axes with poles, included forces with scores, predetermined elements; returns 3–5 conditions per quadrant referencing forces by label.
**Human review.** Accept/reject per condition.
**Data.** `scenario_conditions`.

**Acceptance criteria**
1. Users can add, reorder, edit and delete conditions per quadrant manually.
2. AI-drafted conditions show as proposals and do not count toward completion until accepted.
3. Each quadrant shows the project's predetermined elements read-only.

## Step 6 — Narratives Imagination

**Objective.** A grounded, cited story per scenario.

| Feature | Label | Required? |
|---|---|---|
| Async narrative generation with citations, fallback template, overwrite guard | [Existing] | Required |
| Provenance (provider, model, prompt version, inputs) | [Existing] | Required |
| Prompt v2: include conditions, predetermined elements, target year, path from today | [Extend] `ScenarioNarrativePrompt` | Required (P1) |
| Evidence beyond axis forces (signals of forces referenced by conditions) | [Extend] `ScenarioService::evidenceFor` | Required (P1) |
| Review status draft / reviewed / approved; G6 | [New] | Required (P1) |
| Statement typing (fact / analysis / assumption / interpretation) | [New] | P2 |
| Citations as rows (`scenario_citations`) | [New] | P2 |
| Evidence precondition uses the resolved user client | [Extend] (G24) | P3 |

**Business rules.** Existing citation rules stay: only offered refs; a quadrant without a valid citation rejects the result. Narrative must reference the quadrant's conditions. Regenerating an approved quadrant resets it to draft after confirmation.
**Validation.** As today (`ScenarioQuadrantRequest`).
**AI.** `scenario-v2`; schema adds optional `statements[] {text, kind, refs}` in P2.
**Human review.** G6 per quadrant.
**Data.** `scenario_quadrants`, `scenarios` provenance, later `scenario_citations`.

**Acceptance criteria**
1. All existing tests in `tests/Feature/Llm/ScenarioTest.php` and `ScenarioHarnessTest.php` still pass.
2. With conditions present, the prompt payload contains them (test with `FakeLlmClient`-style fake).
3. Approving all four quadrants completes step 6 and records approver per quadrant.
4. Unreviewed AI narratives are counted on the project overview.

## Step 7 — Challenges & Opportunities

| Feature | Label | Required? |
|---|---|---|
| Opportunity / challenge items per quadrant with impact level, priority | [New] | Required (P1) |
| Links to forces and conditions | [New] | Required (P1) |
| Migration of existing `implications` text into one untyped item for review | [Extend] | Required |
| Generic-item flag (same text in all four quadrants) | [New] | P2 |
| AI "draft opportunities and challenges" | [New] | P2 |

**Business rules.** Each quadrant needs ≥1 opportunity and ≥1 challenge to complete the step. Items reference the conditions that cause them where possible.
**Validation.** type ∈ {opportunity, challenge}; title ≤160; impact_level ∈ {low, medium, high}.
**AI.** Input: conditions, narrative, focal issue, purpose, organization context. Output items must reference at least one condition label.
**Human review.** Accept / prioritize.
**Data.** `scenario_implications`, `implication_links`.

**Acceptance criteria**
1. Users can add opportunities and challenges per quadrant manually and link them to forces.
2. The deck example (FAVOUR: 1 opportunity, 2 challenges) can be entered exactly.
3. Existing `implications` text is still visible after migration.

## Step 8 — Strategic Response

| Feature | Label | Required? |
|---|---|---|
| Strategic responses per project with rationale, initiatives, priority, owner | [New] | Required (P1) |
| Link to opportunities / challenges | [New] | Required (P1) |
| Scenario fit per quadrant (applies / partial / not) and robustness badge | [New] | Required (P1) |
| Approval (G7) | [New] | Required (P1) |
| Assumption dependency | [New] | P2 |
| AI "suggest responses" and suggested fit | [New] | P2 |

**Business rules.** Do not reuse force-level Status of Action / `action_reasons`. A response applying to ≥3 quadrants is labelled "robust"; to 1 quadrant "scenario-specific"; otherwise "conditional". AI responses start `proposed`; only `approved` responses appear in exports as decisions. Substantive edit of an approved response returns it to `proposed`.
**Validation.** title ≤160; rationale required for approval; fit rows required for all four quadrants before approval.
**AI.** Input: approved implications per quadrant; output responses referencing implication labels, with suggested fit.
**Human review.** G7 by owner.
**Data.** `strategic_responses`, `response_implications`, `response_scenarios`.

**Acceptance criteria**
1. "Secure USP for each xEV model" can be recorded once and marked as applying to FAVOUR and STAGNATION (deck slide 24).
2. The robustness view lists responses by number of quadrants they apply to.
3. An AI-proposed response is visibly "Proposed" and excluded from the approved list until approved.

## Step 9 — Early Warning Signal

| Feature | Label | Required? |
|---|---|---|
| Indicator register per project (description, linked uncertainty/force, metric, unit, baseline, frequency, owner, source type, threshold) | [New] | Required (P1) |
| Expected direction per quadrant | [New] | Required (P1) |
| Migration of `early_indicators` strings into indicator proposals | [Extend] | Required |
| Observation log (manual) | [New] | P2 |
| Link accepted signal → indicator observation; AI match suggestion | [New] | P2 |
| Monitoring dashboard: which scenario the observations point to | [New] | P2 |
| Threshold crossed → review task, stale flags | [New] | P2 |
| External data integrations | [New] | P3 |

**Business rules.** Metric fields optional (qualitative indicators allowed). `source_type` makes clear whether monitoring is manual, from the signal feed, or external (future). An indicator should have different expected directions in at least two quadrants; otherwise warn that it does not discriminate. Scenario relevance is a simple count of observations matching each quadrant's expected direction; it is a hint, not a probability.
**Validation.** name ≤160; frequency from list; owner tenant user; threshold text ≤500.
**AI.** Suggest indicators from axes, conditions, and the four themes pattern in the deck; match newly accepted signals to indicators as proposals.
**Human review.** G8; each observation confirmed by a person.
**Data.** `early_warning_indicators`, `indicator_scenarios`, `indicator_observations`.

**Acceptance criteria**
1. "xEV adoption growth" can be one indicator with direction "rising" for FAVOUR and DICHOTOMY and "falling/slow" for STAGNATION and CHALLENGING (deck slide 25).
2. Each indicator shows whether it is monitored manually or from signals.
3. Recording an observation that crosses a threshold flags the project for review (P2).

---

## Cross-Cutting Features

| Feature | Label | Priority | Acceptance criteria |
|---|---|---|---|
| Project workspace with nine-step stepper and overview | [New] | P1 | Shows step status, last gate, stale count, unreviewed AI count, next action |
| Navigation regroup (Scenario Planning / Library / Analysis / Governance) | [Extend] `AuthenticatedLayout.jsx` | P1 | No existing route removed; permissions unchanged |
| Staleness flags and acknowledgement | [New] | P1 | Upstream edit → downstream banner with reason; "Keep" clears with audit |
| `ai_generation_runs` provenance | [New] | P1 | Every AI artefact links a run with provider, model, prompt version |
| Participants, comments, consensus | [New] | P2 | Participants per project; comment threads on forces, uncertainties, quadrants, responses |
| Scenario comparison view | [New] | P2 | Four columns: name, summary, conditions, top implications, responses, indicators |
| Export (PDF / slides) of an approved exercise | [New] | P3 | Mirrors deck slides 15–25 structure |
