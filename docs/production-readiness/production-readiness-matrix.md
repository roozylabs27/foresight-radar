# Production Readiness Matrix — Foresight Radar

Verification date 2026-11-13. Base commit `main` `81fa161`; this pass's changes are uncommitted (see [final-verification-report.md](final-verification-report.md) §2).

Status values:
- **PASS:** verified with evidence.
- **PASS (code):** verified in code and tests, not on production.
- **PARTIAL:** some parts verified.
- **UNVERIFIED:** no evidence in this pass.
- **FAIL:** known not met.

## Baseline (Phase 0)

| Area | Current state | Evidence | Remaining gap | Priority |
|---|---|---|---|---|
| Repository | `main` `81fa161` = `development` `de6765d` (same tree); no open PRs; clean tree | `git rev-parse`, GitHub PR list | – | – |
| CI | CI #118 passed on `81fa161` | GitHub Actions | – | – |
| Deployment | Deploy #57 deployed `81fa161`; #55 had failed (fixed by `apply-runtime-config.sh`) | GitHub Actions | Manual dispatch was not CI-gated (fixed, F) | P0 |
| Full test suite | The earlier failure is gone: 641/641 on the baseline | Report §1 row 1 | – | – |
| PI-07 | Open: `created_at` period filter | `ForesightReportingService.php:59` | Fixed (D) | P0 |
| Composer advisories | 12, CI advisory-only | `composer audit` | 8 fixed by the Symfony update; 4 listed; CI blocks new ones | P0 |
| Alerts, backup cron, off-server copy | Not configured or unconfirmed on production | Checklist O3; Ops §4b | Owner actions (R-01, R-02) | P1 |

## Readiness dimensions

| # | Dimension | Status | Evidence | Commit | Remaining gap | Owner or dependency |
|---|---|---|---|---|---|---|
| 1 | Full regression suite | **PASS** | 660 passed / 0 failed / 8,568 assertions (final tree before two type-only edits, covered by a targeted run); Pint and Larastan per PROGRESS session 100 | uncommitted on `81fa161` | CI must pass on the committed SHA | User: commit, PR, CI |
| 2 | PI-07 FIRM period | **PASS (code)** | `FirmAssessmentPeriodTest` (6); dev DB backfill: 27 ratings, 0 null, 2 moved, 1 without history | same | – (UI explanation checked in a local browser) | – |
| 3 | Lifecycle, gates G1–G7, staleness, step 9, rationale, `/scenarios` archive, AI recovery | **PASS (code)** | Suites in the 660: ScenarioPlanning (incl. PI-01/02/03/06/09/10, PAD-2 and rationale tests), journeys. Invalid transitions are refused server-side (422/403/405 tests) | same | Browser E2E | Engineering |
| 4 | Tenant isolation | **PASS (code)** | `TenantIsolationTest`, `TenantIsolationJourneyTest`, `Security/*`; audit found no cross-tenant IDOR (report §3) | same | Production cross-organisation check with two real organisations | Product owner |
| 5 | Authorization boundaries | **PASS (code)** after fixes | SEC-PR-01/02/03 fixed with tests; F5 open (R-09) | same | R-06, R-07, R-09 | Engineering |
| 6 | Secrets and credentials | **PARTIAL** | Stored AI keys never reach the browser; the platform key no longer leaves; `.env`/`.git` 404 on production | – | R-13 default MySQL credentials unverified | VPS owner |
| 7 | Deployment gate | **PASS (code)** | `deploy.yml`: push-to-main CI run, or an API proof of CI for the dispatched SHA; concurrency; `--ff-only` | uncommitted | Not yet exercised by a real deploy | Next deploy |
| 8 | Backup before migration | **PASS** | Deploy step 5 stops on failure (Deploy #55/#57 history); lock, space, checksum tested locally (report §2 H) | – | – | – |
| 9 | Scheduled and off-server backup | **FAIL / UNVERIFIED** | Script ready (Ops §4b); installation not confirmed | – | R-02 | VPS owner |
| 10 | Recovery and rollback | **PASS (rehearsed in isolation)** | 2026-11-13 rehearsal: restore 12.6 s, 0 orphans, code rollback 19 s; runbook §5 lists the non-reversible migration | – | Off-server restore not rehearsed | VPS owner |
| 11 | Post-deploy health | **PASS (code)** | `post-deploy-check.sh` in `deploy.yml` | uncommitted | First run on the next deploy | Next deploy |
| 12 | Production runtime health | **PARTIAL** | Browser, read-only: `/up` 200, headers, 404 on sensitive paths, HTTPS, HttpOnly session | live `81fa161` | `verify-production.sh` not run in this pass (debug, queue, scheduler, disk) | VPS owner |
| 13 | Monitoring and alert delivery | **FAIL** | `ops:failed-jobs` scheduled; `OPS_ALERT_EMAIL` empty (checklist O3) | – | R-01 | VPS owner |
| 14 | Browser E2E (workflows A–F) | **FAIL** | Only the login smoke in CI; HTTP journeys exist | – | R-04 | Engineering |
| 15 | Live AI reliability | **UNVERIFIED** | Fake-client tests only | – | R-03 | Product owner |
| 16 | Security headers | **PARTIAL** | Live; CSP report-only | live | R-05 | Engineering |
| 17 | Dependency advisories | **PASS** with exceptions | `composer audit` exit 0 with 4 listed (R-08); `npm audit` 0 critical | uncommitted | Laravel 12 upgrade | Engineering |
| 18 | Runbook | **PASS** | [deployment-and-recovery-runbook.md](deployment-and-recovery-runbook.md) and Ops runbook | – | – | – |

## Gate (Phase 11)

**CONDITIONALLY READY.** No known critical code blocker remains once this pass is committed and deployed. Critical operational dependencies are still open: alert delivery (R-01), the scheduled backup (R-02) and the MySQL credentials check (R-13). Browser E2E (R-04) is missing, and production runtime health is only partly verified. A controlled release to one organisation is acceptable only with explicit risk acceptance of these items and the owner actions in [known-risks.md](known-risks.md). It is not PRODUCTION READY.
