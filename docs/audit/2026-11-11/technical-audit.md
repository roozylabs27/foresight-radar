# Technical Audit — 2026-11-11

Commit audited: `1a89e0c` on `development`. Evidence comes from reading the code (three independent read-only reviews plus spot checks). Line numbers are as of that commit. Severity: P0 data corruption / isolation / unsafe migration / broken core workflow; P1 methodology or reliability gap in a core path; P2 hardening, consistency, harness; P3 nice to have.

Classification vocabulary: confirmed bug · missing functionality · integration gap · security risk · data integrity risk · performance risk · UX issue · documentation issue · architectural improvement · product decision.

---

## Methodology and workflow

### AUD-01 · P1 · Integration gap — Step 6 narratives live outside the exercise
- **Current:** `ScenarioPlanningProjectService::STEPS[6]['module'] = 'scenarios.index'` (`app/Services/ScenarioPlanningProjectService.php:29`) and `SCOPED_ROUTES` (`:213`) has no step 6, so the stepper links to the global page (`:276`). Hard links: `resources/js/Pages/ScenarioPlanning/Framework.jsx:121`, `Show.jsx:118`. Narrative endpoints exist only under `scenarios.*` (`routes/web.php:236-248`, `ScenarioController` generate/quadrant/review). The global page (`resources/js/Pages/Report/Scenarios.jsx`) always opens the most recently updated scenario, not the exercise's.
- **Expected:** Step 6 is a page of the exercise, showing that exercise's four quadrants, their conditions and evidence, with generate, edit and review.
- **Impact:** users leave the exercise to finish it and can pick the wrong framework; the stepper cannot show Step 6 as a scoped step.
- **Remediation:** U3 — `scenario-planning.narratives.*` routes and a `Narratives` page reusing `ScenarioNarrativeService`; `SCOPED_ROUTES[6]`; links updated; `/scenarios` removed from the sidebar (kept reachable, no data deleted — user decision).
- **Acceptance:** from any exercise, Step 6 opens that exercise's quadrants; generation, edit and review work there; tests cover scoping and authorisation.
- **Verification:** feature tests + browser check.
- **Status: fixed in U3 (2026-11-11).** Routes `scenario-planning.narratives.{index,state,generate,quadrant,review}` (`ExerciseNarrativeController`), page `Pages/ScenarioPlanning/Narratives.jsx` (reuses `QuadrantCard`), `SCOPED_ROUTES[6]`; links in `Framework.jsx`, `Show.jsx`, `Conditions.jsx` (next) and `Implications.jsx` (back) now go to Step 6. Edit and review logic moved from `ScenarioController` into `ScenarioNarrativeService::updateQuadrant()/reviewQuadrant()`, and the payload into `ScenarioPresenter`, so both pages behave the same. "Scenarios" left the sidebar; `/scenarios` still works by URL. Tests: `ScenarioNarrativeReviewTest` (6 new), `WorkspaceTest` (stepper and sidebar).

### AUD-02 · P1 · Missing functionality — no narrative revision history
- **Current:** regeneration overwrites `scenario_quadrants.narrative` in place and deletes the old citations (`app/Services/ScenarioNarrativeService.php:161`, `:193`); manual edits overwrite too. No revision table exists.
- **Expected:** every overwrite keeps the previous text, statements and citations with who/what produced them.
- **Remediation:** U3 — `scenario_narrative_revisions`, written before each overwrite or edit, listed on the Step 6 page.
- **Acceptance:** regenerate and edit keep earlier versions with source (AI run id or user) and time.
- **Status: fixed in U3 (2026-11-11).** Table `scenario_narrative_revisions` (`ScenarioNarrativeRevision::capture()`): before an edit that changes title, narrative or implications, and before a regeneration overwrites a filled quadrant, the old title, summary, narrative, statements, implications, indicators, `cited_signal_ids`, origin, provider, review state and generation run are stored with the reason and the user. Rows are only added. Listed per quadrant on the Step 6 page. The citation rows (`scenario_citations`) are still rewritten; the revision keeps the cited signal ids and the typed statements.

