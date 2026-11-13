# Production Operations Runbook — Foresight Radar

Verified 2026-11-13 ([verification](../audit/production-verification-report.md), [backup](../audit/backup-integrity-report.md), [recovery](../audit/recovery-rehearsal-report.md)). Deployment details and the short rollback guide: [deployment-and-rollback.md](deployment-and-rollback.md).

Each procedure states how far it was verified:

- **run on production:** executed against production, read-only;
- **rehearsed in isolation:** executed against a restored copy of the production backup;
- **reviewed only:** checked against the code, not executed.

## Infrastructure facts (verified 2026-11-12)

- VPS: Linux. Project in `/var/www/foresight-radar`, branch `main`, owner `roozylabs`.
- Containers (from `docker-compose.yml`):
  - `laravel_app`: PHP-FPM 8.3;
  - `laravel_queue`: `queue:work --tries=1 --timeout=310 --sleep=3 --max-time=3600`;
  - `laravel_scheduler`: `schedule:work`;
  - `laravel_nginx`;
  - `laravel_mysql`: MySQL 8.0.46.
- Database `foresight_radar`: 55 InnoDB tables, about 4 MB. The application user (from `.env`) has ALL privileges on it. The MySQL root password in `docker-compose.yml` does **not** work on production.
- `docker/nginx/default.conf` and `docker/php/local.ini` are bind-mounted as single files. After `git` changes them, a reload is not enough and the container must be recreated (§3).
- Backups: `~/foresight-backups/db-<UTC time>-<previous release>.sql.gz`, written only by deploys.
- Logs: `storage/logs/laravel-YYYY-MM-DD.log`, kept 14 days.

## 1. Health check — run on production

From the project directory (read-only; prints no secrets or records):

```bash
bash scripts/ops/verify-production.sh "$PWD"
```

Healthy means:
- §2: HEAD equals the last deployed `main` commit, and there are no uncommitted files;
- §3: all five containers Up, with restart counts not growing;
- §4: host and container hashes equal, `expose_php` Off;
- §6: no pending migrations;
- §7: 0 waiting jobs older than a few minutes, no new failed jobs, and `ops:failed-jobs` runs every hour;
- §9: the latest backup passes `gzip -t` and ends with "Dump completed".

From a browser: `https://foresight-radar.id/login` loads, and the response headers show HSTS, `Content-Security-Policy-Report-Only`, `Permissions-Policy`, `Server: nginx` without a version, and no `X-Powered-By`.

## 2. Verify a deployment — run on production

1. In GitHub → Actions, the "Deploy to VPS" run was triggered by `workflow_run` for the commit you merged, and CI for that commit is green.
2. In the log, check that these lines appear:
   - `Database backup: … (N bytes, sha256 …)`;
   - `Nothing to migrate` or the migrations you expected;
   - `nginx live config: 2 Permissions-Policy line(s)`.
3. Run §1 and compare HEAD with the merged commit.

## 3. Apply changed nginx or PHP configuration — reviewed; the guard is tested locally (deploy step 4b/4c)

`scripts/ops/apply-runtime-config.sh` runs from the project directory:

```bash
cd /var/www/foresight-radar && bash scripts/ops/apply-runtime-config.sh
```

It checks that `laravel_app` and `laravel_nginx` are running, then:
- **PHP:** if the container's `local.ini` equals the repository's, it reloads PHP-FPM; otherwise it recreates `app`, `queue` and `scheduler`.
- **nginx:** it tests the repository's config with `docker compose run --rm --no-deps -T nginx nginx -t`. If the container's file equals the repository's, it reloads nginx; otherwise it recreates `nginx`.
- **Verify:** it compares the hashes again and exits 1 if a container still sees a different file. It prints `nginx live config: N Permissions-Policy line(s)` and `PHP expose_php`.

Each recreate costs a few seconds of downtime for that service. Deploy #55 (2026-11-13) stopped exactly where this logic sat inline in `deploy.yml`. The deploy now calls this script in one line.

## 4. Verify a backup — run on production; integrity rehearsed in isolation

`bash scripts/ops/verify-production.sh` §9 shows the latest file's size, sha256, `gzip -t`, completion line and tables. A backup is usable only when:

- the file is larger than about 20 KB;
- `gzip -t` is ok;
- the last line is `-- Dump completed on …`;
- it has 55 `CREATE TABLE` statements (or the current table count).

