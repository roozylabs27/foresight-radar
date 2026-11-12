# Scenario Planning Feature Alignment Matrix

**Date:** 2026-10-30
**Basis:** `docs/audit/SCENARIO_PLANNING_FEATURE_AUDIT.md` (evidence and gap IDs) and `docs/product/SCENARIO_PLANNING_METHODOLOGY.md` (required capabilities).

## Legend

**Status** (overall, per capability)

| Status | Meaning |
|---|---|
| Complete | Implemented, usable, aligned with the method |
| Partial | Some of it exists; the workflow is incomplete |
| Missing | No meaningful implementation |
| Misaligned | Exists, but behaves against the method |
| Needs Verification | Static evidence insufficient |

**FE / BE / Data:** I = Implemented, P = Partial, M = Missing.
**Alignment:** A = Aligned, PA = Partially Aligned, MA = Misaligned, — = nothing to align.
**Priority:** P0 essential alignment · P1 core completeness · P2 advanced / collaborative · P3 enhancement.

---

## Step 1 — Key Focal Issue

| # | Required capability | Existing feature | Status | FE | BE | Data | AI | Align | Gap | Recommendation | Pri | Evidence |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| 1.1 | Create a scenario planning exercise | None | Complete (2026-10-31) | I | I | I | — | A | G01 | Done (R0.1) | P0 | No model in `app/Models`; no route in `routes/web.php` |
| 1.2 | Focal question | `scenarios.focal_question` (optional, per matrix) | Complete (2026-10-31) | I | I | I | Passed to narrative prompt | A | G01 | Done (R0.1) | P0 | `2026_10_16_000004_create_scenarios_tables.php`; `ScenarioRequest`; `Scenarios.jsx:120` |
| 1.3 | Planning horizon / target year | None | Complete (2026-10-31) | I | I | I | — | A | G01 | Done (R0.1) | P0 | grep `target_year` → none |
| 1.4 | Geographic / industry scope | None | Complete (2026-10-31) | I | I | I | — | A | G01 | Done (R0.1) | P1 | — |
| 1.5 | Organization / business context, owner | Tenant only | Complete (2026-10-31) | I | I | I | — | A | G01 | Done (R0.1) | P1 | `tenants` table |
| 1.6 | Purpose, background, objectives | None | Complete (2026-10-31) | I | I | I | — | A | G01 | Done (R0.1) | P0 (purpose) / P1 | Deck slide 15 pairs question + purpose |
| 1.7 | AI refinement of focal issue | None | Complete (2026-11-09) | I | I | I | Prompt `focal-issue-v1` | A | G22 | Done (R3.1): queued critique and alternative questions; applied only through the edit dialog | P3 | — |
| 1.8 | Sponsor confirmation | None | Complete (2026-10-31) | I | I | I | — | A | G01 | Done (R0.1) | P1 | — |

## Step 2 — Driving Forces

