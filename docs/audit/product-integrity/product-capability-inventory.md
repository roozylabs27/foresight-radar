# Product Capability Inventory — Foresight Radar

Product capability and feature integrity audit, 2026-11-13. Code baseline: `main` `46e22d4` = `development` `82a26a6` (identical trees; clean working tree). This is an audit only: no application code was changed.

**Verification legend**
- **S:** source inspection (file and line).
- **T:** automated tests exist (named; not re-run in this audit). The full suite passed on 2026-11-12: 628 tests.
- **B:** browser evidence from earlier sessions: production E2E of steps 1→9 (2026-11-12) and restored-copy page sweeps (2026-11-13).
- **N:** not verified.

**Statuses:** Implemented / Partial / Disconnected / Redundant / Missing / Unverified. **Confidence:** High = several independent forms of evidence; Medium = source plus tests; Low = implementation only. `feature_list.json` marks all 69 features "passing". That means their tests pass; it is not this classification.

Detailed findings: [product-feature-integrity-report.md](product-feature-integrity-report.md). Workflows: [product-workflow-traceability.md](product-workflow-traceability.md). Methodology: [product-methodology-integrity.md](product-methodology-integrity.md). Roadmap: [product-gap-roadmap.md](product-gap-roadmap.md).

## Personas

| Persona | Who | Main permissions |
|---|---|---|
| Analyst | collects and curates evidence and forces | `view-signal`, `review-signal`, driving force CRUD |
| Facilitator / coordinator | runs a scenario planning exercise | `manage-scenario` + project policy (creator, owner or admin) |
| Participant (contributor/expert) | scores and comments inside an exercise | `view-scenario` + participant role |
| Executive / BOD | reads reports and approves firm-library items | `view-dashboard`, report views, firm Approval |
| Organisation admin | members, roles, dimensions, AI key | `manage-organization`, `manage-members` |
| Platform operator | developer / super-admin across workspaces | platform roles, workspace switch |

## A. Environmental intelligence (firm library)

| ID | Capability | Persona | Entry point | Input → processing → output | Downstream consumer | Business value | Verification | Status / Conf. | Rationale |
|---|---|---|---|---|---|---|---|---|---|
| A-01 | Source ingestion (pasted text) | Analyst | "Signal Ingestion" `signals.ingest` | title, text 30–60k chars, URL as metadata → `SignalExtractionService::ingest` (SignalExtractionService.php:115) → `sources` row, `ExtractSignals` queued | A-02 | evidence base | S, T (SignalIngestionTest, LlmSignalExtractionTest) | Partial / High | URL never fetched; `content_hash` indexed but not used to detect re-ingestion |
| A-02 | AI signal extraction | System | job `ExtractSignals` | source text, active dimensions, approved forces → `LlmSignalExtractor`, `::store` :263 in one transaction → PENDING signals with dimension, horizon, scores, suggested force, run id | A-05 review | turns documents into candidate signals | S, T (LlmSignalExtraction 13, Recovery, Benchmark); B: AI unavailable on prod 2026-11-12, failure handled | Implemented / High | Atomic claim, stale recovery, provenance; live AI output never verified on production |
| A-03 | Verbatim quote verification | System | in extraction and review | quote vs source → `EvidenceVerifier::verify` (EvidenceVerifier.php:19) | review gate (accept blocked when unverified) | evidence integrity | S, T (EvidenceVerifierTest) | Implemented / High | strongest evidence control in the product |
| A-04 | Retry failed or lost extraction | Analyst | `signals.extraction.retry` | source → `SignalExtractionService::retry` :142 (releases unique lock) | A-02 | recovery | S, T (SignalExtractionRecoveryTest); B (REL-04 on prod) | Implemented / High | — |
| A-05 | Signal review (reject / link to force / accept as new force / edit) | Analyst | Signals page `signals.review` | decision → `SignalIngestionController::review` :318, `forceFromSignal` :254 → ACCEPTED/REJECTED, `signal_driving_force`, new PENDING force | A-08 library, A-14 graph, AI-11 matching, step 2 candidates | curation | S, T (SignalIngestionTest, ForceAggregationTest) | Implemented / High | irreversible (no reopen or unlink, 409); domain logic in the controller |
| A-06 | Signal classification (likely/could/might, wild card) | Analyst | `signals.classify` | → `classified_by/at` | step 9 sensing panel (EarlyWarningIndicatorService:96) | weak-signal framing | S, T (SignalClassificationTest) | Implemented / High | second horizon field next to `suggested_time_horizon_id`; labels unclear |
| A-07 | Signal citations lookup | Analyst | `signals.citations` | → quadrants citing a signal | Signals UI | traceability back from evidence | S, T (Llm/ScenarioTest) | Implemented / Medium | — |
| A-08 | Driving force register (CRUD) | Analyst | "Driving Force" `driving-force.` | keyword, description, dimension, PIC → PENDING force | A-09, A-21, reports | firm library | S, T (DrivingForceTest); B (e2e spec) | Implemented / High | success copy says "signal" (DrivingForceController:94,124,160) |
| A-09 | Six-stage force lifecycle (horizon → rating → status action → approval → closed) | Analyst / BOD | Governance: 5 sidebar pages | → rating, priority, status | FIRM reports, exercise copy | firm prioritisation and governance | S, T (RatingUrgency, ApprovalValidation, ClosedItemsReopen, ForesightLifecycle) | Partial / High | works but spread over 5 pages and 6 controllers; no approver/closer recorded; no transition history |
| A-10 | Rating history analytics | Exec | Dashboard history charts | observer → `driving_force_rating_histories` → `RatingHistoryAnalyticsService` | Dashboard | trend of the firm view | S, T (RatingHistoryAnalyticsTest) | Partial / Medium | aggregate only, no per-force history view |
| A-11 | Merge duplicate forces | Analyst | `driving-force.merge` | → moves signals, cross-impacts and exercise links; tombstone; flags exercises stale | exercises | library hygiene | S, T (ForceAggregationTest) | Implemented / High | the only firm change that reaches exercises |
| A-12 | Per-organisation dimensions and environments | Org admin | Settings → Dimensi & Lingkungan | → tenant taxonomy, stable codes; in-use dimensions archived | extraction prompt, review, every report | own taxonomy | S, T (DimensionSettings, TenantDimensions, DimensionMigrationSafety) | Implemented / High | new-force fallback `Dimension::first()` ignores archiving (SignalIngestionController:401) |
| A-13 | Cross-impact matrix and causal loops | Analyst | "Cross-Impact" | pair score −3..3 → `CrossImpactService`, `CausalLoopService` | Radar overlay, simulation propagation, exercise G4 independence | systemic view | S, T (CrossImpactTest, CausalLoopServiceTest) | Implemented / High | FIRM only, not labelled; an empty matrix reads as "independent" in G4 |
| A-14 | Knowledge graph | Analyst | "Knowledge Graph" | → all sources, signals and forces, every status, unpaginated | none (view only) | exploration | S, T (KnowledgeGraphTest) | Partial / Medium | no scope or status filter; borrowed permission; 959-line page |

