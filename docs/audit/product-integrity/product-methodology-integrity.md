# Methodology Integrity — the nine-step scenario planning method

Audit of 2026-11-13, `main` `46e22d4`.

The intended method comes from:
- `docs/product/SCENARIO_PLANNING_METHODOLOGY.md`
- `SCENARIO_PLANNING_TARGET_WORKFLOW.md`
- `SCENARIO_PLANNING_FEATURE_SPECIFICATION.md`
- `PRD_SCENARIO_PLANNING_PHASES_7_9.md`
- `ASSESSMENT_METHODOLOGY.md`
- `docs/methodology/scenario-relevance.md`

The code is the source of truth for what is implemented. Each row was verified by source and existing tests (S, T). Steps 1–9 were also completed in the browser on production on 2026-11-12 (B). No deviation was "fixed" by changing the method in this audit.

## Overall verdict

**The method is implemented coherently in structure and partly in substance.**
- **Structure: present.** The steps, their order, server-side gates, staleness propagation and the decision log are all in place.
- **Substance: partial.** Several gates certify an activity without requiring it. In particular:
  - consensus scoring (G3);
  - the conditions-before-narrative order;
  - discriminating indicators;
  - re-approval after upstream change.

Scenario relevance is correctly presented as a count of supporting and contradicting evidence, **not a probability** (docs/methodology/scenario-relevance.md; panel wording). No deviation of that kind was found.

## Step by step

| Step | Methodology requirement | Implementation (evidence) | Deviation | User impact | Status | Correction (smallest) |
|---|---|---|---|---|---|---|
| **1 Key focal issue** | A decision-oriented question with scope, purpose and target year guides the exercise. AI may critique; the coordinator decides. | Fields and validation (target year in the future); G1 `confirm` with a row lock; a focal edit withdraws G1–G5 and flags downstream work (ScenarioPlanningProjectService:151-211). AI critique only pre-fills the edit (FocalIssueSuggestionService; FocalSuggestion.jsx:60). | (a) The exercise status can be set through PUT update, bypassing the lifecycle rules (PI-01). (b) The framework keeps a copy of `focal_question` (ScenarioFrameworkService:35) that the narrative prompt reads and nobody re-syncs. | Narratives may answer an outdated question; the status loses meaning. | Partial / High | Remove `status` from the update request. Read the question from the project in the prompt. |
| **2 Driving forces** | Forces, distinct from signals, linked to evidence; relevant to the question; firm library as the source. | Library add and AI candidates; G2 requires G1 and ≥1 included force (ProjectDrivingForceService:31-117). Forces and signals are distinct entities; the signal count is shown. | (a) Forces in any firm status can be added (PI-06). (b) Category coverage is advisory only. (c) Toggling `is_predetermined` on an axis member propagates nothing. (d) No "why relevant to this question" field beyond an optional influence note. | A rejected or closed firm force can shape the futures. | Partial / High | Offer only APPROVED forces, or require a reason. Treat a predetermined change on an axis member as a G4 withdrawal. |
| **3 Scoring** | Impact and uncertainty scored separately, with rationale, by several assessors. Disagreement visible. Consensus agreed for this question. | Separate scores 1–10 with rationale; individual scores, median, min/max and assessor names; wide spread flagged (ProjectAssessmentService:151-173). G3 requires a consensus row for every included force. | **Library-copied scores count as consensus** (ProjectDrivingForceService:124-140; journey test reaches G3 without scoring). A wide spread does not block G3 and is not recorded. | The exercise can inherit the firm's view unexamined (PI-02). | Partial / High | G3 blocks while any consensus row has `origin=library` or an unresolved wide spread, unless an override reason is recorded. |
| **3b Critical uncertainties** | Exactly 2 axes from high-impact, high-uncertainty forces, independent of each other, chosen with a rationale, not only the top scores. | `selection_rationale` required; pair strength and independence checked against the cross-impact matrix, with an acknowledgement when it is high (CriticalUncertaintyService:75-105, 159-194). | (a) No warning when a member scores below 6/6 (spec threshold). (b) An empty cross-impact matrix gives influence 0, shown as "independent". | Weak axes can be approved; "independent" can mean "never assessed". | Partial / High | Warn below threshold. Say "no cross-impact data" instead of 0. |
| **4 Framework** | Two axes = the two uncertainties; distinct opposing poles; four logically built quadrants with meaningful names; rules and AI critique complement each other. | Axes must equal the approved CUs; poles and names locked after G5; pole checks; AI poles, names and critique as proposals (ScenarioFrameworkService:22-242; FrameworkSuggestionService). | G5 accepts the default "pole·pole" names; pole checks are advisory. | Quadrants may stay unnamed in practice. | Implemented / Medium | Require a non-default name, or an explicit "keep". |
| **5 Conditions** | ≥2 differentiated conditions per quadrant, coherent with the poles, predetermined elements shared; generic and repeated ones detected; **conditions precede the narrative**. | ≥2 per quadrant marks the step done; predetermined elements are shared; overlap and genericity checks (exact text in all 4, Jaccard) (ScenarioConditionService:154-231). | (a) Steps 5–9 open together at G5, and G6 does not require step 5. (b) A condition stores its axis but not its pole, so coherence with the pole is not checked. (c) Changing a condition does not flag the implications linked to it. | Narratives can be written and approved before any conditions exist. | Partial / High | G6 requires step 5 done. Flag linked implications. |
| **6 Narratives** | One narrative per quadrant, built from conditions, statements typed and supported by cited evidence, revisions kept, reviewed. | The prompt includes conditions and predetermined elements; typed statements; citations validated (unknown ones stripped; a quadrant with no valid citation rejects the result); revisions; G6 review rejects unedited templates (ScenarioNarrativeService). | (a) **Any edit drops the typed statements and leaves the citations unverified** (:215-218). (b) Approval ignores stale flags. (c) G6 survives framework withdrawal. (d) The template cites the same signals in all four quadrants. | Approved narratives can lose their evidence trail or be outdated (PI-03, PI-05). | Partial / High | Block approval while stale. Withdraw G6 on G5 withdrawal. Re-tag or re-verify citations on edit. |
| **7 Challenges & opportunities** | Specific to each scenario, derived from conditions and forces; opportunity vs challenge distinct; generic ones detected; impact level and priority (PRD §3.3); review after upstream change. | Per quadrant, typed opportunity/challenge; generic and similar checks; optional links to forces and conditions; AI drafts must cite conditions of the same quadrant (ScenarioImplicationService). | (a) No link to narrative statements. (b) Links to conditions are optional, so "derived from conditions" cannot be checked. (c) `impact_level` and `priority` are nullable, against the PRD. | The reasoning from scenario to implication is not recorded. | Partial / High | Require ≥1 condition link or a rationale. Enforce the PRD fields. |
| **8 Strategic responses** | Linked to implications; fit per scenario; robust vs conditional distinguished; dependencies clear; approval accountable; link to backcasting. | Fit ×4, robustness (≥3 applies = robust), links to implications and conditions, `depends_on`; G7 needs a rationale, fit ×4 and G6 (StrategicResponseService:75-99, 177-184). | (a) No implication link is required. (b) G7 survives upstream withdrawal; one approved response marks step 8 done even when stale. (c) 0 "applies" is labelled "conditional". (d) The approver is not the response owner (target workflow §4). (e) No execution fields. | Responses can be approved without the implication they answer, and can stay "approved" for an old framework. | Partial / High | Require ≥1 implication link. Withdraw or block on stale. Add a "none" robustness class. Minimal execution fields (PI-11). |
| **9 Early warning** | Indicators tied to uncertainties and forces; direction per quadrant; **discriminating**; owner and frequency; thresholds evaluated on the server; observations traceable; one event per crossing; relevance explains support and contradiction; breach notified (PRD §5.6.3); G8 thresholds confirmed. | Directions per quadrant; server-side thresholds; one event per observation, closed on delete; observations with signal or external source; relevance with a 12-month window and "not a probability" (EarlyWarningIndicatorService, IndicatorThresholdService, ThresholdEvaluator). | (a) Done with one indicator, even a non-discriminating one. (b) No owner or frequency check. (c) Relevance ignores indicator review status and discrimination. (d) No G8. (e) No breach notification. | Monitoring can start on indicators that cannot tell the futures apart, and nobody is told when one crosses (PI-04, PI-09). | Partial / High | Done = ≥1 discriminating indicator per axis with owner and frequency. Relevance counts accepted, discriminating indicators only. Notify the owner. |

