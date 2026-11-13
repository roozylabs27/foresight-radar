# Deployment and Recovery Runbook — Foresight Radar

Production readiness remediation, 2026-11-13. This runbook is the entry point. The detailed procedures stay where they were rehearsed:
- [docs/guidelines/production-operations-runbook.md](../guidelines/production-operations-runbook.md), referred to below as "Ops §n";
- [docs/guidelines/deployment-and-rollback.md](../guidelines/deployment-and-rollback.md).

Production: one VPS (Docker Compose: `laravel_app` PHP-FPM, `laravel_nginx`, `laravel_mysql`, `laravel_queue`, `laravel_scheduler`), project directory `/var/www/foresight-radar`, backups in `~/foresight-backups`.

## 1. Pre-deployment checklist

1. The change is merged into `main` through a pull request, and **CI & Security Quality Gate** passed for the merge commit (push to `main`). CI runs:
   - Pint, Larastan level 5 and the full PHPUnit suite on MySQL;
   - `composer audit` (blocks on any advisory not listed in `composer.json` `config.audit.ignore`);
   - `npm audit --audit-level=critical`, ESLint and the production build;
   - the e2e login smoke.
2. Read the migrations of the release. For each one, note whether old code still runs on the new schema (additive, nullable: yes) and whether `down()` loses data. A migration that moves or deletes data needs a restore to roll back (§6).
3. Check that a recent valid backup exists (`bash scripts/ops/backup-database.sh --check`, Ops §4) and that the disk has room (`df -h`).
4. Know the current release (`tail -n 3 ~/foresight-releases.log`, or `git rev-parse --short HEAD` on the server).

## 2. Deployment (automatic)

`.github/workflows/deploy.yml` runs only:
- after CI succeeded for a **push to `main`** (`workflow_run`: conclusion success, event push, head branch main); or
- when started by hand from `main` (`workflow_dispatch`). The first step then queries the GitHub API and **refuses to deploy** unless a successful CI push run exists on `main` for exactly that commit.

Runs never overlap (`concurrency: deploy-production`). Steps:

1. Check out the CI-tested SHA, then `npm ci`, the build, and packing of `public/build`.
2. Validate the secrets; upload the build archive.
3. On the VPS:
   - record `PREVIOUS_SHA`, then `git merge --ff-only <sha>` (refuses diverged history);
   - certificate paths;
   - `docker compose up -d --remove-orphans`.
4. Extract the assets; `composer install --no-dev`.
5. `scripts/ops/backup-database.sh <previous sha>`: one run at a time (lock), a free-space check, `.partial` until "Dump completed", gzip check, `.sha256` sidecar, optional verified off-server copy, retention. **A failed backup stops the deploy before migrations.**
6. `php artisan migrate --force`. A failure stops the deploy with the migration error in the log.
7. `optimize:clear`, `optimize`, `view:cache`, and ownership of `storage` and `bootstrap/cache`.
8. `scripts/ops/apply-runtime-config.sh`: reload PHP-FPM (opcache) or recreate it; test nginx, then reload or recreate it; **fails unless the containers see the repository's config** (hash check, OPS-01).
9. Restart `laravel_queue`, so the workers load the new code.
10. `scripts/ops/post-deploy-check.sh <sha> <previous>`: HEAD equals the deployed SHA, all 5 containers run, no pending migration, `GET https://<APP_URL host>/up` returns 200 through nginx and PHP-FPM, and a line goes to `~/foresight-releases.log`. **A failed check fails the deploy run.** The release stays live; decide between fixing forward and rolling back (§5).

## 3. Post-deployment smoke test (non-destructive)

1. GitHub: the deploy run is green, and its log shows "Deploying <sha>; previous release: <sha>" and the `OK` lines of the post-deploy check.
2. On the VPS: `bash scripts/ops/verify-production.sh`, read-only. Check:
   - release and release log;
   - containers;
   - the mounted config hashes;
   - `app.debug=false`, `session.secure=true`, `session.http_only=true`;
   - `OPS_ALERT_EMAIL` set;
   - database connection, migrations and pending migrations;
   - queue and failed jobs; scheduler runs;
   - log errors;
   - backups and `backup --check`.