## 4b. Scheduled and off-server backups (OPS-02, OPS-03, OPS-11) — script tested locally; installing it on the VPS needs the owner

`scripts/ops/backup-database.sh` is the one backup script. The deploy calls it with the previous release as label; cron calls it with `scheduled`.

**What it does:**
- reads the app credentials from `.env`;
- writes owner-only files, as `.partial` until mysqldump prints "Dump completed";
- logs size, sha256 and duration;
- optionally copies the dump off the server and verifies the copy byte for byte;
- deletes dumps older than `RETENTION_DAYS` (14), but never below `KEEP_MIN` (3) valid ones, and only after a successful dump;
- optionally pings a dead man's switch.

`--check` fails when the newest valid dump is older than `MAX_AGE_HOURS` (26) or incomplete.

**Settings** (optional; shared by the deploy and cron): `~/.config/foresight-backup.env`, mode 600. Keys `OFFSITE_REMOTE`, `BACKUP_PING_URL`, `RETENTION_DAYS`, `KEEP_MIN`, `MAX_AGE_HOURS`, `BACKUP_DIR`.

**Install (once, as `roozylabs` on the VPS; server clock is UTC):**

1. Try it by hand from the project directory. It should print `Database backup: … sha256 …`, then `--check` should print `OK`:

   ```bash
   cd /var/www/foresight-radar && bash scripts/ops/backup-database.sh scheduled
   ```

   ```bash
   cd /var/www/foresight-radar && bash scripts/ops/backup-database.sh --check
   ```

2. Run `crontab -e` and add these two lines: a daily backup at 01:15 WIB (18:15 UTC) and a freshness check every 6 hours:

   ```
   15 18 * * * cd /var/www/foresight-radar && bash scripts/ops/backup-database.sh scheduled >> $HOME/foresight-backups/backup.log 2>&1
   45 */6 * * * cd /var/www/foresight-radar && bash scripts/ops/backup-database.sh --check >> $HOME/foresight-backups/backup.log 2>&1
   ```

   Failures are also written to syslog with the tag `foresight-backup`. Read them with `journalctl -t foresight-backup` or in `~/foresight-backups/backup.log`.

**Off-server copy (choose one; each needs your account):**

- **a. Object storage with rclone and client-side encryption (recommended).** For example Backblaze B2, Cloudflare R2, S3, or Google Drive.
  1. `sudo apt install rclone`.
  2. Run `rclone config`: create the storage remote (for example `offsite-raw`), then a `crypt` remote on top of it (for example `foresight-offsite`) with a password you store in your password manager.
  3. Put `OFFSITE_REMOTE="foresight-offsite:db"` in `~/.config/foresight-backup.env`, then `chmod 600` that file.
  4. Run step 1 again. It must print `Off-server copy verified: …`.

  Once set, a failed off-server copy also stops a deploy before migrations. Remove the key to deploy without it.
- **b. Provider snapshots** of the VPS (daily). This covers the whole disk but is not a separate copy of the dumps, and it is restored only with the provider's tooling.
- **c. A pull from another machine** (as in the rehearsal): a scheduled `scp` of the newest `db-*.sql.gz` to an office machine or NAS.

**Restore from the off-server copy (rehearse it once):** run `rclone copy foresight-offsite:db/<file> <local folder>`, then follow §5 with that file.

**Dead man's switch (optional, OPS-11):** create a check at a heartbeat service (for example healthchecks.io, period 1 day, grace 2 h) and set `BACKUP_PING_URL` in the settings file. You are then notified when no successful backup arrives.

## 5. Restore rehearsal — rehearsed in isolation (repeat at least monthly)

On a workstation with Docker:

1. Copy a backup from the server. Check the host key fingerprint on first use:

   ```bash
   scp roozylabs@<server>:/home/roozylabs/foresight-backups/<file> <local folder outside the repository>/
   ```

2. Start the isolated stack (its own database, network and configuration; read-only code):

   ```bash
   docker compose -p foresight-recovery -f docker-compose.recovery.yml up -d
   ```

3. Restore the backup and run the checks. The script refuses any target outside the recovery project:

   ```bash
   bash scripts/ops/restore-rehearsal.sh <local file> <sha256 from the server>
   ```

4. Optional: open `http://localhost:8090` with a recovery-only account created in the restored copy, and check the exercise pages.

5. Remove the stack and its data, then delete the local copy of the backup:

   ```bash
   docker compose -p foresight-recovery -f docker-compose.recovery.yml down -v
   ```