| # | Required capability | Existing feature | Status | FE | BE | Data | AI | Align | Gap | Recommendation | Pri | Evidence |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| 2.1 | Create / edit / delete driving forces | Driving Force register | Complete (as CRUD) | I | I | I | — | PA | G02 | Keep; scope to exercise | — | `DrivingForceController`, `DrivingForce/Table.jsx`, `Form.jsx`, `DrivingForceTest` |
| 2.2 | Macro / mezzo level | `environments` (Macro, Mezzo) | Complete | I | I | I | — | A | — | Keep | — | `database/data/environment.json` |
| 2.3 | Eleven deck categories | 8 seeded dimensions | Complete (2026-10-30) | I | I | P | LLM picks from seeded list | A | G03 | Done (R0.3): eleven categories with codes and order; "Regulation" mapped to Legislative pending client question 5 | P0 | `database/data/dimension.json`; `SignalExtractionPrompt::schema` |
| 2.4 | Description and explanation | `description` ≤20 words (create) / ≤30 (update) | Complete (2026-11-02) | I | I | I | — | A | G04 | Done (R1.2): description up to 120 words, keyword ≤4 words | P1 | `DrivingForceRequest`; `WordCountRule.php:31` |
| 2.5 | Influence on the focal issue | None | Complete (2026-10-31) | I | I | I | — | A | G04 | Done (R0.2): per-exercise influence note | P1 | — |
| 2.6 | Link supporting signals (M:N) | `signal_driving_force` pivot, accept_link | Complete | I | I | I | Extractor suggests related force | A | — | Keep; show signal count per force in exercise | — | `SignalIngestionController::review` (`:264-279`); `Signal::driving_forces()` |
| 2.7 | Signals contribute to, not become, forces | `accept_create` turns one signal into one force | Complete (2026-11-02) | I | I | I | — | A | G05 | Done (R1.3): link is the default; a new force needs its own name and description and may collect more signals | P1 | `SignalIngestionController.php:281-325` |
| 2.8 | Merge / de-duplicate forces | None | Complete (2026-11-02) | I | I | I | — | A | G05 | Done (R1.3): merge moves links, cross-impacts, exercise links and axes; tombstone | P1 | grep merge → signal-level only |
| 2.9 | AI candidate forces from signal clusters | Harness KMeans (benchmark only) | Complete (2026-11-08) | I | I | I | Prompt `force-cluster-v1` | A | G05, G22 | Done (R2.4): queued AI candidate forces from accepted signals, reviewed one by one; accept adds a PENDING library force to the exercise with its signals | P2 | `app/Services/Harness/KMeansClusterer.php` |
| 2.10 | Scope forces to the focal issue | Tenant-wide; month filter by `created_at` | Complete (2026-10-31) | I | I | I | — | A | G02 | Done (R0.2): exercise list, no month filter; the tenant-wide modules keep their month filter | P0 | `DrivingForceService::resolveDateRange` |
| 2.11 | Validation of the force list | Force approval requires Status of Action | Complete (2026-10-31) | I | I | I | — | A | G06 | Done (R0.2, R0.4): inclusion and grouping ignore Status of Action | P1 | `ApprovalController::create` (`:56-66`) |
| 2.12 | Collaborative assessment / expert validation | PIC + approver permission | Complete (2026-11-08) | I | I | I | — | A | G21 | Done (R2.1 + R2.2): participants score alone, coordinator agrees the consensus, comments on forces | P2 | `driving_forces.pic`; `create-approval-items` permission |

## Step 3 — Critical Uncertainties

