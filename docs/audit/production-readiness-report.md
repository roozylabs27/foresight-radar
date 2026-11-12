# Production Readiness Report — Foresight Radar

Status: **in progress** (Phases 1–4 done, Phase 5 in progress, 2026-11-12). Final assessment: **not yet given**.

Related artefacts (filled in as the phases progress): [workflow matrix](production-workflow-matrix.md) · [security report](production-security-report.md) · [reliability report](production-reliability-report.md) · [remediation backlog](production-remediation-backlog.md) · [readiness checklist](production-readiness-checklist.md). The earlier code audit is in [2026-11-11/](2026-11-11/README.md); its fixes are the starting point here, not repeated.

## 1. Scope and environment

| Item | Value | How it was established |
|---|---|---|
| Target environment | **https://foresight-radar.id** — the production server, used as staging because no staging server exists yet (decision of the product owner, 2026-11-12) | user decision |
| Deployed version | current `main` (`9ae54b4`, same tree as `development` `d144162`) | Ziggy route list on the production login page: identical to the local named routes except the three Ignition debug routes (correctly absent in production) |
| Stack | Laravel 11 / PHP 8.3.36, nginx 1.31.8, MySQL 8, Docker Compose (`app`, `queue`, `scheduler`, `nginx`, `mysql`) | response headers; `docker-compose.yml` |
| Queue | `database` driver; worker `queue:work --tries=1 --timeout=310 --max-time=3600`; `retry_after` 360 s | `docker-compose.yml`, `config/queue.php` |
| Scheduler | `schedule:work`; one task: `indicators:fetch-external` daily 02:00 `withoutOverlapping` | `routes/console.php` |
| Logging | `stack` → `single` file (`storage/logs/laravel.log`), no rotation, no external error tracking | `config/logging.php`, `composer.json` |
| Sessions | file driver, 120 min, `secure` when `APP_ENV=production`, `http_only`, `SameSite=Lax` | `config/session.php`; only `XSRF-TOKEN` is readable by JavaScript in production |
| Deployment | GitHub Actions `deploy.yml`: on **every push to `main` or `development`**, builds assets, `git pull`, `docker compose up -d`, `composer install --no-dev`, `migrate --force`, cache, FPM reload, queue restart | `.github/workflows/deploy.yml` |
| AI provider | allowed for this validation on test data (product owner, 2026-11-12); production had **no provider configured** on 2026-11-11 | user decision; PROGRESS session 71 |
| Browser validation | the agent drives the built-in browser; the product owner signs in (the agent does not create accounts or enter passwords on this host) | user decision; safety rule |

Validation rules for this environment: non-destructive only; every record created is prefixed **[QA PROD]**; no migrations, load tests or deletion of data the validation did not create; real organisations' data is not opened beyond what a page shows by default.

## 2. Methodology

Phases as in the brief: (1) baseline and discovery, (2) workflow mapping, (3) security and integrity, (4) reliability and usability incl. browser E2E, (5) remediation of verified issues only, (6) re-verification and decision. Every scenario is recorded with ID, preconditions, role and organisation, steps, expected and actual result, evidence and status (PASS / FAIL / BLOCKED / NOT TESTED).

## 3. Phase 1 — Baseline and discovery (done 2026-11-12)

### 3.1 Code and test baseline (local, from the previous session)

- Full PHPUnit **626 passed** (7,502 assertions); Pint and Larastan level 5 clean; ESLint 0 errors / 57 warnings; `npm run build` passes; `npm audit` 0 critical (4 moderate, 6 high).
- HTTP-level journeys exist: `ForesightExerciseJourneyTest` (Steps 1→9) and `TenantIsolationJourneyTest`. **These are not browser E2E and were run on SQLite, not MySQL.**
- Browser E2E specs in the repository cover login, password reset and a few pages only; none of the nine steps (AUD-19).

### 3.2 Inventory

| Area | Count / items |
|---|---|
| Named routes | 201 locally (198 in production Ziggy + 3 Ignition); 93 under `scenario-planning/*` |
| Queued jobs | 13: `ExtractSignals`, `GenerateScenarioNarrative`, `FetchIndicatorSource`, `MatchSignals`, 8 `Suggest*` AI jobs, plus the external-indicator command; all `RunInTenantContext` + `ShouldBeUnique`, `tries=1` |
| Migrations | 63 |
| Models / controllers | 40 / 37 |
| Console commands | `indicators:fetch-external` (scheduled), `llm:smoke`, `llm:compliance-report`, clustering harness |
| Supporting workflows | sign-in, password reset, invitations, members and roles, operator workspace switch, organisation settings and AI provider, dimensions and environments, signal ingestion and review, driving force library and approval/closed items, reports (Dashboard, Prioritizing, Radar, Registered List, Cross-Impact, Knowledge Graph), user management (platform roles) |

### 3.3 Observations from the production front door (read-only, not signed in)