Restored data is production data: keep it on the workstation only for the rehearsal.

## 6. Code-only rollback (no migration in the bad release) — rehearsed in isolation, not executed on production

1. Find the previous release: the deploy log line `previous release: <sha>`, the newest backup's name, or `git reflog -n 5` on the server.
2. On the server:

   ```bash
   cd /var/www/foresight-radar && git checkout --detach <previous-sha>
   ```

   ```bash
   docker exec laravel_app php artisan optimize:clear && docker exec laravel_app php artisan optimize
   ```

   ```bash
   docker exec laravel_app sh -c 'kill -USR2 1' && docker restart laravel_queue
   ```

3. Frontend: if `resources/js`, `resources/css` or `package*.json` changed between the two releases, the live `public/build` belongs to the bad release. Re-run "Deploy to VPS" for the previous commit, or build it and copy `public/build`.
4. Run §1. Fix forward on `main`, then `git checkout main` on the server before the next deploy, which fast-forwards it.

## 7. Database recovery (a bad migration or data damage) — rehearsed in isolation, not executed on production

1. `docker exec laravel_app php artisan down`
2. Pick the dump taken just before the bad deploy: the newest `~/foresight-backups/db-*-<previous-sha>.sql.gz`. Check it as in §4 and note its time: **everything written after that time is lost** (the rehearsal confirmed this).
3. Restore with the application user. The dump drops and recreates each table. Rehearsed over a populated database in 20.8 s for 4 MB:

   ```bash
   cd /var/www/foresight-radar
   env_value() { grep -E "^$1=" .env | tail -n 1 | cut -d= -f2- | sed -e 's/^"\(.*\)"$/\1/' -e "s/^'\(.*\)'$/\1/"; }
   gunzip -c ~/foresight-backups/<file> | docker exec -i -e MYSQL_PWD="$(env_value DB_PASSWORD)" laravel_mysql mysql --protocol=TCP -h 127.0.0.1 -u"$(env_value DB_USERNAME)" "$(env_value DB_DATABASE)"
   ```

   The `env_value` helper is the one `deploy.yml` uses for the backup. The password goes through `MYSQL_PWD`, not the command line.

4. Check the code, as in §6.
5. `docker exec laravel_app php artisan migrate:status`: no pending migration for that code.
6. `docker exec laravel_app php artisan up`, then run §1.

Do not use `migrate:rollback` for recovery: some migrations move data, and their `down()` refuses lossy changes.

## 8. Queue recovery — reviewed only

- Worker stopped or stuck: `docker restart laravel_queue`. It also restarts itself every hour (`--max-time=3600`), and the container has `restart: unless-stopped`.
- Jobs waiting too long (§1 §7 shows the oldest `available_at`): check `docker logs --since 1h laravel_queue`.
- Lost QUEUED or PROCESSING sources and indicators become retryable from the page after the stale window (`StaleJob`).

## 9. Failed-job investigation — alert path rehearsed in isolation

1. The hourly `ops:failed-jobs` logs `Queued jobs failed {"count":…,"jobs":{…}}` in `storage/logs/laravel-<date>.log`, and mails `OPS_ALERT_EMAIL` when that is set. **On production it is empty (OPS-07), so failures appear only in the log.**
2. On the server:

   ```bash
   docker exec laravel_app php artisan queue:failed
   ```

3. Read the exception for one job:

   ```bash
   docker exec laravel_app php artisan tinker --execute 'echo DB::table("failed_jobs")->latest("id")->value("exception");'
   ```

   The exception text can contain tenant data; do not paste it into tickets.

4. AI jobs (`tries=1`) mark their own record failed and can be retried from the page. Only retry from the CLI (`queue:retry <uuid>`) when the cause is fixed.
5. Remove handled entries with `queue:forget <uuid>`. Failed jobs are kept until then; there is no automatic pruning.

## 10. Incident response — reviewed only

1. **Declare.** Who: the product owner (no on-call rota yet, OPS-13). Note the time of detection.
2. **Assess** with §1 and the browser. Decide whether this is a code problem (§6), a data problem (§7) or infrastructure (containers, disk, DB).
3. **Contain.** `php artisan down` if data is at risk.
4. **Recover** with §6 or §7. Record the backup used and its time, which is the recovery point.
5. **Verify** with §1 and §2; `php artisan up`.
6. **Follow up.** Write a short note: cause, data lost (time window), fix and test.