| # | Required capability | Existing feature | Status | FE | BE | Data | AI | Align | Gap | Recommendation | Pri | Evidence |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| 3.1 | Uncertainty 1–10 | `uncertainty_analysis` | Complete | I | I | I | Preliminary score from extraction | A | G02 | Done (R1.1): scored per exercise in `force_assessments` | — | `RatingUrgencyController::create`; `RatingUrgency/Table.jsx` |
| 3.2 | Impact 1–10 | `impact_analysis` | Complete | I | I | I | Preliminary score from extraction | A | G02 | Done (R1.1): scored per exercise in `force_assessments` | — | same |
| 3.3 | Multiple assessors / consensus | One shared rating; history log | Complete (2026-11-07) | I | I | I | — | A | G07 | Done (R2.1): individual rows per assessor, spread (min / max / median, wide flag), coordinator consensus distinct; participants list comes with R2.2 | P2 | `DrivingForce::rating()` hasOne; `TimeHorizonController` `updateOrCreate`; `driving_force_rating_histories` |
| 3.4 | Score rationale | None | Complete (2026-10-31) | I | I | I | Extraction has `confidence_rationale` per signal only | A | G08 | Done (R1.1): rationale per score | P1 | `2024_07_30_074945_create_driving_forces_table.php` |
| 3.5 | Uncertainty × impact matrix | Prioritizing chart | Complete (2026-10-31) | I | I | I | — | A | G02 | Done (R1.1): exercise matrix with numbered points | P1 | `Components/PrioritizingChart.jsx`; `/visualization/prioritizing` |
| 3.6 | High-impact / high-uncertainty highlight | Threshold 6 → High priority | Complete | I | I | I | — | A | — | Done (R1.1): candidate and predetermined zones labelled | P1 | `DrivingForceRating::calculatePriority()` |
| 3.7 | Select exactly two critical uncertainties | Implicit: axis picks in scenario form | Complete (2026-10-31) | I | I | I | Pair suggestions | A | G09 | Done (R0.4): exactly two approved per exercise | P0 | `ScenarioService::suggestAxes`; `ScenarioRequest` axis fields |
| 3.8 | Group several forces into one named uncertainty | Axis = exactly one force | Complete (2026-10-31) | I | I | I | — | A | G09 | Done (R0.4): an uncertainty groups 1..n exercise forces under its own name | P0 | `scenarios.axis_a_force_id` |
| 3.9 | Selection rationale | None | Complete (2026-10-31) | I | I | I | — | A | G09 | Done (R0.4): selection reason required | P0 | — |
| 3.10 | Independence check | Cross-impact abs(score) < 2 warning | Complete | I | I | I | — | A | — | Reuse for critical-uncertainty pairs | — | `ScenarioService::MAX_AXIS_INFLUENCE`; `Scenarios.jsx:143`; `ScenarioTest` |
| 3.11 | Review AI-suggested scores | Signal preliminary scores reviewed in signal review | Complete (2026-11-08) | I | I | I | Prompt `assessment-v1` | A | G22 | Done (R2.4): queued AI score suggestion per included force with rationale and cited signals; accept copies into the consensus, reject discards | P2 | `ReviewSignalRequest` `preliminary_*` |
| 3.12 | Approval of the two | None | Complete (2026-10-31) | I | I | I | — | A | G09 | Done (R0.4): approver and time recorded (G4) | P0 | — |

## Step 4 — Scenario Framework

| # | Required capability | Existing feature | Status | FE | BE | Data | AI | Align | Gap | Recommendation | Pri | Evidence |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| 4.1 | Axes from the two critical uncertainties | Axes from two approved forces | Complete (2026-10-31) | I | I | I | — | A | G09 | Done (R0.5): framework axes are the two approved critical uncertainties; legacy force axes kept | P0 | `scenarios.axis_*_force_id` |
| 4.2 | Name each dimension | Force keyword shown as axis name | Complete (2026-10-31) | I | I | I | — | A | G09 | Done (R0.5): axis name is the uncertainty name | P0 | `ScenarioController::present` `axis()` |
| 4.3 | Two opposing poles per axis | Four pole labels | Complete | I | I | I | — | A | — | Poles live on the critical uncertainty (R0.4); the framework reuses them in R0.5 | P0 | `axis_*_low_label`/`high_label` |
| 4.4 | Generate four quadrants | Positions HH/HL/LH/LL, created only on generation | Complete (2026-10-31) | I | I | I | LLM writes all four | A | G11 | Done (R0.5): four quadrants created on save; generation fills them in place | P0 | `ScenarioNarrativeService::generate` (`quadrants()->delete()` then create) |
| 4.5 | Rename quadrants | Editable `title` | Complete | I | I | I | LLM suggests title | A | — | Keep | — | `ScenarioController::updateQuadrant`; `QuadrantCard.jsx` |
| 4.6 | Quadrant description / assumptions | None (narrative only) | Complete (2026-10-31) | I | I | I | — | A | G11 | Done (R0.5): one-line `summary`; assumptions become step 5 conditions (R1.5) | P0 | `scenario_quadrants` columns |
| 4.7 | Validate dimensions and poles | Non-empty labels; axes differ and approved | Complete (2026-11-11) | I | I | I | Prompt `framework-v2` critique | A | G11, G22 | Done: `DifferentPole` rule (the two poles of an axis differ ignoring case and spaces) on critical uncertainties, framework poles and the legacy Scenarios page; step 4 warns when one pole is used on both axes; `framework-v2` adds an AI critique of the current poles (not opposite, different variable, value-laden, vague, implausible, axes overlap) shown with the suggestion as advice | P2 | `App\Rules\DifferentPole`; `ScenarioFrameworkService::poleChecks`; `ScenarioFrameworkTest` |
| 4.8 | AI pole and name suggestions | None for poles | Complete (2026-11-09) | I | I | I | Prompt `framework-v1`, `framework-v2` since 2026-11-11 | A | G22 | Done (R2.5): queued AI pole labels and quadrant names for a draft framework; applied as a whole or discarded | P2 | — |
| 4.9 | Preserve link uncertainties → scenarios | FK to forces; cascade on hard delete | Complete (2026-11-01) | I | I | I | — | A | G09, G16 | Done (R0.5, R1.10): restricted uncertainty FKs, force deletes refused for approved frameworks, stale flags | P1 | `cascadeOnDelete()` on axis FKs |
| 4.10 | Framework approval | `status` FINAL in API only | Complete (2026-10-31) | I | I | I | — | A | G11 | Done (R0.5): gate G5 with approver/time, locks poles and names, reopen | P0 | 0 `FINAL` in `Scenarios.jsx`; `ScenarioRequest` `status` |

