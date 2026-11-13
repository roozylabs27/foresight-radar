# Final Verification Report — Foresight Radar

Production readiness remediation and final verification, 2026-11-13 (times WIB).

**Baseline release:**
- `main` `81fa161` (PR #25 merge); its tree is identical to `development` `de6765d`;
- CI #118 passed on `81fa161`, and Deploy #57 deployed it;
- no open pull requests; working tree clean at the start.

**Release candidate:** `81fa161` plus the uncommitted changes listed in §2. The user commits; the SHA is assigned at commit. Nothing was deployed, committed or pushed in this pass.

## 1. Commands executed and results

All commands ran on the developer machine: Docker stack `laravel_app` (PHP 8.3); PHPUnit on SQLite in memory; Node 22.

| # | Tree | Command | Exit | Result | Duration |
|---|---|---|---|---|---|
| 1 | baseline `81fa161` | `docker exec laravel_app php artisan test` (full) | 0 | **641 passed, 0 failed**, 8,499 assertions, 0 skipped / risky / incomplete | 943 s |
| 2 | baseline | `npm run lint` | 0 | 0 errors, 57 warnings (known debt) | 18 s |
| 3 | baseline | `npm audit --audit-level=critical` | 0 | 0 critical (4 moderate, 6 high, known: SheetJS / esbuild) | 15 s |
| 4 | baseline | `composer audit` | 6 | **12 advisories in 6 packages:** laravel/framework ×4, symfony/http-foundation ×4, http-kernel, mime, routing, yaml | 4 s |
| 5 | remediated (mid) | full PHPUnit | 1 | 659 passed, **1 failed** (`ReasoningEngineTest::test_trend_covers_the_last_twelve_months_by_default`: the fixture backdated only `created_at`; fixed in §2 E) | 492 s |
| 6 | remediated | `npm run lint` / `npm run build` / `npm audit --audit-level=critical` | 0 / 0 / 0 | 0 errors, 57 warnings / built / 0 critical | 16 s / 41 s / – |
| 7 | remediated | `pint --test` | 1 → 0 | 1 style issue (`OrganizationController`), fixed with Pint; then PASS, 493 files | – |
| 8 | final, before the last two type edits | full PHPUnit | 0 | **660 passed, 0 failed**, 8,568 assertions, 0 skipped / risky / incomplete | 507 s |
| 9 | same | `phpstan analyse` (Larastan level 5, whole code base) | 1 | 1 error: `ReasoningEngineService:493` reads `assessed_at` on an untyped Model | 372 s |
| 10 | final | Larastan on the 3 changed files after typing the closure and `@return Builder<DrivingForceRating>` | 0 | No errors | – |
| 11 | final | `php artisan test tests/Feature/Reasoning tests/Feature/Reliability/FirmAssessmentPeriodTest.php` | 0 | 38 passed, 253 assertions | – |
| 12 | final | `composer audit` | 0 | 0 unlisted advisories (4 Laravel 11 advisories listed with reasons, R-08) | – |
| 13 | final | `phpstan analyse` (whole code base) and `pint --test` | 0 / 0 | **No errors** / PASS, 493 files | 337 s / – |

Run 8 covers the final code except a closure parameter type and a docblock in `ReasoningEngineService` / `ForesightReportingService`. Run 11 covers both.

## 2. Changes in this pass

| ID | Area | Change | Regression tests (red → green) |
|---|---|---|---|
| **A. SEC-PR-01** (Critical) | AI connection test | `POST /settings/llm/test` filled in the **platform AI key** when the user sent none and posted it to the user-chosen `base_url`. Reproduced: the attacker URL answered 200. The fallback is removed from the controller; `LlmClientResolver` lends the platform key only to the default Gemini host | `LlmCredentialSafetyTest`: 3 tests |
| **B. SEC-PR-02** (High) | SSRF | New rule `App\Rules\PublicHttpsUrl`: https only; no private, loopback, link-local or reserved IP literals; no `localhost`, single-label names (Docker services) or internal suffixes. Applied to the personal test/save and the organisation AI base URL | `LlmCredentialSafetyTest`: 8 data sets × 2 endpoints, plus 1 positive case |
| **C. SEC-PR-03** (High) | Members | `TenantMemberController::updateRole` / `removeMember` now check `UserPolicy::update`, so an organisation admin cannot demote or remove a platform operator | `TenantMemberAuthorizationTest::test_admin_cannot_demote_or_remove_a_platform_operator_in_the_organisation` |
| **D. PI-07** (High, data integrity) | FIRM period | New column `driving_force_ratings.assessed_at` (migration `2026_11_13_000004`, additive, indexed), set when a rating is created or a scored field changes (the same `TRACKED` fields as the rating history; `touch()` or an unchanged value is not an assessment). Backfill: latest `driving_force_rating_histories.changed_at`, else `created_at`. `baseApprovedRatingQuery` filters on it, so Dashboard, Radar, Prioritizing, Registered List and the export follow; the dashboard trend groups by assessment month. The UI label is "Periode penilaian" with an explanation | `FirmAssessmentPeriodTest`: 6 tests (inside/outside, reassessment, non-assessment save, boundaries 00:00:00 / 23:59:59, all reports consistent, backfill with and without history) |
| **E.** | Test fixture | `ReasoningEngineTest` backdated a rating's `created_at` only; it now also backdates `assessed_at` (the same "rated 3 months ago" intent). Assertions unchanged | – |
| **F. DEP-PR-01** (High) | Deploy gate | `deploy.yml`: `workflow_run` must be a successful **push** run on `main`; `workflow_dispatch` must find a successful CI push run on `main` for the exact SHA through the API, or it stops before checkout; `permissions: contents/actions read` | Not executable locally; YAML parsed |
| **G. DEP-PR-02** (Medium) | Post-deploy | New `scripts/ops/post-deploy-check.sh`: HEAD = SHA, 5 containers running, no pending migration, `/up` returns 200 through nginx, a release log. Called as the last deploy step | `bash -n`; output format of `migrate:status` checked locally |
| **H. OPS-PR-01** (High) | Backups | `backup-database.sh`: `flock` (deploy and cron cannot delete each other's `.partial`), free-space precheck, `gzip -t`, `.sha256` sidecar verified by `--check`, sidecar removed with retention | Run locally against the dev DB in WSL: two concurrent runs (B waited for A), both checksums OK; `--check` OK; tampered dump → exit 1; held lock → exit 1 after timeout; low disk → exit 1, no file |
| **I.** | Dependencies | 20 Symfony packages 7.4.17–21 → 7.4.22/23 (patch/minor, within constraints): fixes 8 advisories | Full suite run 8 on the updated vendor |
| **J.** | CI | `composer audit` now **blocks**; the 4 Laravel 11 advisories without an 11.x fix are listed in `composer.json` `config.audit.ignore` with reasons | `composer audit` exit 0 locally |
| **K.** | Ops visibility | `verify-production.sh` also prints the release log, the checksum sidecar and `backup --check` | `bash -n` |

## 3. Security findings

Read-only audit of routes, nested bindings, validation, members, exports, AI keys, jobs and scheduled commands (§1 boundary list in the readiness matrix).

| Finding | Severity | Status |
|---|---|---|
| Platform AI key sent to a user-chosen host (SEC-PR-01) | Critical | **Fixed** (A) |
| SSRF through the AI base URL (SEC-PR-02) | High | **Fixed** for literals and names (B); DNS rebinding remains R-06 |
| Organisation admin can demote or remove an operator (SEC-PR-03) | High | **Fixed** (C) |
| Operators list every organisation's users (F4, AUD-14) | Medium | Accepted; R-07 |
| Merge without policy (F5) | Low | Open; R-09 (not exploitable with the seeded roles) |
| Laravel 11 advisories without a fix | Medium | Listed; R-08 |
| Default MySQL credentials in `docker-compose.yml` | High if live | **Unverified on production**; R-13 |
| Cross-tenant IDOR through route binding, nested children or `exists:` rules | – | None found. `EnforceTenantContext` runs before `SubstituteBindings` (`bootstrap/app.php:44-47`); a missing tenant gives no rows; children are checked or bound with `scopeBindings`; all related-id rules use `TenantRule`; `RunInTenantContext` refuses jobs of a missing or inactive tenant; `failed()` handlers create no rows; stored keys are never sent to the browser |

## 4. E2E results

- **Browser E2E for workflows A–F: not run in this pass.** The repository's runner (`e2e`, agentic) needs credentials and spends AI tokens; CI runs only the login smoke.
- What exists:
  - HTTP journeys `ForesightExerciseJourneyTest` (steps 1→9) and `TenantIsolationJourneyTest` (part of the 660);
  - per-change local browser checks in sessions 98–99;
  - the production E2E of 2026-11-12.
- Local browser, after the user signed in: the Foresight Radar filter shows "Periode penilaian" with its explanation tooltip; the radar data endpoint returns 200 (26 forces for the default period); no failed requests on the page.

## 5. Deployment, recovery and runtime checks

- **Deployment pipeline:** CI #118 → Deploy #57 succeeded for `81fa161` (read in GitHub Actions). The new gate (F, G) runs from the next deploy and is **not yet exercised**.
- **Recovery:** the restore rehearsal of 2026-11-13 (`docs/audit/recovery-rehearsal-report.md`): 12.6 s, 55/55 tables, 0 orphans, 37/37 pages, code rollback 19 s. Not repeated in this pass.
- **Production runtime, read-only, from a browser, 2026-11-13:**
  - `https://foresight-radar.id/up` → 200;
  - HSTS `max-age=31536000; includeSubDomains`, CSP-Report-Only, Permissions-Policy, `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy`;
  - no `X-Powered-By`; `server: nginx` without a version;
  - `/.env`, `/.git/config`, `/composer.json` and `/storage/logs/laravel.log` → 404;
  - session cookie not readable by JavaScript; `http://` ends on `https://` over h2 with a trusted certificate.
- **Not verified on production in this pass** (needs the VPS owner):
  - `verify-production.sh`: `APP_DEBUG`, `session.secure`, queue and scheduler, migrations, disk;
  - the daily backup cron and off-server copy;
  - `OPS_ALERT_EMAIL` and mail delivery;
  - the MySQL credentials (R-13);
  - the certificate expiry date.

## 6. Final static checks

On the final tree, Larastan level 5 (whole code base): `[OK] No errors` (337 s). Pint `--test`: PASS, 493 files. `composer audit`: exit 0. `npm audit --audit-level=critical`: exit 0. ESLint: 0 errors. Build: passed. Full PHPUnit: 660/660 (row 8; the two later edits are covered by row 11).

## 7. Recommendation

**CONDITIONAL GO** for a controlled release to the first organisation, once the remediated code is committed, passes CI and is deployed, and the VPS owner closes R-01, R-02 (cron) and R-13. **NO-GO for a public launch** until R-01 to R-08 and R-13 are closed and browser E2E for workflows A–E exists. Details: [production-readiness-matrix.md](production-readiness-matrix.md).