### AUD-03 · P1 · Integration gap (authorisation) — narrative rights follow the scenario creator, not the exercise
- **Current:** `ScenarioPolicy::update` allows the scenario's creator or an admin role (`app/Policies/ScenarioPolicy.php:13-17`). An exercise coordinator who did not create the framework cannot write narratives; the exercise's own policy (`ScenarioPlanningProjectPolicy`) is not consulted.
- **Remediation:** U3 — Step 6 routes authorise through the exercise policy.
- **Acceptance:** coordinator allowed; viewer and other exercises' users refused; other tenant not found.
- **Status: fixed in U3 (2026-11-11).** Step 6 routes authorise with `manage-scenario` plus `ScenarioPlanningProjectPolicy::update`; `ScenarioPolicy::update` defers to the exercise policy for a framework, so the old page follows the same rule. A quadrant of another framework answers 404. Tested: exercise owner allowed, a `manage-scenario` user outside the exercise refused, viewer refused, foreign quadrant 404. Not yet tested here: another organisation's user (planned in U7 `TenantIsolationJourneyTest`; the route binding is tenant-scoped).

### AUD-04 · P1 · Confirmed bug (data integrity) — threshold crossing is asserted by the client
- **Current:** `IndicatorObservationController::store` validates `'crossed_threshold' => ['boolean']` and stores it (`app/Http/Controllers/IndicatorObservationController.php:34`, `:48`); the UI is a checkbox. `threshold_rule` is free text. A `true` flags the framework (`:55-56`).
- **Expected:** the server decides a crossing from a structured rule and the observed value.
- **Remediation:** U4 — `threshold_operator` + `threshold_value` on indicators, a pure evaluator, server-side evaluation on every observation path; qualitative indicators keep a manual flag with a required note.
- **Acceptance:** a client boolean is ignored for numeric indicators; boundary cases tested.

### AUD-05 · P1 · Missing functionality — external and AI-matched observations never cross; no event record
- **Current:** World Bank fetch and AI signal matching hardcode `crossed_threshold = false` (`app/Services/ExternalIndicatorService.php:99`, `app/Services/SignalMatchService.php:110`); confirming a proposal does not evaluate. No threshold event table; deleting a crossing observation leaves the stale flag with no trace of why.
- **Remediation:** U4 — `indicator_threshold_events` (unique per observation), evaluation on confirmation, a reason on the framework and on the exercise's approved responses.
- **Acceptance:** a confirmed World Bank value beyond the threshold creates exactly one event and flags review.

### AUD-06 · P1 · Missing functionality — scenario relevance is a raw count without explanation
- **Current:** `EarlyWarningIndicatorService::relevance()` (`app/Services/EarlyWarningIndicatorService.php:187`) adds +1/−1 per accepted observation for every quadrant with an expected direction; no recency, `stable` counts as contradicting, one indicator with many observations dominates, no per-quadrant explanation. The UI does not call it probability (good).
- **Remediation:** U5 — latest accepted observation per indicator within a window, stable/qualitative neutral, threshold events marked, per-quadrant explanation listing indicators; formula documented in `docs/methodology/`; labelled "bukan probabilitas".
- **Acceptance:** tests for recency, latest-per-indicator, conflicts, explanation content.

### AUD-07 · P1 · Integration gap — upstream changes do not reach Steps 7–9
- **Current:** `StalenessService::markFramework` (`app/Services/StalenessService.php:22`) flags the framework and quadrants only; implications, responses and indicators are not flagged when axes or uncertainties change; an implication change does not flag responses; regenerating a narrative does not flag implications.
- **Remediation:** U6 — explicit rules, each with a reason; nothing deleted.
- **Acceptance:** one test per rule (Scenario F).

### AUD-08 · P2 · Product decision — Steps 7–9 gated only on G5
- **Current:** implications, responses and indicators require an approved framework (G5) but not approved narratives (G6); G7 (response approval) does not require G6.
- **Decision proposed in the plan:** G7 approval requires G6; drafting Steps 7–9 earlier stays allowed and the dependency is shown. Implemented in U6.

### AUD-20 · P2 · Testing gap — no end-to-end exercise journey
- **Current:** 20 feature test files under `tests/Feature/ScenarioPlanning/`; the longest chain is `ScenarioNarrativeReviewTest` (Steps 1–6). No test walks Steps 1–9; indicator tests build fixtures directly.
- **Remediation:** U7 — `ForesightExerciseJourneyTest` and `TenantIsolationJourneyTest`.

