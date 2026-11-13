# Product Gap Roadmap & Executive Assessment — Foresight Radar

Audit of 2026-11-13, `main` `46e22d4`. Decisions taken afterwards: [product-architecture-decisions.md](product-architecture-decisions.md). It builds on the [inventory](product-capability-inventory.md), [integrity report](product-feature-integrity-report.md), [workflows](product-workflow-traceability.md) and [methodology](product-methodology-integrity.md).

**Goal:** the smallest set of changes that makes the existing capabilities purposeful, connected and reliable. **No new modules.**

Complexity: S ≤ 1 day, M ≤ 1 week, L > 1 week.

## 1. Prioritised remediation

No **P0** was found. No workflow is broken outright, and no data loss or security breach was seen. The first block below is P1 because each item lets the product present a decision as made when it was not.

### Phase 1 — Integrity of the method (P1, mostly S)

| # | Problem (finding) | Proposed resolution | Acceptance criteria | Dependencies | Size | Type |
|---|---|---|---|---|---|---|
| 1 | Lifecycle bypass through PUT update (PI-01) | Remove `status` from `ScenarioPlanningProjectRequest`; status changes only through `changeStatus` | PUT with `status` changes nothing; test: monitoring before step 9 refused through both endpoints | — | S | **Done 2026-11-13** (requirement): `status` removed from the request and the service; `ProjectTest::test_the_edit_form_cannot_change_the_exercise_status` red → green |
| 2 | G3 on library copies (PI-02) | G3 refused while a consensus row has `origin=library`, unless confirmed per force or overridden with a recorded reason. Record the unresolved wide spread at G3 | Journey test must score or confirm before G3; G3 decision carries the override reason | 13 (rationale field) | S–M | **Done 2026-11-13** (requirement, decision "confirm per force"): G3 refused while an included force still has an unreviewed library copy (`origin=library`, `updated_by` null); per-force "Pakai nilai library" (`assessment.library`) keeps it with the reviewer recorded; the G3 dialog counts wide spreads. 2 tests red → green; 18 fixtures press the button over HTTP (`Tests\Support\KeepsLibraryScores`) |
| 3 | Approvals survive upstream change (PI-03) | `withdrawFrameworkApproval` also withdraws G6 and G7; approval is refused while the artefact is stale; step status `stale` | Reopen → G6/G7 withdrawn and logged; approving a stale narrative or response gives 422 | — | M | **Done 2026-11-13** (requirement, decision "withdraw automatically"): `StalenessService::frameworkReopened` withdraws approved narratives (G6) and responses (G7) row by row (logged) and flags narratives; a stale narrative or response cannot be approved (422) until edited / kept. Step status `stale` not added. Test red → green |
| 4 | Step 9 weak completion; relevance counts weak indicators (PI-09) | Done = ≥1 accepted discriminating indicator per axis, with owner and frequency. Relevance only counts accepted, discriminating indicators. Use the signal date for AI matches | Tests for each rule; relevance unchanged for valid data | — | S | **Done 2026-11-13** (requirement): done = owned, accepted, active indicators that together tell both axes apart (`axesTold`); relevance counts accepted discriminating indicators only; AI matches dated by the source's publication date. 3 tests red → green; PRD-06 and journey tests updated |
| 5 | Legacy `/scenarios` still writable (PI-08) | **Decided (PAD-2): read-only archive.** Remove `store`, `generate` and the write routes; move the e2e spec to the exercise | `POST /scenarios` refused; existing stand-alone scenarios still viewable | — | S | **Done 2026-11-13** (consolidation): routes `store`, `update`, `suggest-axes` removed (with `ScenarioRequest`, `ScenarioService::suggestAxes/resolveAxes`); `ScenarioPolicy` refuses stand-alone scenarios; the page is a read-only archive with a link to Scenario Planning; the narrative-engine tests (19) now run on an exercise framework; e2e spec rewritten |
| 6 | Stuck exercise AI jobs (PI-10) | One stale check for the 9 `*_suggestion_status` columns using `StaleJob`, releasing the unique lock | Test mirrors `SignalExtractionRecoveryTest` (lost job → FAILED → retry works) | — | S–M | **Done 2026-11-13** (requirement): `suggestion_started_at` (JSON, migration `2026_11_13_000001`), `SuggestionRecovery` + `RecoverLostAiSuggestions` middleware (GET exercise pages), `uniqueFor` on the 9 jobs. 2 tests red → green |
| 7 | Library/exercise drift (PI-06) | Offer APPROVED forces only, or require a reason. Flag the exercise (reason) when a used firm force is edited, rejected, closed or deleted. Show the firm value next to copied scores | Tests: rejected force not offered; firm edit flags the exercise | — | M | **Done 2026-11-13** (requirement, decision "all except REJECTED/CLOSED"): the library and `add()` exclude REJECTED and CLOSED; `DrivingForceObserver` flags the exercise link (`project_driving_forces.stale_*`, migration `2026_11_13_000002`) and an axis framework when the firm force is edited, rejected, closed or deleted; saving the link clears it. Firm value next to copied scores not added. 2 tests |
| 8 | FIRM period semantics (PI-07) | **Decision (product owner, 2026-11-13): "rated in the period".** The period means "assessed during the period", not "active during the period". Implementation note: a re-rating updates the same `driving_force_ratings` row (`RatingUrgencyController::create` :63-71, `save()`), so `created_at` keeps the first rating date. A force re-rated this month but first rated more than 12 months ago still drops out. The filter must use the latest rating activity (`updated_at`, or the newest `driving_force_rating_histories` entry), and the page must state the rule. | A force re-rated inside the period appears; one last rated before it does not; Dashboard, Radar, Prioritizing and Registered List say "dinilai dalam periode"; test per case | decided | S | requirement |