## B. Strategic foresight (nine-step exercise)

| ID | Capability | Persona | Entry point | Input → output | Downstream consumer | Verification | Status / Conf. | Rationale |
|---|---|---|---|---|---|---|---|---|
| B-01 | Exercise workspace (create, overview, stepper, next action, stale banner, `ahead` label) | Facilitator | "Scenario Planning" | focal fields → project; `steps()`, `nextAction()` | every step | S, T (ProjectTest, WorkspaceTest); B (prod E2E-01..17) | Implemented / High | strongest guidance in the product |
| B-02 | Step 1 focal issue + G1 | Facilitator | overview | question, purpose, target year → G1 | step 2; AI-02 | S, T, B | Partial / High | `status` accepted by PUT update bypasses lifecycle rules (PI-01); framework keeps a stale copy of the question |
| B-03 | Step 2 driving forces + G2 | Facilitator | step 2 | library forces / AI candidates → included list, G2 | step 3 | S, T, B | Partial / High | library offers forces in any status (PI-06); predetermined toggle not propagated |
| B-04 | Step 3 scoring (consensus + individual) + G3 | Facilitator, participants | step 3 | scores 1–10 → consensus, spread | step 3b | S, T (ProjectAssessment 15, ForceAggregation), B | Partial / High | G3 passes on library-copied scores with no exercise deliberation (PI-02) |
| B-05 | Critical uncertainties + G4 | Facilitator | step 3b | members, poles, rationale → 2 approved axes | step 4 | S, T (CriticalUncertainty 11), B | Partial / High | no low-score warning; empty cross-impact = "independent" |
| B-06 | Step 4 framework + G5 | Facilitator | step 4 | poles, names → 4 quadrants | steps 5–9 | S, T (ScenarioFramework 17), B | Implemented / Medium | default names accepted; pole checks advisory |
| B-07 | Step 5 conditions | Facilitator | step 5 | conditions per quadrant, predetermined elements, overlap check | narratives, implications, responses | S, T (ScenarioCondition 11), B | Partial / High | not required before narratives; condition change does not flag linked implications |
| B-08 | Step 6 narratives + citations + revisions + G6 | Facilitator | step 6 (narrative studio) | conditions, signals → narrative, typed statements, citations, revisions | step 7 (seed text), step 9 hints, report | S, T (ScenarioNarrativeReview 12, Llm/ScenarioTest), B | Partial / High | statement trace dropped on any edit; approval ignores stale; G6 survives G5 withdrawal (PI-03, PI-05) |
| B-09 | Step 7 challenges & opportunities | Facilitator | step 7 | quadrant, optional force/condition links → items | step 8 | S, T (ScenarioImplication 9), B | Partial / High | no link to narrative statements; PRD-required fields optional |
| B-10 | Step 8 strategic responses, fit, robustness + G7 | Facilitator | step 8 | implications, conditions, fit ×4 → response | backcasting, report | S, T (StrategicResponse 22), B | Partial / High | G7 survives upstream withdrawal; no implication link required; no execution fields (PI-11) |
| B-11 | Step 9 indicators, thresholds, observations, relevance | Facilitator, participants | step 9 | indicator + directions + threshold; observations → events, relevance | staleness, monitoring | S, T (EarlyWarningIndicator 24, ExternalIndicatorSource 6), B | Partial / High | one non-discriminating indicator completes step 9; relevance counts proposed indicators (PI-09) |
| B-12 | Staleness propagation | System | banners per step | upstream change → flags with reasons | steps 4–9 | S, T (StalenessTest 7), B (E2E-15) | Implemented / High | step status never shows "stale"; next action ignores it |
| B-13 | Gate decision log | System | overview DecisionLog | observer → `project_decisions` | report, overview | S, T (StrategicResponseTest), B (G1–G7 logged once) | Partial / High | no rationale, no artefact id; status changes not logged |
| B-14 | Participants, comments, multi-assessor | Facilitator, participants | overview, step pages | roles, comments, own scores | step 3, artefacts | S, T (ProjectParticipant 4, comments in StrategicResponseTest) | Partial / High | coordinator/contributor/expert identical; comments hard-deleted; no notifications |
| B-15 | Exercise lifecycle (active / monitoring / archived) | Facilitator | overview buttons | `changeStatus` | index badge | S, T, local B | Partial / High | rules bypassable through PUT update; archived still editable |
| B-16 | Legacy stand-alone scenarios `/scenarios` | — | URL only | two firm forces → project-less scenario + AI narratives | signal citations | S, T (Llm/ScenarioTest, e2e scenarios spec) | Redundant / High | second creation path outside the gates, still writable (PI-08) |

