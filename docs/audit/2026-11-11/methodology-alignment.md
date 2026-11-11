# Methodology Alignment — the Nine Steps (2026-11-11)

Baseline: the nine-step scenario planning method (focal issue → driving forces → critical uncertainties → framework → conditions → narratives → challenges & opportunities → strategic responses → early warning). The four product capabilities (environmental intelligence, strategic foresight, decision support, continuous monitoring) group these steps; they do not replace them. Methodology source: `docs/product/SCENARIO_PLANNING_METHODOLOGY.md` and the Era Jaya deck (local, not in git).

An **exercise** is `App\Models\ScenarioPlanningProject` (a Key Focal Issue). Every step's records carry `project_id` directly or through their parent, and every table is tenant-scoped.

## Step by step

| Step | Entities (link to previous) | Gate | Stale propagation in | Assessment |
|---|---|---|---|---|
| 1 Key Focal Issue | `scenario_planning_projects` (focal question, purpose, target year, scope, owner) | G1 confirm | — | Aligned. Changing the focal issue withdraws G1–G4 and flags the framework (`ScenarioPlanningProjectService` :174-189). AI critique of the question is a proposal (R3.1). |
| 2 Driving Forces | `project_driving_forces` (project, library `driving_force_id`, inclusion, predetermined); forces carry dimension (per organisation since 2026-11-11) and signal links | G2 | — | Aligned. Forces and signals are distinct: signals are evidence linked to forces (`signal_driving_force`, `source_signal`). Coverage per dimension shown. |
| 3 Critical Uncertainties | `force_assessments` (consensus, individual, AI suggestion); `critical_uncertainties` + `critical_uncertainty_forces` | G3 scores, G4 two approved axes | Score change on an axis force flags the framework (`markIfAxisForce`) | Aligned. Selection is a human decision with rationale; scores only inform it; independence warning (cross-impact) needs acknowledgement. Individual scores and spread kept. |
| 4 Scenario Framework | `scenarios` with `project_id`, `axis_a/b_uncertainty_id`, pole labels; `scenario_quadrants` | G5 | Axis replaced → framework; pole change → quadrants with a narrative | Aligned. Pole rule checks and AI pole critique (2026-11-11). Reopen supported. No framework versioning beyond the decision log. |
| 5 Conditions | `scenario_conditions` (quadrant, optional project force) | — (needs G5) | Condition change → quadrant narrative and linked responses | Aligned. Distinctness check, consistency review (R2.8). |
| 6 Narratives | `scenario_quadrants.narrative`, statements, citations (`scenario_citations`), review status | G6 all four approved | Withdrawn cited signal → citing quadrants | **Aligned since U3.** Content model, citation validation and G6 were sound; since U3 the work happens on the exercise's Step 6 page with the exercise's rights and a revision history. |
| 7 Challenges & Opportunities | `scenario_implications` (quadrant) + `implication_links` (forces, conditions) | — (needs G5) | Framework/axis change, G5 withdrawal, narrative regeneration (U6) | **Aligned since U6.** Linked to the analysis and flagged for review when upstream work changes. |
| 8 Strategic Responses | `strategic_responses` + `response_implications`, `response_conditions`, per-quadrant fit (`response_scenarios`) | G7 (rationale + four fits) | Condition, implication, framework change, threshold crossing | **Aligned since U6.** Cross-scenario fit and robustness; G7 requires G6; upstream changes flag responses. |
| 9 Early Warning | `early_warning_indicators` (uncertainty, force, expected direction per quadrant) + `indicator_observations` (manual, AI-matched, World Bank) | — (needs G5) | A server-decided crossing (since U4) flags the framework, scenarios and strategic responses, with an event per crossing | **Partial.** Registry, baseline, unit, frequency, owner, external sources, observation review and server-side thresholds (U4) exist; relevance is explained and windowed since U5. |
| Beyond 9 | Preferred future and backcasting milestones (R4.1); report; comparison | — | — | Aligned with the deck's closing slides. |

## Revalidation of previous findings

| Finding | Status | Evidence | Next |
|---|---|---|---|
| A — Step 6 Narrative Studio | **NOT RESOLVED** (routing and ownership); content model sound | `ScenarioPlanningProjectService.php:29`, `:213`, `:276`; `Framework.jsx:121`; `Show.jsx:118`; `ScenarioPolicy.php:13-17` | U3 |
| B — EWS threshold evaluation | **NOT RESOLVED** | `IndicatorObservationController.php:34`, `:48`; `ExternalIndicatorService.php:99`; `SignalMatchService.php:110` | U4 |
| C — Scenario relevance | **PARTIALLY RESOLVED** (no probability wording; still a raw count) | `EarlyWarningIndicatorService.php:187-221`; `RelevancePanel.jsx` | U5 |
| D — Harness documentation | **RESOLVED in this unit (U1)** for git tracking, AGENTS.md accuracy, PROGRESS header, `last_updated`; typecheck/lint gap remains | `.gitignore`; AGENTS.md; `feature_list.json` | U1 done; AUD-18 backlog |
| E — End-to-end integration | **PARTIALLY RESOLVED**: links between steps exist; feedback loops into 7–9 missing; no Step 1→9 test | `StalenessService.php:22`; test inventory | U6, U7 |

## Stale propagation map

Existing (keep):
- Focal issue change → withdraw G1–G4, flag framework.
- Axis-force score change, force merge → flag framework.
- Axis uncertainty replaced → flag framework and quadrants.
- Pole change → flag quadrants with a narrative.
- Condition change → flag quadrant narrative and linked responses.
- Cited signal withdrawn → flag citing quadrants.
- User-ticked threshold crossing → flag framework.

Missing (U4, U6):
- Axis / uncertainty change or framework reopen → implications, responses, indicators of the exercise.
- Implication change → responses linked to it.
- Narrative regenerated or edited → implications of that quadrant.
- Server-computed threshold crossing → framework and approved responses, with an event record.

Principles kept: nothing is deleted; every flag carries a reason; a change that does not affect downstream meaning (e.g. renaming a quadrant title) does not cascade.

## Workflow and UX notes

- The exercise page lists the steps with status and gates; each step links to its scoped page except Step 6 today.
- Pending approvals are visible per step (gates); stale items show banners with "reviewed, keep" actions.
- Evidence: signals carry verbatim quotes checked against the source; narratives cite signals; conditions, implications and responses link to forces and conditions.
- The firm-level reports (Dashboard, Radar, Prioritizing, Registered List) can now be scoped to one exercise; Overview month trends remain firm-only by design.