## Reliability

### AUD-09 · P1 · Confirmed bug — sources and indicator fetches can stay QUEUED forever
- **Current:** `SignalExtractionService::progress()` only treats PROCESSING as stale (`app/Services/SignalExtractionService.php:270-274`); `retry()` accepts only FAILED; a source whose job was lost (worker down, dropped by the unique lock) stays QUEUED and cannot be retried. `ExternalIndicatorService` skips anything QUEUED or PROCESSING with no stale sweep.
- **Remediation:** U2 — stale QUEUED becomes FAILED (retryable) after the window; same for indicator fetches.
- **Status: fixed in U2 (2026-11-11).** `App\Support\StaleJob`: QUEUED is lost after `queue.stale_queued_after` (900 s), PROCESSING after the job timeout + 60 s. `progress()` marks a lost source FAILED with a reason (conditional update, so a job that claimed it meanwhile wins); `retry()` accepts FAILED or lost and releases the old unique lock before dispatching. Indicators: `ExternalIndicatorService::fetchState()` reports a lost fetch as failed and `queue()` re-queues it. Tests: `SignalExtractionRecoveryTest`, `ExternalIndicatorSourceTest`.

### AUD-10 · P2 · Data integrity risk — extraction claim not atomic; stale error kept
- **Current:** the job's status check and the update to PROCESSING are separate (`app/Jobs/ExtractSignals.php`, `SignalExtractionService.php:180`); `ShouldBeUnique` has no `uniqueFor`; the stale window equals the job timeout, so a running job can be shown FAILED; a later COMPLETED keeps the old `extraction_error`. A failed AI call leaves no partial signals (verified: the LLM call is outside the transaction and returns early).
- **Remediation:** U2 — conditional QUEUED→PROCESSING update, `uniqueFor`, clear the error on completion, stale window above the job timeout.
- **Status: fixed in U2 (2026-11-11).** `extract()` and `FetchIndicatorSource` claim with a conditional update (only from QUEUED), so a duplicate job or one for a failed/completed row does nothing. Both jobs set `uniqueFor`. Completion clears `extraction_error` by query. The `failed()` handlers change the row only by key and only while it is still pending, so a late failure cannot overwrite a completed result (and they no longer depend on the tenant scope).

### AUD-13 · P2 · Data integrity risk — AI run recorded `completed` before signals are written
- **Current:** the extraction's `AiGenerationRun` is recorded completed before the insert transaction; a rollback leaves a completed run with no signals. Backlog.

## Data and migrations

### AUD-11 · P1 · Data integrity risk — dimension migration `down()` and recovery
- **Current:** `database/migrations/2026_11_11_000001_make_dimensions_tenant_owned.php` — the up path was fixed on 2026-11-11 (unique index switched before copies; column guards) and ran on a two-organisation dev database. Remaining: the FK swap is not guarded (a rerun after it fails at `dropForeign`); the owner is `min(tenants.id)` including soft-deleted tenants; environments are matched by name only; `down()` matches names without `whereNull('code')`, keeps unmatched copies and then fails on `unique(code)` or silently merges organisations' data.
- **Remediation:** U2 — guard the FK swap, owner = lowest live tenant, make `down()` refuse when it cannot merge losslessly; migration tests on prepared single- and multi-tenant data.
- **Status: fixed in U2 (2026-11-11).** The FK swap checks the current rule (`Schema::getForeignKeys`) and does nothing when it is already right. The owner is the lowest organisation that is not deleted (deleted ones still get a copy). Name matching was already limited to rows without a code in `copyTaxonomy`; `down()` now uses the same rule. `down()` first runs `downBlockers()`: it continues only when every organisation's taxonomy is identical to the owner's (environments; dimension code, name, environment, archive state) and otherwise throws before any change, listing the differences. Tests: `DimensionMigrationSafetyTest` (owner, code-less matching, rerun is a no-op, blockers, refusal leaves data and schema untouched). Not tested: a full `down()` run that changes the schema (DDL inside the test transaction) and a run on MySQL with production-like data.
- **Details and deploy procedure:** [tenant-isolation-data-integrity.md](tenant-isolation-data-integrity.md).