## C. Decision support

| ID | Capability | Entry | Output → consumer | Verification | Status / Conf. | Rationale |
|---|---|---|---|---|---|---|
| C-01 | Dashboard overview (resilience/growth indices, EDA, top forces) | "Dashboard" | rule-based `ReasoningEngineService` → exec view | S, T (ReasoningEngineTest, ReportScopeTest); B (restored copy 200) | Implemented / High | "reasoning" is rules, not AI |
| C-02 | What-if simulation | Dashboard tab | `ScenarioSimulationService` (scope-aware) → not saved | S, T (2 tests) | Disconnected / High | not reachable from the exercise; results never kept |
| C-03 | Foresight Radar | "Foresight Radar" | radar dataset, FIRM or exercise scope | S, T, B (page 200) | Implemented / High | duplicated as a Dashboard tab |
| C-04 | Prioritizing matrix | "Prioritizing" | matrix, scoped | S, T | Redundant / High | duplicated as a Dashboard tab |
| C-05 | Registered List + xlsx export | "Registered List" | table, client-built xlsx | S, T (ExportStreamingTest) | Redundant / High | duplicated as a Dashboard tab; the only export in the product |
| C-06 | Report scope FIRM ↔ Key Focal Issue | `?scope=` on 4 reports + Dashboard | scoped datasets | S, T (ReportScopeTest) | Partial / High | not on Cross-Impact or Knowledge Graph; exercises never link to their scoped reports; period filter on rating `created_at` |
| C-07 | Scenario comparison + consistency sign-off | exercise "Bandingkan" | agreed content side by side | S, T (ScenarioComparison 6), B (restored copy 200) | Implemented / High | no relevance or monitoring signal shown |
| C-08 | Scenario relevance panel | step 9 | supporting/contradicting counts per quadrant, "not a probability" | S, T (4 tests) | Partial / High | only on step 9; counts proposed and non-discriminating indicators |
| C-09 | Preferred future + backcasting milestones | exercise text link | PF decision, milestones (year, owner, status, response/indicator link) | S, T (Backcasting 3) | Implemented / High (discoverability Low) | only reachable from a text link; year-grained |
| C-10 | Printable exercise report | exercise text link | agreed content, gates, decisions, backcasting | S, T (ScenarioReport 3), B (E2E-17) | Implemented / Medium | print only; no relevance, stale state or decision rationale |

