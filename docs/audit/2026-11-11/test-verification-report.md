# Test & Verification Report (2026-11-11)

Environment: Docker (`laravel_app`, MySQL, queue, nginx on :8080). Commands run inside `laravel_app` unless noted. Only results that were actually produced are listed.

## Baseline on `1a89e0c` (before U1)

| Command | Result | Duration |
|---|---|---|
| `vendor/bin/pint` (all files) | PASS, 463 files | — |
| `vendor/bin/phpstan analyse --memory-limit=1G` (configured paths, level 5) | no errors reported | — |
| `php artisan test` | **583 passed** (6,678 assertions) | 823.60 s |
| `DB::table('users')->count()` on the dev database | 38 (unchanged) | — |

Earlier the same day, before the final Part B fixes: 575 passed, 2 failed (`DrivingForcePicTenantScopeTest`, `OperatorWorkspaceTest`: fixtures used a dimension of another organisation; fixtures corrected, tests pass).

Targeted runs on the same day (all passed): `ReportScopeTest` (8 tests incl. Dashboard), `TenantDimensionsTest` (5), `DimensionSettingsTest` (5), `Taxonomy` folder, `LlmSignalExtractionTest` and seven other signal test files (75 + 4), `npm run build` (several runs, last 2 min 42 s, exit 0).

## U1 (this unit)

U1 changed documentation, `.gitignore` and two browser e2e specs. No PHP or JSX changed, so PHPUnit and the build are unaffected; see the U1 evidence row in `PROGRESS.md` for what was re-run.

| Check | Result |
|---|---|
| `git check-ignore` on the deck, PDFs and a screenshot under `docs/` | ignored |
| Files under `docs/` git would track | 74 Markdown/JSON + 1 HTML + 1 CSS |
| Secret scan of `docs/**/*.md|json` (API key, private key, password=… patterns) | no matches |
| `tests/e2e/auth.e2e.ts`, `tests/e2e/accessibility-labels.e2e.ts` | rewritten to `/forgot-password`; **not executed** (see below) |
| `npm run typecheck` (`tsc --noEmit`) | exit 0; note `tsconfig.json` includes only `resources/js/**/*.ts(x)`, so the e2e specs and all JSX are not type-checked (AUD-18) |
| `feature_list.json` parse | valid JSON, version 1.2.0, 68 features |

## U2 (stale job states, atomic claims, migration safety)

| Check | Result |
|---|---|
| 8 new tests in `SignalExtractionRecoveryTest`, before the fix | 7 failed, 1 already passed (with the 4 existing tests: 7 failed, 5 passed) |
| `SignalExtractionRecoveryTest`, `LlmSignalExtractionTest`, `tests/Feature/Taxonomy` (incl. new `DimensionMigrationSafetyTest`), `ExternalIndicatorSourceTest`, `DimensionSettingsTest` after the fix | **62 passed** (538 assertions), 169.45 s |
| New indicator tests (2) and migration tests (5) | written together with the fix; **not seen red** |
| `vendor/bin/pint` on the changed files | PASS (107 files checked) |
| Larastan on the changed PHP files | 1 finding (nullsafe on a non-null enum) fixed; rerun: no errors. After the fix, `SignalExtractionRecoveryTest` + `DimensionMigrationSafetyTest` + `DimensionSettingsTest`: 22 passed (124 assertions, 125.15 s) |
| Dev database | 38 users; no source or indicator fetch left pending |
| Browser (built-in, local) | `/signals` and an exercise's `/indicators` page load; the indicator props carry `fetch_status`/`fetch_error`; `GET /signals/extractions/active` 200 |

Not run in U2: the full PHPUnit suite; `npm run build` (no frontend change); a full `down()` that changes the schema; row locking on MySQL (the tests use SQLite, which has no row locks).

## Not executed, and why

- **Browser e2e (`npm run test:e2e`, `test:e2e:smoke`)**: the `e2e` runner (v0.17, "agentic end-to-end testing") drives the browser through a model; running it spends AI tokens and needs credentials in the environment. Not run in this unit. UI checks were done with the built-in browser instead during the feature work.
- **`npm audit --audit-level=critical`**: not re-run today.
- **Migration on production-like multi-tenant data**: only the two-organisation dev database (see tenant-isolation-data-integrity.md).
- **Production**: nothing run against production.

## Coverage gaps (tracked in the backlog)

- No test walks one exercise from Step 1 to Step 9 (U7).
- Threshold crossing has only a test that sends the client boolean (U4 will replace it).
- No browser e2e for scenario planning, Signals or Settings (AUD-19).
- `npm run typecheck` checks one unused `.ts` file; no lint (AUD-18).
