# Prioritised Remediation Backlog (2026-11-11)

Order follows the agreed plan: small units, each ending with tests, documentation and a stop for review and commit. No P0 was found; P1 units come first, in dependency order.

| Unit | Priority | Findings | Scope (main files) | Depends on | Acceptance criteria | Status |
|---|---|---|---|---|---|---|
| **U1** Audit + harness truth | P2 | AUD-17, AUD-19 (register specs), Finding D | `.gitignore`, `docs/audit/2026-11-11/*`, `AGENTS.md`, `PROGRESS.md`, `feature_list.json`, `tests/e2e/auth.e2e.ts`, `tests/e2e/accessibility-labels.e2e.ts` | — | docs tracked (no binaries); AGENTS.md matches the stack; no spec opens `/register` | **Done 2026-11-11** |
| **U2** Stale states + migration safety | P1 | AUD-09, AUD-10, AUD-11, AUD-12 | `SignalExtractionService`, `ExtractSignals`, `ExternalIndicatorService`, migration `2026_11_11_000001`, `DimensionService::delete` | — | stale QUEUED becomes retryable; atomic claim; error cleared on completion; FK swap guarded; `down()` refuses lossy merges; migration tests on prepared single/multi-tenant data | **Done 2026-11-11** (row lock not testable on SQLite; full `down()` DDL not run) |
| **U3** Step 6 inside the exercise | P1 | AUD-01, AUD-02, AUD-03, AUD-25 | new `scenario-planning.narratives.*` routes/controller, `Pages/ScenarioPlanning/Narratives.jsx`, `scenario_narrative_revisions`, `SCOPED_ROUTES[6]`, sidebar | — | Step 6 opens the exercise's quadrants; exercise policy; revisions kept; `/scenarios` off the sidebar | **Done 2026-11-11** (other-tenant check left to U7) |
| **U4** Threshold engine | P1 | AUD-04, AUD-05 | indicator threshold columns, `indicator_threshold_events`, evaluator, observation paths, `IndicatorDialog`, `ObservationDialog` | — | server decides crossings for numeric indicators on every path; one event per crossing; client boolean ignored | **Done 2026-11-11** |
| **U5** Explainable relevance | P1 | AUD-06 | `EarlyWarningIndicatorService::relevance`, `RelevancePanel`, report/compare, `docs/methodology/` | U4 (threshold events shown) | latest per indicator within a window; neutral stable/qualitative; per-quadrant explanation; "bukan probabilitas"; formula documented | **Done 2026-11-11** (report/compare not changed) |
| **U6** Propagation into Steps 7–9 | P1 | AUD-07, AUD-08 | `StalenessService`, implication/indicator stale fields, `StrategicResponseService` G7 | U3 (narrative events), U4 (threshold events) | each rule flags with a reason and decision log; nothing deleted; G7 needs G6 | **Done 2026-11-11** (flags carry reasons; no extra decision-log rows for flags) |
| **U7** End-to-end journeys | P1 | AUD-20 (+ Scenario A–F) | `tests/Feature/Journeys/*`, e2e smoke if runnable | U2–U6 | one test walks Steps 1→9; tenant isolation journey; full verify recorded | **Done 2026-11-12** (browser e2e not run) |

## P2 / P3 backlog (not scheduled; needs a decision or follows the units)

| ID | Priority | Item | Note |
|---|---|---|---|
| AUD-13 | P2 | AI extraction run recorded `completed` before the signal transaction | **Done 2026-11-12**: recorded inside the transaction; a storage failure records a failed run |
| AUD-14 | P2 | `User` not tenant-scoped for platform user management; created users get `tenant_id = NULL` | Product decision on platform user management |
| AUD-16 | P2 | Job `failed()` handlers outside tenant context | Document rule; wrap with `TenantContext::runAs` if a handler ever creates rows |
| AUD-18 | P2 | Typecheck covers no JSX; no ESLint | **ESLint added 2026-11-12** (0 errors, 108 warnings as debt); JS vs TS still undecided |
| AUD-19 | P2 | Browser e2e for scenario planning, Signals, Settings; CI runs only smoke | The agentic `e2e` runner needs a model; decide budget |
| AUD-21 | P3 | Dashboard tabs duplicate report pages | Decide: keep tabs or link to the pages |
| AUD-22 | P3 | Unused / duplicate routes | Remove after confirming no external caller |
| AUD-23 | P3 | User Management not in navigation | Add for platform roles, or remove |
| AUD-24 | P3 | Pages above the 150-line rule | Split when touched |
| AUD-15 | P3 | Global email uniqueness disclosure | Likely intended |
| AUD-26 | P3 | Unused files | Remove when touched |

## Rules for every unit

Tests first (seen red), focused diff following existing patterns, Pint + Larastan on changed files, affected tests, `npm run build` when the frontend changes, a local browser check, dev database user count unchanged, PROGRESS and `feature_list.json` updated with real evidence, no commit or push by the agent.
