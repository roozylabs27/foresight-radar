# Known Risks — Foresight Radar

Updated 2026-11-14 (final production readiness closure).

- **`main`:** `bb4fa31`; CI #120 green, Deploy #58 green.
- **PR #27** (`d71ec32`, ESLint zero-warning gate): open, checks green, not merged.
- **The closure work of 2026-11-14** (SSRF/DNS-rebinding guard, `ops:alert`, verification verdicts, browser E2E, three UX fixes): uncommitted on `development` (see [final-verification-report.md](final-verification-report.md)).

Columns:
- **Likelihood:** how likely the risk occurs without the mitigation.
- **Blocks:** whether the risk blocks the production release (GO).
- **Owner:** who closes it.

## Open risks

| ID | Risk | Impact | Likelihood | Severity | Mitigation in place | Blocks GO | Owner, next step |
|---|---|---|---|---|---|---|---|
| R-01 | **Alert delivery unverified.** `OPS_ALERT_EMAIL` was empty on production (checklist O3); no test mail confirmed. | A broken queue, a failed AI job or a failed backup goes unnoticed until a user complains | High (nothing is configured) | High | `ops:failed-jobs` hourly; `ops:alert` (failed-job summaries, backup failures, `--test`), tested with 4 PHPUnit tests; failures are logged without loops | **Yes** | VPS owner: [handoff](vps-owner-handoff.md) block 3, then confirm the mailbox |
| R-02 | **Scheduled backup and off-server copy unverified.** The daily cron was never confirmed on the VPS; `OFFSITE_REMOTE` is not configured, so every dump shares the VPS failure domain. | Data written since the last deploy is lost on database damage; the VPS failing means losing everything | Medium | High (Critical for disaster recovery) | A backup before every migration (lock, space check, gzip test, checksum, retention), tested locally 2026-11-13; restore rehearsed 2026-11-13 (12.6 s, 0 orphans) | **Yes** (cron); **Yes for disaster recovery** (off-server) | VPS owner: handoff blocks 4 and 5 |
| R-13 | **Production MySQL credentials unverified.** `docker-compose.yml` still carries `root`/`secret` defaults (used only when the volume was first created). | A process on the host or in the Docker network could act as MySQL root | Unknown until checked | High (if the defaults are live) | MySQL bound to `127.0.0.1`; the app and the backup use the `.env` account; `verify-production.sh` §10 now checks all five database boundaries without printing secrets | **Yes** (until verified) | VPS owner: handoff blocks 2 and 6; engineering: move the compose values to variables |
| R-14 | **`main` has no branch protection** (no ruleset, 2026-11-14). | A direct or force push reaches `main` without review (it still deploys only after green CI, and `--ff-only` refuses diverged history) | Low | Medium | Deploy gate (CI success for the exact SHA, push events only; manual runs prove CI through the API) | No | Owner: handoff block 7 |
| R-15 | **Production health only partly verified this pass.** Deploy #58's SSH log (post-deploy check lines) could not be read from the browser pane; `verify-production.sh` was not run on the server. Indirect evidence: Deploy #58 green, which needs the post-deploy check to exit 0; `/up` 200; production serves the `bb4fa31` frontend. | Health claims rest on indirect evidence | Low | Medium | `post-deploy-check.sh` fails the deploy on a bad release; verdict summary in `verify-production.sh` | **Yes** (GO needs the actual run) | VPS owner: handoff blocks 1 and 2 |
| R-03 | **Live AI provider reliability unproven.** Browser and PHPUnit tests use test doubles (E2E scripted client, FakeLlmClient). | AI assists may fail or time out in production; the method still works by hand | Medium | Medium | Failure states, retry, stuck-job recovery, duplicate prevention (browser-tested with the double) | No (AI output is optional, always a proposal) | Product owner: one live pass per assist after release |
| R-05 | **CSP is report-only.** | An injected script would not be blocked by CSP | Low | Medium | React escaping; the other headers are live | No | Engineering: enforce after a clean report period |
| R-07 | **Platform operators see every organisation's users** (`User` is not tenant-scoped; AUD-14). | An operator account compromise exposes all user lists | Low | Medium | Operators cannot be demoted or removed by organisation admins (SEC-PR-03) | No | Product owner: confirm which production accounts are operators |
| R-08 | **Five Laravel 11 advisories with no 11.x fix**, listed in `composer.json` `config.audit.ignore` with reasons; CI blocks any other advisory. The fifth (Concurrency task output injection, published 2026-11-14) was caught by the blocking audit and reviewed: the app never uses Concurrency. | Exploitable only with `APP_DEBUG=true`, a signed route relying on the signature alone, an email value reaching a raw header, or a call to the Concurrency facade (none in the app) | Low | Medium | `APP_DEBUG=false` (checked by `verify-production.sh` §10); signed routes also check a token or hash; Symfony Mime 7.4.22+ | No | Engineering: Laravel 12 upgrade |
| R-09 | **Force merge without a policy check** (`DrivingForceController::merge`). | A custom role with `edit-driving-force` could merge forces it may not edit | Low (seeded roles pass the policy anyway) | Low | Seeded roles | No | Engineering: `authorize('update', $source)` |
| R-10 | **Migration `2026_11_11_000001` is not reversible by code.** | A rollback past it needs a database restore | Low | Medium | Pre-migration dump; runbook §5 | No (already on production) | Operator: restore, never `migrate:rollback` |
| R-16 | **Browser E2E is proven locally; the CI job is new.** 32/32 passed on the isolated stack on 2026-11-14; the `browser-e2e` CI job runs from the next push. | A CI-only problem (image build, timing on hosted runners) could show up there first | Medium | Low | The same `scripts/e2e/run.sh` locally and in CI; traces uploaded on failure | No | Engineering: watch the first CI run |
| R-17 | **The E2E stack is slower than production** (PHP's built-in server over a bind mount: ~1 s per page; generous timeouts). | Timing-related flakiness | Medium | Low | One retry in CI; explicit waits, no fixed sleeps in the specs | No | Engineering |
| R-18 | **Per-user rate limits vs. automated clients.** CI browser tests hit `429` at 120 and 60 requests per minute per user (2026-11-16). Limits are now configurable (`config/ops.php`); production keeps 120 and 60, and the E2E stack sets 6000. | A very fast real user, or heavy polling, could also see 429 | Low | Low | Defaults unchanged; `RateLimitingTest` covers the header and the 429 | No | Engineering: watch production logs for 429s |
| R-19 | **Server-Sent Events hold PHP-FPM workers.** Each open AI status stream holds one of 20 workers for up to 25 s. | Too many open streams could slow every other request | Low (cap) | Medium | At most `AI_SSE_MAX_STREAMS` (6) streams; above that 503 and the page polls; streams end after 25 s; locks expire on their own | No | Engineering: raise `pm.max_children` before raising the cap; watch for 503s on `/ai-status/stream` |

## Closed with evidence

- **R-06 DNS rebinding / SSRF (was Medium; required closure):** `OutboundGuard`.
  - It resolves once, requires every answer to be public (IPv4, IPv6 and IPv4-in-IPv6 forms), and pins the connection to the checked IP with `CURLOPT_RESOLVE` (TLS still verifies the host name).
  - Redirects are refused, https and port 443 only, and the response is capped at 5 MB.
  - Tests: `tests/Unit/OutboundGuardTest.php`, `tests/Feature/Security/OutboundRequestGuardTest.php`, `LlmCredentialSafetyTest.php`, with a mutation check (re-enabling redirects or disabling the guard makes them fail).
- **R-04 no browser E2E:** Playwright suite for workflows A–F, 32 tests, passed on a fresh isolated stack (`scripts/e2e/run.sh`, 469 s).
- **R-11 release information:** `~/foresight-releases.log`, written by `post-deploy-check.sh` since `bb4fa31`.
- **R-12 PI-07 period semantics:** deployed in `bb4fa31`; the production bundle shows "Periode penilaian".
- **Found and fixed by the browser E2E (2026-11-14):**
  - the G1 reason was never sent (`Show.jsx` dropped request data);
  - the narrative editor wiped typed text on any background refresh (`QuadrantCard.jsx`);
  - a failed network request gave no feedback (global Inertia `exception` toast);
  - the exercise create/edit dialog discarded unsaved input without asking.
- **Earlier closures** (SEC-PR-01..03, OPS-PR-01, DEP-PR-01..02, Symfony advisories): see the 2026-11-13 entries in PROGRESS session 100.
