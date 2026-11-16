# Final Verification Report — Foresight Radar

Final production readiness closure, 2026-11-14 (times WIB). It supersedes the report of 2026-11-13.

## 1. Release state

| Item | Value | Evidence |
|---|---|---|
| Latest `main` SHA | `bb4fa31` (merge of `134ba1a`: SSRF fix, PI-07, deploy gate, backup lock) | `git log origin/main` |
| Latest successful CI on `main` | CI #120 on `bb4fa31`: success | GitHub Actions |
| Latest deployed production SHA | `bb4fa31` (Deploy #58: success) | GitHub Actions run 43446355280; production serves the `bb4fa31` frontend (the `AnalysisFilters` chunk contains "Periode penilaian"); `/up` 200 |
| Deployment workflow result | success; "Verify CI passed for this commit" skipped as designed (`workflow_run`) | job steps read in GitHub |
| Post-deployment verification | **Indirect only.** Deploy #58's SSH step ends with `post-deploy-check.sh` and succeeded, so its checks exited 0. The step log itself could not be read in the browser pane. | known risk R-15 |
| Open PR | #27 (`d71ec32`, ESLint zero-warning gate), 6/6 checks green, not merged | GitHub |
| This closure pass | uncommitted on `development` (based on `d71ec32`); the user commits | `git status` |

## 2. Executed and passed (developer machine, Docker stack; PHPUnit on SQLite in memory)

| Check | Command | Result | Duration |
|---|---|---|---|
| Full PHPUnit (final tree) | `docker exec laravel_app php artisan test` | **746 passed, 0 failed**, 8,690 assertions, 0 skipped / risky / incomplete | 558 s |
| Larastan level 5 (whole code base) | `docker exec laravel_app ./vendor/bin/phpstan analyse --memory-limit=2G` | `[OK] No errors` | 300 s |
| Pint | `docker exec laravel_app ./vendor/bin/pint --test` | PASS, 506 files | – |
| ESLint, zero warnings | `npm run lint` (`--max-warnings=0`) | exit 0 | – |
| Production build | `npm run build` | exit 0 | – |
| npm audit | `npm audit --audit-level=critical` | exit 0 (4 moderate, 6 high, known: SheetJS/esbuild) | – |
| composer audit | `composer audit` | exit 0 with 5 reviewed Laravel 11 exceptions; the fifth was published on 2026-11-14 and **caught by the blocking audit** (R-08) | – |
| **Browser E2E, fresh isolated stack** | `bash scripts/e2e/run.sh` (start, `migrate:fresh` + `E2eSeeder`, Playwright Chromium, tear down) | **32 passed, 0 failed**, exit 0 | 469 s total (5.5 min of tests) |
| SSRF guard mutation check | re-enable redirects / turn the guard off, then run the tests | 4 and 1 failures, as expected; files restored | – |
| Backup script, concurrency and failure paths | WSL against the local dev DB (read-only dump) | two runs at once serialised; checksums OK; tampered dump, held lock and low disk each exit 1 | – |
| `verify-production.sh` dry run | WSL against the local stack | all verdicts produced; a git failure now gives SKIP, not a false PASS (bug fixed) | – |
| Shell syntax | `bash -n` on each `scripts/ops/*.sh` and `scripts/e2e/run.sh` | all OK | – |
| Dev DB | migrate:status, user count | 0 pending, 38 users; FPM reloaded, queue restarted | – |

## 3. Executed and failed, then fixed (regression evidence)

| Found by | Defect | Fix | Guard |
|---|---|---|---|
| Browser E2E C1 | The G1 reason typed in the confirm dialog was **never sent**: `Show.jsx`'s `visit(method, url)` dropped the data | `visit(method, url, data)` | E2E C1 asserts the reason in the decision log |
| Browser E2E C7 | The narrative editor **wiped typed text** whenever page data refreshed in the background (effect depended on the `quadrant` object) | `useEffectEvent`, reset only when the sheet opens or the quadrant id changes | E2E C7 (types after the sheet loads and checks the value) |
| Browser E2E F3 | A **failed network request gave no feedback** | global Inertia `exception` toast "Koneksi ke server gagal…" | E2E F3 |
| Browser E2E F1 | The exercise create/edit dialog **discarded unsaved input without asking** | `ProjectForm` reports `isDirty`; `FormDialog dirty` asks "Buang perubahan?" | E2E F1 |
| Local dry run | `verify-production.sh` reported PASS for the git checks when git failed | git failure becomes SKIP | dry run output |
| composer audit (blocking) | New Laravel advisory `PKSA-yxbx-3c9s-cjt9` | reviewed (Concurrency unused, CLI-only), listed with reason | CI audit step |

## 4. Security: SSRF and DNS rebinding

- **Implementation:**
  - `App\Support\Net\OutboundGuard` with the injectable `DnsResolver`;
  - `OutboundTarget::guzzleOptions()`;
  - used for every OpenAI-compatible client built from user or organisation settings (`guard_outbound`). The platform client from `.env` may still point at a local model server.
- **Guarantees:**
  - https only, port 443, no credentials in the URL;
  - IP literals and every DNS answer must be public: IPv4 private, loopback, link-local, CGNAT, reserved and documentation ranges are blocked, as are IPv6 loopback, ULA, link-local, multicast and documentation ranges, and IPv4-mapped, NAT64, 6to4 and Teredo addresses;
  - **one forbidden answer rejects the host**;
  - the name is resolved once, and the connection is **pinned to the checked IP** with `CURLOPT_RESOLVE` (TLS/SNI/Host keep the name, so certificates are still verified). The DNS answer checked is the one connected to; a later answer is never used for that request;
  - redirects are not followed;
  - the response is capped at 5 MB, with existing timeouts;
  - errors are user-safe, and the platform key is never sent to a custom URL.
- **Tests:**
  - `tests/Unit/OutboundGuardTest.php`: forbidden and public addresses, mixed answers, rebinding sequence, IPv6 pinning, NXDOMAIN, 14 bad URL forms, IP literals;
  - `tests/Feature/Security/OutboundRequestGuardTest.php`: request-time rebinding refused before any request; redirects to localhost, a private IP, metadata or a chain are not followed; the save and test endpoints; the platform client unguarded;
  - `tests/Feature/Security/LlmCredentialSafetyTest.php`;
  - DNS is controlled by `FakeDnsResolver`, so no real name is resolved and no external host is contacted.
- **Result:** implemented and tested. Known limitation: none for the AI path. Other outbound calls go to fixed hosts (World Bank, Anthropic).

## 5. Browser E2E (Playwright 1.65, Chromium; isolated stack `docker-compose.e2e.yml`)

| Workflow | Spec | Tests | Covers |
|---|---|---|---|
| A. Authentication and tenant isolation | `auth.spec.js` | 6 | login, logout, invalid credentials, expired session, viewer refused an admin page (403), viewer cannot create, organisation A cannot list, open (404) or read the data of organisation B |
| B. Exercise lifecycle | `exercise-journey.spec.js` B1–B3 | 3 | create, edit and save, find again, archive (badge, "Aktifkan kembali") |
| C. Nine steps and gates | `exercise-journey.spec.js` C1–C11 | 11 | step 2 locked before G1; G1 with reason; G2; G3 refused while library scores are unreviewed; G4 (two axes); G5; condition; four narratives and G6; implication; response with fit and G7 with reason; owned, discriminating indicator (step 9 done); reopening G5 locks steps 5–9, flags work, withdraws G6/G7 (decision log) |
| E. Monitoring | `exercise-journey.spec.js` E1–E4 | 4 | observation below the threshold (no event); crossing creates exactly one system-decided event; one event per crossing observation, kept after reload; the crossing flags the response for review with reasons |
| D. AI generation | `ai.spec.js` | 3 | pending state, no duplicate submission while pending, completion persisted across reload; failure message and retry; another organisation cannot trigger (403/404) or read |
| F. Recovery and operational UX | `recovery-ux.spec.js` | 5 | unsaved-changes guard (keep or discard), empty state guidance, network failure feedback without a change, refresh during a running AI job, expired session during an action (419 or login) |

AI in the E2E stack is `E2eScriptedLlmClient`, a test double behind a real queue worker. **It proves the app's handling, not a real provider** (R-03). Recovery of a job lost with its worker needs the 15-minute stale window and is covered by PHPUnit (PI-10), not by the browser. The double is unavailable outside `APP_ENV=e2e` (`E2eScriptedClientGuardTest`).

## 6. Requires the VPS owner (not executed; nothing fabricated)

Prepared in [vps-owner-handoff.md](vps-owner-handoff.md), with exact commands and expected output:

| Requirement | Block | Status |
|---|---|---|
| Release SHA, release log, Deploy #58 SSH log | 1 | **BLOCKED**: block 1 handed over 2026-11-14, no output received yet |
| `verify-production.sh` verdicts (containers, config, debug, DB, migrations, queue, scheduler, backups, alert configuration, disk) | 2 | **BLOCKED**: needs the new script on the server (after merge and deploy) |
| Alert delivery end to end | 3 | **BLOCKED**: `ops:alert` built and tested (PHPUnit); production mail and recipient not configured |
| Scheduled backup | 4 | **BLOCKED** |
| Off-server copy and restore from it | 5 | **BLOCKED** (needs the owner's storage account) |
| MySQL credentials and least privilege | 6 | **BLOCKED** (verdicts ready in §10) |
| Branch protection / environment protection | 7 | **FAIL**: no ruleset on `main` (R-14) |

## 7. Restore evidence

The latest rehearsal is from **2026-11-13**, in isolation (`docs/audit/recovery-rehearsal-report.md`):
- restore 12.6 s; 55/55 tables; 0 orphans;
- 37/37 pages; code rollback 19 s.

Not repeated in this pass. No migration was added after `bb4fa31`, so the schema is unchanged. A restore from an off-server copy has never been rehearsed (block 5).

## 8. Observability

| Signal | State |
|---|---|
| Application errors | daily log files (PRD-04); no secrets or prompts logged (audited) |
| Queue failures | `ops:failed-jobs` hourly, mailed by `ops:alert` once `OPS_ALERT_EMAIL` is set |
| Scheduler | `verify-production.sh` §10 checks its runs |
| Database connectivity | `/up`, the post-deploy check, verdicts |
| Disk | verdict (85 %) and the backup free-space precheck |
| Backup failures | `ops:alert` mail, syslog, `--check` |
| Deployment failures | GitHub run status (mail to the actor) |
| AI failures | `ai_generation_runs` rows and `llm.error` log lines (no prompt text) |

Alert **delivery** is unverified (R-01).

## 9. Final release recommendation

**NOT PRODUCTION READY — NO-GO** (for now).

All code-side requirements are implemented and verified with evidence:
- SSRF and DNS rebinding closed;
- critical journeys pass in a real browser;
- the deploy gate is tight;
- backups are safe to run concurrently.

But the release gate requires actual production evidence that does not exist yet:
- the production verification run;
- the MySQL credentials check;
- alert delivery;
- the scheduled backup (and an off-server copy for disaster recovery).

Under the agreed rules these may not be waived as "conditional".

The path to GO:
1. merge PR #27 and this pass, and let CI (now including browser E2E) and the gated deploy run;
2. the VPS owner runs handoff blocks 1–6 and fixes any FAIL;
3. enable the `main` ruleset (block 7);
4. re-run `verify-production.sh` until it shows 0 FAIL, and update the matrix.