## Step 5 — Develop Scenarios

| # | Required capability | Existing feature | Status | FE | BE | Data | AI | Align | Gap | Recommendation | Pri | Evidence |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| 5.1 | Conditions per scenario | None | Complete (2026-10-31) | I | I | I | — | A | G12 | Done (R1.5): ordered conditions per quadrant | P1 | — |
| 5.2 | Implications of the poles | Inside narrative text | Complete (2026-10-31) | I | I | I | Narrative | A | G12 | Done (R1.5): pole-expression conditions tagged with their axis | P1 | `ScenarioNarrativePrompt::SYSTEM` |
| 5.3 | How other forces behave per scenario | None; prompt sees only axis forces | Complete (2026-10-31) | I | I | I | — | A | G12, G13 | Done (R1.5): force-behaviour conditions link an included non-axis force | P1 | `ScenarioService::evidenceFor` |
| 5.4 | Assumptions / predetermined elements | None | Complete (2026-10-31) | I | I | I | — | A | G12 | Done (R1.5): predetermined forces shown on every quadrant; shared conditions flagged | P1 | — |
| 5.5 | Differences between scenarios | Prompt instruction only | Complete (2026-11-09) | I | I | — | Prompt line | A | G23 | Done (R2.5 + R2.8): rule-based distinctness check, plus the comparison page puts the four futures side by side | P2 | `ScenarioNarrativePrompt::SYSTEM` |
| 5.6 | Consistency review | None | Complete (2026-11-09) | I | I | I | — | A | G12 | Done (R2.8): rule checks per quadrant plus a coordinator sign-off with note, cleared when an accepted condition changes | P2 | — |
| 5.7 | Revise AI drafts | Quadrant edit (narrative only) | Complete (2026-11-08) | I | I | I | Prompt `conditions-v1` | A | G12 | Done (R2.5): queued AI condition drafts stored as proposed conditions, accepted or discarded one by one; only accepted ones count for step 5 | P1 | `QuadrantCard.jsx` |

## Step 6 — Narratives Imagination