| Check | Result |
|---|---|
| HTTPS + HSTS | `max-age=31536000; includeSubDomains` |
| `X-Content-Type-Options` / `X-Frame-Options` / `Referrer-Policy` | `nosniff` / `SAMEORIGIN` / `strict-origin-when-cross-origin` |
| Content-Security-Policy, Permissions-Policy | **absent** |
| Version disclosure | `Server: nginx/1.31.8`, `X-Powered-By: PHP/8.3.36` |
| Unknown URL | 404 page, no debug output (no Ignition routes in production) |
| Session cookie | not readable by JavaScript (HttpOnly) |

### 3.4 Preliminary findings (observed; to be validated in later phases)

See [production-remediation-backlog.md](production-remediation-backlog.md): PRD-01 (deploys are not gated and have no backup or rollback), PRD-02 (production doubles as staging), PRD-03 (no CSP, version disclosure), PRD-04 (logging and monitoring), PRD-05 (AI provider configuration in production).

## 4. Phase 2 — Workflow mapping (done 2026-11-12)

- Mapped the nine steps and eleven supporting journeys in [production-workflow-matrix.md](production-workflow-matrix.md): actor, gate, transitions, entities, authorisation, handled failures, and three validation columns (HTTP test / production render / production E2E).
- Production, read-only, signed in as Developer (ProNext Hub): every step page of the test exercise answers 200 through the stepper's own links; the gate log holds G1, G2, G3, G4×2, G5 and the preferred-future decision.
- AI provider: a Gemini key is active for the Developer account (PRD-05 re-checked).
- New observations: PRD-06 (no closing state), PRD-07 (steps can complete out of order), PRD-08 (duplicated narrative fields and stand-alone scenarios), PRD-09 (template narratives despite an active key; to verify).
- Only one organisation is visible to this account; the second organisation and the other roles are needed for Phase 3.

## 5. Phase 3 — Security and integrity (done 2026-11-12)

- 17 scenarios in [production-security-report.md](production-security-report.md): production (as Developer) and local Docker (three temporary QA accounts in two organisations, deleted afterwards).
- **No P0.** Production refuses CSRF-less writes and unknown ids, exposes no secrets, keeps the session cookie from JavaScript. Locally, an admin of another organisation gets 404 on 28 reads and writes of the exercise, settings and members, and 422 when pointing at the other organisation's dimension or users; a viewer sees no management actions and gets 403 on 11 direct writes.
- New findings: PRD-10 (viewer may record observations, decision), PRD-11 (duplicate gate decisions under concurrency), PRD-12 (duplicate create under concurrency), PRD-13 (cross-organisation not testable on production).

## 6. Phase 4 — Reliability, usability and browser E2E (done 2026-11-12)

- [production-reliability-report.md](production-reliability-report.md): 17 browser E2E scenarios on production, 6 AI scenarios, 8 failure cases, usability notes.
- **The nine steps were completed on production through the browser** on "[QA PROD] Transisi Energi Industri 2032": all steps `done`, G1–G7 logged once each, server-side threshold crossing flagged the framework, quadrants and response, the report is complete.
- **AI live output not validated:** the provider (Gemini) was overloaded all session; every AI path failed cleanly with a clear message but after waits of up to ~4 minutes (PRD-14).
- New findings: PRD-14 (AI wait / availability), PRD-16 (split stale reason), PRD-17 (dialog copy), PRD-18 (G5/G7 without confirmation), PRD-19 (no unsaved-change warning), PRD-20 (English labels on Signals). PRD-12 limited to direct API calls (the form blocks double clicks).

## 7. Phase 5 — Remediation (in progress, 2026-11-12)

No P0 was found, so Phase 5 starts with the P2/P3 findings that need no product decision. Each fix has a test that failed before the change and passes after it. Nothing is deployed to production in this phase.

| Group | Finding | Change | Test |
|---|---|---|---|
| 1 | PRD-11 duplicate gate decisions | `App\Support\RowLock::run()` (transaction, `lockForUpdate`, reload) around G1, G2, G3, G6, G7; a passed gate is a no-op | `StrategicResponseTest::test_a_gate_passed_twice_from_stale_copies_is_logged_once` |
| 1 | PRD-16 split stale reason | threshold reason "(rule, nilai x)" without "; " | `EarlyWarningIndicatorTest::test_the_server_decides_a_numeric_crossing_and_ignores_the_client_flag` |
| 1 | PRD-14 AI wait | timeout kept (successful Gemini calls take 193–253 s); waiting hint in the shared AI control | ESLint + build; browser check pending |

Waiting for a product decision: PRD-01 (deploy pipeline), PRD-06 (closing state), PRD-07 (step completion order), PRD-10 (viewer observations). Next fix groups: PRD-17, PRD-18, PRD-19, PRD-20.

## 8. Phase 6

Not started: re-verification, readiness checklist, final assessment.

### What Phase 2+ needs from the product owner

- Sign in to production in the built-in browser with an account of the organisation used for testing.
- For role and tenant boundaries: a second organisation with its own account, and accounts with a viewer-type role (e.g. `bod`) and an admin role; the product owner signs in to each when asked.
- An AI provider configured for the test organisation (Settings → Mesin AI), since real AI calls are allowed on test data.
