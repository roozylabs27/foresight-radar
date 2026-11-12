# Production Readiness Checklist — Foresight Radar

Phase 6 of the [production readiness validation](production-readiness-report.md), 2026-11-12. Each item gives its evidence. **PASS** means verified by the stated check. **OPEN** means not done or not verified. **ACCEPTED** means a known gap the product owner accepted for now. Code state: Phase 5 is committed on `development` (`7b9b75c` fix group 1, `1908f68` groups 2–3), not on `main`. **Production runs `7b9b75c`:** the push of fix group 1 still used the old workflow, which deployed every `development` push. This was confirmed in Phase 6 from the production bundle (the PRD-14 hint is present; the group 2–3 UI and the `scenario-planning.status` route are absent). Fix group 1 has no migration. `1908f68` carries the new workflow, so it did not deploy. On the server, the working copy is now on `development`; the next gated deploy switches it back to `main`.

## 1. Quality gates (local, Phase 6)

| # | Check | Evidence | Status |
|---|---|---|---|
| Q1 | Pint, whole code base | `pint --test` in `composer verify`: PASS | PASS |
| Q2 | Larastan level 5, whole code base | `phpstan analyse` in `composer verify`: No errors | PASS |
| Q3 | Full PHPUnit suite | `php artisan test` in `laravel_app`: **628 passed (7,581 assertions)**, 0 failed, 1,288 s. `composer verify` stopped PHPUnit at Composer's 1,200 s process timeout with no failure so far, so the suite was run directly | PASS |
| Q4 | ESLint | `npm run lint`: 0 errors, 57 warnings (warnings unchanged from baseline) | PASS |
| Q5 | Production build | `npm run build`: built | PASS |
| Q6 | npm audit, critical | `npm audit --audit-level=critical`: exit 0; 4 moderate, 6 high (unchanged) | PASS (high findings OPEN, see 6) |

## 2. Workflow (Steps 1→9)

| # | Check | Evidence | Status |
|---|---|---|---|
| W1 | An organisation completes Steps 1→9 in the browser on production | E2E-01..17, reliability report §1 | PASS (production, without AI narratives) |
| W2 | Steps 1→9 over HTTP with fake AI | `ForesightExerciseJourneyTest` | PASS |
| W3 | Gates G1–G7 logged once, also under concurrent requests | PRD-11 fix; `StrategicResponseTest` stale-copy test; **local MySQL re-run: three parallel G1 confirms → one "G1 approved"** (Phase 6) | PASS (local) |
| W4 | Exercise closes into monitoring / archive | PRD-06 fix, test, local browser | PASS (local) — not on production yet (`1908f68`) |
| W5 | Step order is clear (done-ahead label, template not "started") | PRD-07 fix, tests, local browser | PASS (local) |
| W6 | Threshold crossing decided on the server and flags review; reason readable | E2E-15; PRD-16 fix + test | PASS |

## 3. Security and tenant isolation

| # | Check | Evidence | Status |
|---|---|---|---|
| S1 | CSRF, unknown ids, no secrets in props, HttpOnly session | SEC-01..04 (production) | PASS |
| S2 | Cross-organisation reads and writes refused | SEC-05..09 (local), `TenantIsolationJourneyTest` | PASS (local + tests) |
| S3 | Cross-organisation on production | only one organisation there | OPEN (PRD-13) |
| S4 | Viewer cannot change the exercise | SEC-10, SEC-11; PRD-10 fix (observations need a contributor) | PASS |
| S5 | Security headers (CSP, Permissions-Policy, version disclosure) | Not live on production after Deploy #53: the mounted config was never applied (OPS-01, verified). Fixed in `deploy.yml`, not deployed | FAIL |
| S6 | Duplicate create under concurrent API calls | the form blocks double clicks (E2E-02) | ACCEPTED (PRD-12, P3) |

## 4. Reliability and AI

| # | Check | Evidence | Status |
|---|---|---|---|
| R1 | AI failures end cleanly with a clear message and no partial data | AI-01..06 (production, provider overloaded) | PASS |
| R2 | Users know an AI call can take minutes | PRD-14 waiting hint (not browser-checked during a live AI call) | PASS (code) |
| R3 | Live AI output on production (proposals, narratives with citations, extraction) | provider answered 503 all of 2026-11-12; Phase 6 re-check not possible (production session expired) | OPEN (PRD-09, PRD-14) |
| R4 | Stuck or lost queued jobs recover | `StaleJob`, atomic claims, tests (U2) | PASS (tests) — not reproduced on production |
| R5 | Network, database or worker outage | not simulated on production | OPEN |
| R6 | Unsaved form text is not lost silently | PRD-19 fix, local browser | PASS (local) |

## 5. Operations