## Cross-step mechanisms

| Mechanism | Requirement | Implementation | Deviation | Status |
|---|---|---|---|---|
| Gates G1–G7 | recorded decisions, accountable | `project_decisions` through an observer; row locks against duplicates (PRD-11) | no rationale (except G4 and G7 on the artefact); no artefact id; status changes not logged | Partial |
| Staleness | upstream change triggers review, never deletes | `StalenessService` with reasons, banners, acknowledgement | step status never "stale"; next action ignores it; one acknowledgement clears all; preferred future and milestones not flagged | Partial |
| Lifecycle | done exercise moves to monitoring and archive | `changeStatus` (PRD-06) | bypass through PUT (PI-01); archived not read-only | Partial |
| Collaboration | expert deliberation, dissent visible | participants, individual scores, spread, comments | roles without rights; dissent not recorded at the gate; comments hard-deleted; no notifications | Partial |

## Deviations ranked

1. **P1:** G3 on library copies (step 3).
2. **P1:** approvals survive upstream change (steps 6 and 8).
3. **P1:** step 9 completion and relevance on non-discriminating indicators; no breach notification.
4. **P1:** lifecycle bypass through PUT (step 1 and lifecycle).
5. **P1:** narrative → implication trace (steps 6 and 7).
6. **P2:** conditions do not have to precede the narrative (step 5).
7. **P2:** critical uncertainty guardrails (step 3b).
8. **P2:** implication fields and condition links (step 7).
9. **P2:** governance rationale and roles.
10. **P3:** default quadrant names, robustness "none", stale step status, focal question copy, preferred future flags.

None of these needs the method to change. Each is the code catching up with the method that is already documented.