## D. Continuous monitoring

| ID | Capability | Output | Verification | Status / Conf. | Rationale |
|---|---|---|---|---|---|
| D-01 | Indicator register (owner, frequency, source, threshold, directions) | indicators | S, T | Implemented / High | `frequency` and `owner` stored, never enforced |
| D-02 | Server-side thresholds and crossing events | one event per observation; review flags | S, T (+ Unit ThresholdEvaluatorTest), B (E2E-15) | Implemented / High | single rule per indicator; no acknowledgement or owner on events |
| D-03 | Manual observations | observations by managers and contributing participants | S, T, B | Implemented / High | — |
| D-04 | External source (World Bank) daily fetch | **proposed** observations | S, T (6) | Partial / High | proposals never evaluate thresholds until a person confirms, and nothing tells the person |
| D-05 | AI signal → indicator matching | proposed observations with signal | S, T | Partial / Medium | manual trigger only; `observed_at` = match date, not signal date (SignalMatchService:107) |
| D-06 | Human notification of crossings, overdue indicators, pending proposals, stale work | — | S: only `TenantInvitationNotification` exists; NotificationBell reads ingestion events from localStorage | **Missing / High** | no continuous loop without a person opening the page |
| D-07 | Monitoring cadence / overdue detection | — | S: `frequency` unused | **Missing / High** | — |

## E. AI capabilities (detail in the integrity report §AI)

| ID | Capability | Consumer and acceptance | Status / Conf. |
|---|---|---|---|
| AI-01 | Signal extraction | review queue | Implemented / High |
| AI-02 | Focal issue critique | "Gunakan" pre-fills the edit; nothing saved without a person | Implemented / High |
| AI-03 | Candidate forces from signals | accept creates a library force + exercise link | Implemented / High (duplicate risk) |
| AI-04 | Score suggestions | accept copies into consensus | Implemented / High (run link lost on accept) |
| AI-05 | Poles, quadrant names, critique | accept updates poles | Implemented / High |
| AI-06 | Narratives with typed statements and citations | edit / review / G6; revisions | Implemented / High |
| AI-07 | Condition drafts | accept / edit | Implemented / High |
| AI-08 | Implication drafts | accept / edit | Implemented / High |
| AI-09 | Response proposals with fit | approve / reject | Implemented / High |
| AI-10 | Indicator proposals (must discriminate) | accept / edit | Implemented / High |
| AI-11 | Signal-to-indicator matching | confirm → threshold evaluation | Partial / Medium |
| AI-12 | Provider gateway (personal → tenant → platform key), quota, run log | — | Partial / High (runs not visible in any UI; run id on 4 of 11 outputs) |
| AI-13 | Recovery of stuck exercise AI jobs | — | Missing / Medium (no stale handling for 9 suggestion statuses) |

## F. Platform

| ID | Capability | Verification | Status / Conf. | Rationale |
|---|---|---|---|---|
| P-01 | Authentication, RBAC (developer, super-admin, admin, bod) | S, T (Auth/*, AuthorizationTest, RoleEscalation) | Implemented / High | 7 permissions with no use (`view-role`, `create-role`, …) |
| P-02 | Organisation creation | S: only `UserSeeder` (`Tenant::firstOrCreate`) | **Missing / High** | an operator cannot provision a new organisation in the app |
| P-03 | Invitations, members, role change, last-admin guard | S, T (TenantInvitationLifecycle 16, TenantMemberAuthorization) | Implemented / High | only 2 tenant roles (admin, bod); a contributor role does not exist |
| P-04 | Operator workspace switch | S, T (OperatorWorkspace 7) | Implemented / High | — |
| P-05 | Platform user management | S, T (UserManagementTenantTest) | Redundant / Medium | overlaps Settings → Members; operators only |
| P-06 | Tenant isolation | S, T (TenantIsolation, Journeys, Security/*); B (local cross-org checks) | Implemented / High | not provable on production (one organisation) |
| P-07 | Settings: organisation, AI keys, dimensions, profile, locale | S, T | Implemented / High | sidebar English in the `id` locale |
| P-08 | Operations tooling (verify, backup, restore rehearsal, failed-jobs alert) | S, T, production run 2026-11-13 | Implemented / High | not a user-facing product capability |
| P-09 | Synthetic foresight / clustering harness | S, T (Unit/Harness) | Implemented (R&D) / Medium | developer tooling, not product |
