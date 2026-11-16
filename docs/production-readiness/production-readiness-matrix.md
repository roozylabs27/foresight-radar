# Production Readiness Matrix — Foresight Radar

Verification date 2026-11-14.

- **Production / `main`:** `bb4fa31` (CI #120, Deploy #58).
- **This closure pass:** uncommitted on top of `d71ec32` (PR #27).

Statuses:
- **PASS:** implemented and verified with evidence.
- **FAIL:** verification failed.
- **PARTIAL:** implementation or verification incomplete.
- **BLOCKED:** an external dependency prevents verification.
- **N/A:** not applicable.

Source inspection alone is never PASS.

| ID | Requirement | Priority | Implementation | Verification | Evidence | SHA / state | Test or workflow | Remaining risk | Responsible |
|---|---|---|---|---|---|---|---|---|---|
| RR-01 | Latest commit passed CI and was deployed | P0 | Done | **PASS** | CI #120 and Deploy #58 green for `bb4fa31`; production serves its frontend; `/up` 200 | `bb4fa31` | ci.yml, deploy.yml | – | – |
| RR-02 | Post-deploy checks executed | P0 | Done (`post-deploy-check.sh` in deploy.yml) | **PARTIAL** | Deploy #58's final SSH step succeeded, which needs the check to exit 0; the log text was not read | `bb4fa31` | deploy.yml step 6 | R-15 | VPS owner (block 1) |
| RR-03 | Production verification script run (VPS) | P0 | Done: verdict summary, exit code, no secrets | **BLOCKED** | Dry run locally (all verdicts produced; git-failure SKIP fix); production run pending | uncommitted | `scripts/ops/verify-production.sh` §10 | R-15 | VPS owner (block 2) |
| RR-04 | Production MySQL credentials and access | P0 | Checks built (not root, no default passwords, no global admin grants, localhost bind) | **BLOCKED** | – | uncommitted | verify §10, handoff block 6 | R-13 | VPS owner |
| RR-05 | Operational alert delivery | P0 | Done: `ops:alert --test`, backup failures, failed-job summaries; synchronous; no payloads; failures logged without loops | **BLOCKED** (code PASS) | `OpsAlertTest` 4/4; production mailbox not confirmed | uncommitted | `OpsAlertTest`, handoff block 3 | R-01 | VPS owner |
| RR-06 | Scheduled database backup | P0 | Script ready (lock, space, checksum, alert); cron documented | **BLOCKED** | Local WSL tests 2026-11-13/14 | uncommitted | handoff block 4 | R-02 | VPS owner |
| RR-07 | Off-server backup | P0 (disaster recovery) | rclone `crypt` support with verification | **BLOCKED** | – | – | handoff block 5 | R-02 | VPS owner (needs an account) |
| RR-08 | Restore rehearsal | P0 | Done | **PASS** (2026-11-13, isolated) | 12.6 s, 55/55 tables, 0 orphans, 37/37 pages | `46e22d4` era; schema unchanged since | `restore-rehearsal.sh` | Off-server restore not rehearsed | VPS owner (block 5) |
| RR-09 | SSRF and DNS rebinding closed | P0 | Done: IP pinning, every DNS answer public, redirects refused, size cap | **PASS** | Unit, feature and credential tests green; mutation check | uncommitted | OutboundGuardTest, OutboundRequestGuardTest, LlmCredentialSafetyTest | – | – |
| RR-10 | Platform AI key never sent to user hosts | P0 | Done (SEC-PR-01) | **PASS** | LlmCredentialSafetyTest, OutboundRequestGuardTest | `bb4fa31` + pass | PHPUnit | – | – |
| RR-11 | Tenant isolation and authorization | P0 | Done | **PASS** | PHPUnit isolation suites; browser A6 and D3 (404/403, no data leaks) | uncommitted | auth.spec.js, ai.spec.js | Production check with two real organisations | Product owner |
| RR-12 | Browser E2E, critical workflows A–F | P0 | Done (32 tests) | **PASS** (local, fresh isolated stack) / **PARTIAL** (CI job not yet run) | `scripts/e2e/run.sh`: 32 passed, exit 0, 469 s | uncommitted | tests/browser, ci.yml `browser-e2e` | R-16, R-17 | Engineering (watch the first CI run) |
| RR-13 | Full regression suite | P0 | – | **PASS** | PHPUnit 746/746; Larastan no errors; Pint; ESLint 0 warnings; build; audits | uncommitted | `composer verify` equivalent | CI must pass on the merged SHA | User (merge) |
| RR-14 | Deploy gate: only CI-verified SHAs, manual runs proven | P0 | Done | **PASS** (code, Deploy #58) / manual path not exercised | `workflow_run` push+main; `workflow_dispatch` checks CI via the API | `bb4fa31` | deploy.yml | – | – |
| RR-15 | Backup before migration; stop on failure | P0 | Done | **PASS** | Deploy step order; `set -euo pipefail`; local failure tests | `bb4fa31` | deploy.yml, backup-database.sh | – | – |
| RR-16 | Least-privilege workflow tokens | P1 | Done (`contents: read`; deploy adds `actions: read`) | **PARTIAL** | YAML parsed; effective from the next run | uncommitted | ci.yml, deploy.yml | – | – |
| RR-17 | Branch and environment protection | P1 | Not configured | **FAIL** | No ruleset on `main` (2026-11-14) | – | GitHub settings | R-14 | Owner (block 7) |
| RR-18 | Observability: errors, queue, scheduler, disk, backup, AI traceability | P1 | Done in code | **PARTIAL** | Verdicts and alerts exist; delivery unverified | uncommitted | verify §10, ops:alert | R-01 | VPS owner |
| RR-19 | Rollback procedure documented and tested | P1 | Done | **PASS** (rehearsed 2026-11-13) | Code rollback 19 s; DB restore 20.8 s | – | runbook §5, Ops §6/§7 | R-10 (non-reversible migration) | – |
| RR-20 | Runbook current and executable | P1 | Done | **PASS** | Commands match the scripts; queue and scheduler recovery added | uncommitted | deployment-and-recovery-runbook.md | – | – |
| RR-21 | Dependency advisories | P1 | Done (blocking audit with reviewed exceptions) | **PASS** | composer audit exit 0; npm audit 0 critical | uncommitted | ci.yml | R-08 | Engineering (Laravel 12) |
| RR-22 | Live AI provider | P2 | – | **BLOCKED** (needs a live provider) | – | – | – | R-03 | Product owner |
| RR-23 | CSP enforced | P2 | Report-only | **PARTIAL** | Headers live | – | nginx | R-05 | Engineering |

## Gate

**NOT PRODUCTION READY — NO-GO.**

The P0 rows RR-03 to RR-07 are BLOCKED on the VPS owner, and RR-02 has only indirect evidence. The release rules do not allow these to be accepted as conditional gaps: they cover production health, database security, alert delivery and backup.

Every row that depends only on code or local verification is PASS.

The steps to GO are in [final-verification-report.md](final-verification-report.md) §9.