3. In a browser (not curl, AGENTS.md §11):
   - `https://foresight-radar.id/login` loads;
   - the response carries HSTS, `X-Content-Type-Options`, `X-Frame-Options`, Referrer-Policy, Permissions-Policy and CSP-Report-Only, with no `X-Powered-By`;
   - `/.env` and `/.git/config` give 404.
4. With an authorised test account only: sign in, open the dashboard, one exercise overview and one report. **Create no production data.**

## 4. Backup

- **Before every migration:** automatic (§2 step 5).
- **Daily:** the cron from Ops §4b (01:15 WIB backup and a 6-hourly `--check`). **Not confirmed as installed on the VPS** (known risk R-02).
- **Off-server copy:** `OFFSITE_REMOTE` with rclone `crypt` (Ops §4b). **Not configured** (R-02).
- **Verify a backup:** run `backup-database.sh --check`. It checks age (≤ 26 h), the gzip stream, the completion marker and the `.sha256` sidecar.
- **Locking:** the deploy and cron runs share `~/foresight-backups/.backup.lock`. A second run waits up to `LOCK_WAIT_SECONDS` (900 s), then fails without touching the other run's files. Tested locally on 2026-11-13.

## 5. Rollback

Decide by what the bad release changed:

| Bad release changed | Do |
|---|---|
| Code only, no migration | Code-only rollback, Ops §6: check out the previous SHA from `~/foresight-releases.log`; `optimize:clear` and `optimize`; reload FPM; restart the queue. If `resources/` or `package*.json` changed, redeploy the previous commit's assets (Ops §6 step 3). |
| Additive migrations only (new nullable columns or tables, e.g. `2026_11_13_000001…000004`) | Code-only rollback is enough: old code ignores the new columns. Leave the schema; the next release reuses it. |
| A migration that moves or deletes data (e.g. `2026_11_11_000001`, organisation-owned dimensions) | Database recovery, Ops §7: `artisan down`; restore the dump labelled with the previous SHA; check out the previous code; `migrate:status` shows nothing pending; `artisan up`. **Never `migrate:rollback`.** Data written after the dump is lost. |
| Runtime config (nginx, PHP ini) | Check out the previous SHA, then `bash scripts/ops/apply-runtime-config.sh`; it recreates the container when the file differs. |

After any rollback:
- restart `laravel_queue` (queued jobs serialise models, and new job classes do not exist in old code);
- run §3;
- fix forward on `main`;
- `git checkout main` on the server before the next deploy.

## 6. Restore (disaster recovery)

- **Rehearsal (monthly):** `scripts/ops/restore-rehearsal.sh` restores a production dump into the isolated `docker-compose.recovery.yml` stack, never into production (Ops §5). Last result, 2026-11-13: restore 12.6 s, 55/55 tables, 0 orphans, 37/37 pages, code rollback 19 s. See `docs/audit/recovery-rehearsal-report.md`.
- **RPO, measured capability:** the time since the last dump. Today that is the last deploy, because the daily cron is not confirmed. **Target:** ≤ 24 h with the daily cron, plus an off-server copy.
- **RTO, measured:** restore ≈ 21 s over a populated 4 MB database, code rollback ≈ 19 s. **Target:** ≤ 1 h end to end, including the decision and the checks.

## 7. Incident response

1. Confirm: run `verify-production.sh` sections 3, 7 and 8 (containers, queue and failed jobs, log errors), and load `/up` in a browser.
2. Contain:
   - `php artisan down` for data damage;
   - `docker restart laravel_queue` for a stuck worker;
   - stuck AI or extraction jobs recover themselves after the stale window (PI-10, `StaleJob`).
3. Recover with §5 or §6. Record the start and end times.
4. Validate with §3 and §8.
5. Write the incident into `PROGRESS.md`: cause, timeline, data lost (if any), and the follow-up.

## 8. Recovery validation

After a restore or rollback, all must hold:
- `migrate:status`: nothing pending for the running code;
- row counts of `tenants`, `users`, `scenario_planning_projects`, `driving_forces` and `signals` match the dump (`verify-production.sh` §6);
- no orphan foreign keys (the queries of `restore-rehearsal.sh`);
- `/up` returns 200;
- sign-in works, and one exercise and one FIRM report open;
- the queue processes a job (no growing `jobs` table);
- `backup-database.sh --check` passes after the next scheduled dump.