### Phase 2 — Close the intelligence loop and the evidence chain (P1/P2, M)

| # | Problem | Proposed resolution | Acceptance criteria | Dependencies | Size | Type |
|---|---|---|---|---|---|---|
| 9 | Monitoring never reaches a person (PI-04) | A daily `indicators:review-due` that creates database notifications (and optional mail) for the indicator owner, falling back to the exercise owner. Triggers: a new threshold event; overdue by frequency (monthly 35 d, quarterly 100 d); proposed external or AI observations waiting, with a "would cross" hint; stale work. A server-fed notification bell | Tests per trigger; bell shows count and links; no duplicate notification for the same event | owner and frequency (#4) | M | requirement |
| 10 | Narrative → implication trace (PI-05) | Keep statement typing on edit: re-validate citations against the text, or mark them "needs re-check". Optional `statement` reference on implications. Artefact-level links (signal, force, implication, response) in the existing cards | After an edit, citations are re-verified or flagged; implication shows its source statement; links open the cited item | — | M | requirement |
| 11 | Response → execution (PI-11) | Add to `StrategicResponse`: `execution_status` (not started / in progress / done / paused), `due_date`, `kpi_indicator_id` (an existing indicator), `last_reviewed_at`. Show on Responses and the Report; feed "review due" into #9 | Tests for the fields and the review-due trigger; report section | #9 | M | requirement |
| 12 | Response → indicator link | Covered by `kpi_indicator_id` (#11) | — | #11 | — | — |
| 13 | Gate rationale and artefact (PI-12) | `project_decisions`: nullable `artefact_type/id` and `rationale`; dialogs ask for a short reason at G1–G7; status changes logged | Decision log shows the reason and opens the artefact | — | M | **Done 2026-11-13** (requirement, decision "optional, required on override"): `project_decisions.artefact_type/artefact_id/rationale` (migration `2026_11_13_000003`); `DecisionContext` carries the optional reason from G1–G5, G7 and status dialogs (required when the G4 dependency warning is overridden); status changes logged as `ST`; decision log shows the reason. Opening the artefact from the log not added; G6 review has no reason field. 3 tests |
| 14 | AI provenance (PI-13) | Nullable `generation_run_id` on conditions, implications, responses, indicators and observations, kept on accept; run detail in `ProvenanceBadge` | Accepting an AI item keeps its run; badge shows model and date | — | M | maturity |

### Phase 3 — Coherence and onboarding (P2/P3)

| # | Problem | Proposed resolution | Size | Type |
|---|---|---|---|---|
| 15 | Two products in one shell (PI-15) | **Dashboard tabs stay (PAD-3).** **One firm-lifecycle board instead of 5 governance pages (PAD-4).** Exercise → its scoped reports; force → exercises that use it; label firm-only analyses; Indonesian navigation labels | M–L | consolidation |
| 16 | Organisation provisioning and roles (PI-16) | Operator screen or command to create an organisation (seeds its taxonomy); a tenant `analyst` role (ingest, score) between admin and bod | M | requirement |
| 17 | Method order (PI-17) | G6 requires step 5 done; condition change flags linked implications; predetermined toggle on an axis member withdraws G4 | S | requirement |
| 18 | CU guardrails (PI-18) | Below-threshold warning; "no cross-impact data" instead of independent | S | maturity |
| 19 | Participant roles meaningful (PI-12) | Coordinator may run gates; expert scores only; contributor scores and drafts | M | maturity |
| 20 | Duplicates (PI-14) | `content_hash` warning; similarity check when accepting force candidates, responses and indicators, offering "link existing" | S–M | maturity |
| 21 | What-if disconnected (C-02) | Open from Compare or step 8 with the scope preset; optionally save named runs | S | integration |
| 22 | Firm governance history (PI-19) | `approved_by`/`closed_by` and a status transition log; allow re-open of a signal review | M | maturity |
| 23 | Leftovers (PI-20) | Remove unused permissions, `welcome.blade.php`, `api/user`, `STEPS[].module`; fix README and docs drift; skip the guest 401 polling; split the oversized pages (AUD-24) when touched | S | hygiene |
| 24 | Knowledge Graph unscoped and oversized (A-14) | **Decided (PAD-5): simplify.** Status filter (accepted/approved by default), FIRM or exercise scope, period, own permission (starting with today's holders), page split | M | simplification |

### Explicitly deferred (P3 / optional)

- A PDF/PPTX export of the exercise report (print to PDF works).
- Automatic scheduled AI matching (keep it manual until #9 exists).
- More external data connectors beyond the World Bank.
- A knowledge graph redesign.
- A second AI provider as fallback (operational, PRD-14).
- Multi-organisation membership per account.
- Full project-management features (tasks, Gantt, resourcing): out of scope. #11 is the deliberate minimum.

### Recommended sequence

1. Phase 1 items 1, 4, 5, 6 (all S).
2. Then 3, 2 and 7, together with 13, which provides the rationale field they need.
3. Decision 8 in parallel.
4. Phase 2: 9 → 11 → 10, then 14.
5. Phase 3: 15 and 16 first (onboarding), then the rest.

Each item ships with tests that fail first. `feature_list.json` is updated only with real evidence.

## 2. Executive assessment

1. **What is Foresight Radar today?** A **scenario planning platform** (the nine-step method, well guided and gated). It is fed by an **evidence library**: signals with verbatim verification, and driving forces with a firm lifecycle. On top sit **firm-level analytics** inherited from an earlier "driving force register" product, and a **monitoring register**.
2. **Is it a complete strategic foresight platform?** Not yet. The steps are complete, and every step can be done in the browser (production E2E 2026-11-12). But several gates certify work without requiring it, and the loop back from monitoring to a person is missing.
3. **Which kind of platform?** A combination. Mainly scenario planning, with environmental intelligence as its input. Decision intelligence is partial (comparison, robustness, backcasting); continuous monitoring is nascent.
4. **Genuinely strong:**
   - verbatim evidence verification;
   - extraction with recovery;
   - the exercise workspace (stepper, next action, locks, staleness);
   - server-side thresholds;
   - AI as proposals only;
   - tenant isolation;
   - operational recovery (backup and restore rehearsed).
5. **Implemented but limited value:**
   - Dashboard tabs duplicating report pages;
   - Knowledge Graph as it stands;
   - unsaved what-if;
   - the 5-page governance split;
   - platform user management for tenants.
6. **Disconnected:** what-if simulation; scoped reports not linked from exercises; the firm lifecycle vs exercise changes.
7. **Redundant:** legacy `/scenarios`; Dashboard tabs vs report pages; user management vs members.
8. **Essential capabilities missing:**
   - notifications and review queue;
   - monitoring cadence;
   - response execution status;
   - gate rationale;
   - organisation provisioning;
   - stuck AI-job recovery.
9. **Method coherent?** In structure, yes. In substance, partly (see the methodology report: G3, G6/G7 after reopen, step 5 order, step 9).
10. **Core journey without workarounds?** Yes for the happy path. Without AI, narratives are written by hand, which is valid. Correcting a signal review needs deletion.
11. **Continuous intelligence or scenario planning?** Scenario planning with a monitoring register. Continuity depends on someone opening the exercise.
12. **Can decisions be traced to evidence?** Partly: from quote to force, and from indicator to event. Not from a narrative statement to a challenge and a response, and no link anywhere is navigable.
13. **Recommendations connected to execution?** Only through backcasting milestones. Responses stop at approval.
14. **What to fix before new modules:** Phase 1, then items 9–13.
15. **Next three phases:**
    1. Method integrity (Phase 1).
    2. Loop and traceability (Phase 2).
    3. Coherence and onboarding (Phase 3).

## 3. Maturity (1–5; not averaged — the dimensions are not comparable)

| Dimension | Score | Evidence and rationale |
|---|---|---|
| Foundation | 4 | 628 tests green; tenant isolation tested; deploy gated, backups verified and restore rehearsed. No organisation provisioning |
| Functional completeness | 4 | All nine steps and the library exist and work; missing notifications, execution and provisioning |
| Workflow integrity | 3 | Happy paths complete (production E2E); library ↔ exercise drift; what-if and reports disconnected |
| Methodology integrity | 3 | Correct structure; four P1 substance gaps (G3, G6/G7, step 9, lifecycle) |
| Intelligence quality | 3 | Strong evidence verification; duplicates not caught; FIRM period semantics questionable; live AI unverified on production |
| Decision support | 3 | Compare, robustness and backcasting exist; rationale, execution and relevance outside step 9 missing |
| Continuous monitoring | 2 | Register with server thresholds; no notification, cadence or owner workflow |
| Product usability | 3 | Exercise side 4 (guided, lean pages); firm side 2 (5 governance pages, 900-line pages, English nav in the Indonesian UI) |
| Product coherence | 2 | Two eras of product in one shell; duplicate surfaces; no navigation between scopes |

## 4. Product direction recommendation

- **Positioning:** *evidence-based scenario planning for organisations*. A team turns verified signals into agreed futures, robust responses and monitored assumptions, with every step recorded.
- **Core value proposition:** "Every strategic response can be traced back to verified evidence, and you are told when the assumptions behind it change."
- **Preserve:**
  - evidence verification;
  - the exercise workspace and gates;
  - AI-as-proposal;
  - thresholds and relevance (fixed);
  - backcasting;
  - tenant isolation and the ops tooling.
- **Consolidate:**
  - Dashboard tabs → report links;
  - 5 governance pages → one lifecycle board;
  - legacy `/scenarios` → read-only archive;
  - the narrative's implications and early-indicator text → seeds labelled as such;
  - user management → operators only.
- **Defer:** new connectors, PDF/PPTX export, knowledge graph redesign, automatic AI matching, project-management features.
- **Highest-priority gaps:**
  1. gates that pass without the work;
  2. no notification or review loop;
  3. a broken middle of the evidence chain;
  4. responses without execution status.
- **Next development phase:** Phase 1 (method integrity). It is mostly small changes with tests. It makes every "done" in the product true, and it is the prerequisite for trusting notifications and reports built on top.