| # | Required capability | Existing feature | Status | FE | BE | Data | AI | Align | Gap | Recommendation | Pri | Evidence |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| 6.1 | Generate narratives via existing LLM | `GenerateScenarioNarrative` + `scenario-v2` | Complete | I | I | I | Structured JSON, any configured provider | A | — | Keep | — | `ScenarioNarrativeService`; `Llm/ScenarioTest` (14 tests) |
| 6.2 | How the future unfolds / sequence | 120–200 word narrative; no sequence requirement | Complete (2026-10-31) | I | I | I | Prompt | A | G13 | Done (R1.6): prompt v2 asks for the path from today to the target year | P1 | `ScenarioNarrativePrompt::SYSTEM` |
| 6.3 | Grounded in conditions | Grounded in axes + axis signals only | Complete (2026-10-31) | I | I | I | — | A | G12, G13 | Done (R1.6): conditions and predetermined elements in the prompt; condition-force signals as evidence | P1 | `ScenarioNarrativePrompt::request` |
| 6.4 | Reference signals | `[Sn]` citations, enum-constrained, server-checked | Complete | I | I | I | Yes | A | — | Keep | — | `ScenarioNarrativeService::validated` |
| 6.5 | No invented facts | Prompt rule + citation rejection + verified quotes | Complete (bounded) | I | I | I | Yes | A | — | Keep; statement typing in 6.7 | — | `EvidenceVerifier`; `test_a_quadrant_without_valid_citation_rejects_the_whole_result` |
| 6.6 | Edit and validate | Edit + "edited" badge + overwrite guard | Complete (2026-10-31) | I | I | I | — | A | G14 | Done (R1.6): review status draft / reviewed / approved (G6) with reviewer | P1 | `updateQuadrant`; `test_edited_quadrants_are_not_overwritten_without_confirmation` |
| 6.7 | Distinguish fact / analysis / assumption / AI interpretation | Hedging words only | Complete (2026-11-09) | I | I | I | Prompt `scenario-v3` `statements[]` | A | G13 | Done (R2.7): typed statements with refs, inline markers and legend; citations are rows queryable by signal | P2 | — |
| 6.8 | Provenance | provider, model, prompt_version, input_signal_ids, generated_at | Complete | I | I | I | — | A | — | Keep | — | `scenarios` columns; `QuadrantCard.jsx:21-30` |
| 6.9 | Async, retry, failure handling | Unique job, tries=1, status polling, stale window, template fallback | Complete | I | I | I | — | A | G24 | Done 2026-10-30: precondition uses the resolved client | P3 | `GenerateScenarioNarrative`; `ScenarioNarrativeService::assertGeneratable` |

## Step 7 — Challenges & Opportunities

| # | Required capability | Existing feature | Status | FE | BE | Data | AI | Align | Gap | Recommendation | Pri | Evidence |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| 7.1 | Opportunities per scenario | `implications` text (mixed) | Complete (2026-10-31) | I | I | I | Narrative prompt | A | G15 | Done (R1.7): opportunity items per quadrant | P1 | `scenario_quadrants.implications` |
| 7.2 | Challenges per scenario | Same field | Complete (2026-10-31) | I | I | I | Same | A | G15 | Done (R1.7): challenge items per quadrant | P1 | same |
| 7.3 | Impact on organization | Implied in text | Complete (2026-10-31) | I | I | I | — | A | G15 | Done (R1.7): description of the impact and impact level | P1 | — |
| 7.4 | Link to forces | None | Complete (2026-10-31) | I | I | I | — | A | G15 | Done (R1.7): links to included exercise forces | P1 | — |
| 7.5 | Link to assumptions / conditions | None | Complete (2026-10-31) | I | I | I | — | A | G15 | Done (R1.7): links to conditions of the same quadrant | P1 | — |
| 7.6 | Review and prioritize | Edit only | Complete (2026-10-31) | I | I | I | — | A | G15 | Done (R1.7): priority 1–5; imported text starts as a proposal | P1 | — |
| 7.7 | Avoid generic items | None | Complete (2026-11-09) | I | I | I | Prompt `implications-v1` (drafts) | A | G15 | Done (R2.6, rule-based by decision): titles in all four quadrants or overlapping (≥60% words) an item of another quadrant are flagged "Generik"; AI drafts must cite a condition of their quadrant | P2 | — |

## Step 8 — Strategic Response