| # | Check | Evidence | Status |
|---|---|---|---|
| O1 | Deploy only after CI on `main`, exact commit | First gated run (2026-11-12, `main` `88bc5fd`): started after CI, fast-forwarded the server to the tested commit | PASS (first run) |
| O2 | Database backup before migrations, rollback documented | Deploys #52 and #53 wrote complete dumps (verify script: `gzip -t` ok, "Dump completed", 55 tables). The latest one was **restored in isolation** (12.6 s, 0 orphans, 37/37 pages). The rollback runbook now uses the app user | PASS |
| O3 | Logs rotated, errors tracked or alerted | Daily log files on production; `ops:failed-jobs` runs hourly (4 "DONE" in 6 h); the alert works in isolation. `OPS_ALERT_EMAIL` is empty on production, so there is no mail delivery | FAIL (mail delivery, OPS-07) |
| O4 | Separate staging environment | production doubles as staging | ACCEPTED for now (PRD-02) |
| O5 | AI provider configured for the organisation (not only a personal key) | personal Gemini key active; organisation key not checked | OPEN (PRD-05) |
| O6 | Test data removed from production | "[QA PROD]" exercise and source, "[SMOKE TEST]" records still there | OPEN (needs the product owner's go-ahead) |

## 6. Readiness decision

**CONDITIONALLY READY** — for a controlled first organisation, once the conditions below are met. It is not yet ready for open onboarding.

Why not lower: there is no P0. The nine-step method was completed end to end on production through the browser. Tenant isolation holds in every local and automated check. AI failures are handled without partial data. The integrity issues found (duplicate gate decisions, viewer writes, unclear step state) are fixed and tested. The full suite, Pint and Larastan pass.

Why not higher:
- The Phase 5 fixes are not on production yet.
- The new deploy safety net has never run.
- The AI path, the product's main assist, has not produced a single live result on production.
- Logging is not production grade.
- Cross-organisation isolation is unproven on production itself.

Conditions, in order:
1. Merge `development` (`1908f68`) to `main` and let the gated pipeline deploy it (the first run is the PRD-01 dry run). Check `~/foresight-backups` holds a dump, then re-check G1/G5/G7 dialogs, the status buttons and the Signals page on production.
2. Re-run AI-01..06 on production when Gemini answers. Configure an organisation key, or a second provider as a fallback (PRD-05, PRD-09, PRD-14).
3. Daily log rotation and at least an alert on `failed_jobs` (PRD-04).
4. Remove the "[QA PROD]" and "[SMOKE TEST]" records with the product owner's go-ahead (O6).
5. Before a second organisation: CSP and Permissions-Policy (PRD-03), and repeat SEC-05..09 on production with two organisations (PRD-13). A staging server (PRD-02) is strongly recommended before data-moving migrations.

## 7. Production operations validation (2026-11-13)

Statuses: PASS (verified by the stated check), FAIL, BLOCKED, NOT TESTED, NOT APPLICABLE. Details: [verification](production-verification-report.md), [backup](backup-integrity-report.md), [recovery](recovery-rehearsal-report.md), [runbook](../guidelines/production-operations-runbook.md).

| Requirement | Evidence | Status |
|---|---|---|
| Release integrity | Server HEAD `2d202e7` = CI #105 and Deploy #53 (`workflow_run`); clean tree; no pending migrations | PASS |
| Application health | `/login` 200 in about 100 ms; no debug routes; Phase 5 routes and bundles live. Signed-in production pages were not checked | PASS (unauthenticated) / NOT TESTED (signed in) |
| Infrastructure | 5 containers Up, no restart loops; disk 14 %; 6.6 GB RAM free; DB reachable with the app user | PASS |
| Deployment | CI gate, exact SHA, fast-forward, backup gate and stop-on-failure verified. Mounted nginx and PHP config were **not applied**; a manual dispatch bypasses CI | FAIL (OPS-01), gap (OPS-12) |
| Backup | Latest dump complete, consistent (InnoDB + `--single-transaction`), 55/55 tables. World-readable; deploy-time only; no off-server copy | PASS (validity) / FAIL (OPS-02, OPS-03, OPS-04) |
| Restore | Production backup restored into an isolated stack: checksum verified, 12.6 s, 55/55 tables, 63 migrations, 0 orphan rows | PASS |
| Data integrity | 25 critical table counts equal the live counts; 165 foreign keys without orphans; `tenant_id` valid everywhere | PASS |
| Application recovery | Restored app: 37/37 critical pages 200 with a recovery-only account; no external calls | PASS |
| Code rollback | Previous release `d34343a` served the restored DB in 19 s; 37/37 pages 200 (in isolation, not on production) | PASS (isolation) |
| Migration recovery | Runbook restore (app user) over a populated DB in 20.8 s; post-backup writes lost as expected; previous code runs on the restored schema | PASS (isolation) |
| Monitoring | `ops:failed-jobs` scheduled and running on production; alert end to end in isolation | PASS |
| Alerting | Mail delivery on production: `OPS_ALERT_EMAIL` empty | FAIL (OPS-07) |
| Documentation | Runbook and rollback guide now match the rehearsed commands (app-user restore, recovery stack) | PASS |
| Deploy step logs | require a GitHub sign-in | NOT TESTED |
| Recovery on production hardware | not allowed by the safety rules (no restore into production; no separate server) | NOT APPLICABLE / BLOCKED (no staging server, PRD-02) |

**Operations assessment: RECOVERY VERIFIED** — not operationally ready yet. A production backup restores completely in isolation, and the application, the previous release and the documented restore all work on it. Open before OPERATIONALLY READY:
- the security config was never applied (OPS-01, fix ready);
- backups happen only at deploy and stay on the same VPS (OPS-02, OPS-03);
- backup files are world-readable (OPS-04);
- no alert mail (OPS-07);
- no monitoring of disk, database or backup age (OPS-11);
- no RPO/RTO targets or recovery owner (OPS-13).

## 8. Remaining backlog after Phase 5

Open: PRD-02 (accepted), PRD-03, PRD-04, PRD-05, PRD-08 (P3), PRD-09, PRD-12 (P3, accepted), PRD-13, PRD-14 (live part). The npm audit "high" findings (6) are not triaged. Fixed in code and verified locally: PRD-01 (workflow), PRD-06, PRD-07, PRD-10, PRD-11, PRD-16, PRD-17, PRD-18, PRD-19, PRD-20.