### AUD-12 · P2 · Data integrity risk — dimension delete races its usage check
- **Current:** `DimensionService::delete` counts usage then deletes without a lock; a signal or candidate inserted in between gets `dimension_id = NULL` (their FK is null-on-delete). Driving forces are protected by the RESTRICT FK. U2.
- **Status: fixed in U2 (2026-11-11).** The delete runs in a transaction that locks the dimension row (`lockForUpdate`) before counting; an insert referencing it waits on its foreign key. Not testable on the SQLite test database (no row locks); the existing in-use refusal tests still pass.

## Security

No P0. Details in [tenant-isolation-data-integrity.md](tenant-isolation-data-integrity.md).

### AUD-14 · P2 · Security risk (scoped) — `User` has no tenant scope
- User listing, update and delete in user management work across tenants, reachable only by platform roles (`developer`, `super-admin`). `UserController::create` creates users with `tenant_id = NULL`, who are then locked out by `EnforceTenantContext`. Backlog; needs a product decision on platform user management.

### AUD-15 · P3 · Information exposure — global email uniqueness
- `Rule::unique('users')` reveals an email registered in another tenant. Likely intended (one login per email). Backlog / decision.

### AUD-16 · P2 · Latent risk — job `failed()` handlers run without tenant context
- They only update existing rows today; a future `create` there would fall back to tenant 1. Backlog: note in AGENTS.md.

## Harness, documentation, tooling

### AUD-17 · P2 · Documentation issue — `docs/` not in git; AGENTS.md inaccurate — **fixed in U1**
- `.gitignore` had `/docs`, so every `docs/` reference in AGENTS.md was missing from git and CI. AGENTS.md said TypeScript, Playwright, Laravel 10 / React 18 and omitted that `verify.sh` runs `npm run typecheck`. `feature_list.json` `last_updated` 2026-10-26; PROGRESS header pointed at an old commit.

### AUD-18 · P2 · Architectural improvement — typecheck covers nothing; no lint
- `resources/js` has 182 `.jsx`, 30 `.js`, 1 `.ts` (unused); `tsconfig.json` includes only TS files, so `npm run typecheck` (CI and `verify.sh`) checks one unused file. No ESLint config or script. Backlog.

### AUD-19 · P2 · Testing gap — browser e2e
- Two specs opened `/register`, which does not exist (accounts are by invitation) — **rewritten in U1** to the password reset page. No e2e covers scenario planning, Signals or Settings; CI runs only the login smoke. The `e2e` runner is agentic and needs a model, so it was not run here.

## Product coherence (backlog)

| ID | Sev | Category | Finding | Evidence |
|---|---|---|---|---|
| AUD-21 | P3 | Product decision | Dashboard tabs repeat Prioritizing, Radar and Registered List pages | `resources/js/Pages/Dashboard.jsx` tabs; sidebar `AuthenticatedLayout.jsx:61-63` |
| AUD-22 | P3 | Architectural improvement | Unused or duplicate routes: `settings.ai.*` (frontend uses `settings.llm.*`), knowledge-graph `/index` and `/get-data` aliases, `user-management.user.profile.*`, `scenarios.update` | `routes/web.php` |
| AUD-23 | P3 | UX issue | User Management page has no navigation entry | no link to `user-management.user.` in `resources/js` |
| AUD-24 | P3 | Architectural improvement | Pages exceed the 150-line rule (e.g. `Signals/Index.jsx` ~900, `KnowledgeGraph.jsx` ~720) | AGENTS.md rule vs code |
| AUD-25 | P2 | Product decision → decided | Legacy global Scenarios module overlaps the exercise (creates stand-alone scenarios; edits exercise quadrants) | `ScenarioController`, `Report/Scenarios.jsx`; decision: hide from sidebar after U3 — **done in U3** |
| AUD-26 | P3 | Architectural improvement | Unused files: `Pages/Welcome.jsx`, `Components/ui/scroll-area.jsx`, `ui/separator.jsx`, `types/foresight.ts` | no imports found |

No mock data, placeholder buttons or `TODO` markers were found in `app/` or `resources/js`.