| # | Required capability | Existing feature | Status | FE | BE | Data | AI | Align | Gap | Recommendation | Pri | Evidence |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| 8.1 | Create strategic responses | None at scenario level | Complete (2026-11-01) | I | I | I | — | A | G17 | Done (R1.8): responses per exercise | P1 | — |
| 8.2 | Link to opportunities / challenges | None | Complete (2026-11-01) | I | I | I | — | A | G17 | Done (R1.8): links to step 7 items | P1 | — |
| 8.3 | Rationale | None | Complete (2026-11-01) | I | I | I | — | A | G17 | Done (R1.8): rationale, required for approval | P1 | — |
| 8.4 | Priorities | None | Complete (2026-11-01) | I | I | I | — | A | G17 | Done (R1.8): priority 1–5 | P1 | — |
| 8.5 | Actions / initiatives | Force-level `action_reasons` (different concept) | Complete (2026-11-01) | I | I | I | — | A | G17 | Done (R1.8): initiatives on the response; Status of Action not reused | P1 | `StatusActionController`, `status_action.json` |
| 8.6 | Evaluate across scenarios | None | Complete (2026-11-01) | I | I | I | — | A | G17 | Done (R1.8): fit per quadrant | P1 | — |
| 8.7 | Identify robust (multi-scenario) strategies | None | Complete (2026-11-01) | I | I | I | — | A | G17 | Done (R1.8): robustness derived and used for ranking | P1 | — |
| 8.8 | Assumption dependency | None | Complete (2026-11-03) | I | I | I | — | A | G17 | Done (R2.9): responses link step 5 conditions; a changed or deleted condition flags them; free-text `depends_on` kept | P2 | — |
| 8.9 | AI suggestions | None | Complete (2026-11-09) | I | I | I | Prompt `response-v1` | A | G22 | Done (R2.6): queued AI responses citing accepted implications with fit per quadrant, proposed until approved through G7 | P2 | — |
| 8.10 | Review / approve; AI ≠ decision | None | Complete (2026-11-01) | I | I | I | — | A | G17 | Done (R1.8): proposed / approved / rejected with approver (G7) | P1 | — |

## Step 9 — Early Warning Signal

| # | Required capability | Existing feature | Status | FE | BE | Data | AI | Align | Gap | Recommendation | Pri | Evidence |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| 9.1 | Indicators per scenario | `early_indicators` JSON strings | Complete (2026-11-01) | I | I | I | Narrative prompt | A | G18 | Done (R1.9): register per exercise with expected direction per quadrant | P1 | `scenario_quadrants.early_indicators` |
| 9.2 | Link to driving forces | None | Complete (2026-11-01) | I | I | I | — | A | G18 | Done (R1.9): link to an exercise critical uncertainty or force | P1 | — |
| 9.3 | What to monitor (description) | String text | Complete (2026-11-01) | I | I | I | — | A | G18 | Done (R1.9): description | P1 | — |
| 9.4 | Measurable metric | None | Complete (2026-11-01) | I | I | I | — | A | G18 | Done (R1.9): metric and unit, optional for qualitative indicators | P1 | — |
| 9.5 | Baseline | None | Complete (2026-11-01) | I | I | I | — | A | G18 | Done (R1.9): baseline value and date | P1 | — |
| 9.6 | Monitoring frequency, owner | None | Complete (2026-11-01) | I | I | I | — | A | G18 | Done (R1.9): frequency and owner | P1 | — |
| 9.7 | Threshold / attention condition | None | Complete (2026-11-01) | I | I | I | — | A | G18 | Done (R1.9): threshold rule text | P1 | — |
| 9.8 | Observations over time | None (signals and rating history are indirect) | Complete (2026-11-08) | I | I | I | — | A | G19 | Done (R2.3): `indicator_observations` with date, value or text, direction, threshold flag and note, confirmed by the recorder | P2 | — |
| 9.9 | New evidence vs scenario assumptions | Signals ingest continuously; no link | Complete (2026-11-09) | I | I | I | Prompt `signal-match-v1` | A | G19 | Done (R2.3 + R2.6): an observation can cite an accepted signal; AI matches new signals to indicators as observations confirmed by a person | P2 | `signal_driving_force` only |
| 9.10 | Trigger exercise update | None | Complete (2026-11-08) | I | I | I | — | A | G16, G19 | Done (R2.3): a threshold-crossing observation flags the framework and its scenarios for review (overview banner, "keep") | P2 | — |
| 9.11 | Which scenario is becoming relevant | None | Complete (2026-11-08) | I | I | I | — | A | G19 | Done (R2.3): step 9 "Skenario yang menguat" counts directional observations matching or opposing each quadrant and marks a unique leader | P2 | — |
| 9.12 | Manual vs automated source per indicator | None | Complete (2026-11-01) | I | I | I | — | A | G18 | Done (R1.9): source type manual / signal feed / external | P1 | — |

## Cross-Cutting

| # | Required capability | Existing feature | Status | FE | BE | Data | AI | Align | Gap | Recommendation | Pri | Evidence |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| X.1 | Methodology navigation / progress | Flat legacy sidebar | Complete (2026-11-02) | I | I | I | — | A | G20 | Done (R1.11): stepper on every step page, overview with next action and last gate, grouped sidebar | P1 | `AuthenticatedLayout.jsx:54-68` |
| X.2 | Upstream change flags | None | Complete (2026-11-01) | I | I | I | — | A | G16 | Done (R1.10): stale flag + reason on framework and quadrants, keep action | P1 | — |
| X.3 | AI vs human provenance | Signals, quadrants | Complete (2026-11-02) | I | I | I | — | A | — | Done (R0.6): origin + review state on every exercise table, one shared badge, documented convention | P0 (for new entities) | `signals.review_status`, `scenario_quadrants.is_ai_generated` |
| X.4 | Participants, comments, consensus | Roles, invitations, PIC | Complete (2026-11-08) | I | I | I | — | A | G21 | Done (R2.2): exercise participants with roles, comment threads on forces, uncertainties, quadrants and responses; consensus via R2.1 | P2 | `TenantMemberController`; `DrivingForcePolicy` |
| X.5 | Revision history | Ratings only | Complete (2026-11-07) | I | I | I | — | A | G21 | Done (R2.2): `project_decisions` logs every G1–G7 approval, rejection and withdrawal with actor; overview lists them. Rating history unchanged | P2 | `DrivingForceRatingObserver` |
| X.6 | Scenario comparison | None | Complete (2026-11-09) | I | I | I | — | A | G23 | Done (R2.8): `/scenario-planning/{project}/compare`, four columns of summary, conditions, implications, response fit and indicator directions | P2 | — |
| X.7 | Existing LLM gateway | Anthropic + OpenAI-compatible (OpenAI, Gemini, OpenRouter) | Complete | I | I | I | — | A | G24 | Reuse. Done 2026-10-30: organization key and token quota wired | P3 | `LlmClientResolver::createClient` |

---

## Summary Counts (capabilities above, 94 rows; updated 2026-11-11 after row 4.7)

| Status | Count |
|---|---|
| Complete | 94 |
| Partial | 0 |
| Missing | 0 |
| Misaligned | 0 |

"Complete" includes two rows qualified in the table: 2.1 (complete as CRUD, not yet exercise-scoped) and 6.5 (bounded by citation rules). No row is misaligned any more: 2.7 became Complete with R1.3 and 8.5 with R1.8.

| Step | Complete | Partial | Missing | Misaligned |
|---|---|---|---|---|
| 1 | 8 | 0 | 0 | 0 |
| 2 | 12 | 0 | 0 | 0 |
| 3 | 12 | 0 | 0 | 0 |
| 4 | 10 | 0 | 0 | 0 |
| 5 | 7 | 0 | 0 | 0 |
| 6 | 9 | 0 | 0 | 0 |
| 7 | 7 | 0 | 0 | 0 |
| 8 | 10 | 0 | 0 | 0 |
| 9 | 12 | 0 | 0 | 0 |
| Cross-cutting | 7 | 0 | 0 | 0 |
