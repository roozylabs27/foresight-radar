# Recovery Rehearsal Report — Foresight Radar

Production operations validation, 2026-11-13 (times WIB unless marked). The production backup was restored **only** into an isolated local recovery stack, never into production. The stack and every copy of production data were removed afterwards.

## 1. Recovery environment and isolation

`docker-compose.recovery.yml` (project `foresight-recovery`) on the developer laptop:

| Requirement | How it is met | Verified |
|---|---|---|
| Separate database | own `recovery_mysql` container and volume `foresight-recovery-mysql`; port bound to 127.0.0.1 only | `docker inspect`: project `foresight-recovery`, network `foresight-recovery` only. The restore script refuses any other target |
| Separate configuration | `docker/recovery/recovery.env` is mounted over `/var/www/.env`, so the developer's `.env` is never read | `config()` inside the app: db host `recovery_mysql`, session `cookie`, cache `array`, log `stderr`, mail `array`, queue `database` |
| No production write access | no production credentials anywhere in the stack; the laptop has no route to the production database | by construction |
| No queue workers, no scheduler | none defined; jobs stay in the `jobs` table | by construction |
| No real e-mail | `MAIL_MAILER=array` (the alert test used `log`, which writes to stderr) | config check |
| No external integrations | AI keys empty. A new `APP_KEY` means provider keys stored encrypted in the restored database cannot be decrypted | config check |
| Code cannot be changed | the project is mounted read-only | an attempted write failed with "Read-only file system" (§6) |

## 2. Backup used

`db-20261112-152935-d34343a.sql.gz`: 28,141 bytes, created 2026-11-12 15:29:35 UTC by Deploy #53 (the release before `2d202e7`), sha256 `f60b0158…e81a`. It was copied by the product owner with `scp` after checking the server's host key fingerprint. The checksum was identical before the restore.

## 3. Restore (`scripts/ops/restore-rehearsal.sh`)

| Measurement | Value |
|---|---|
| Integrity before restore | `gzip -t` ok; completion marker present; 55 tables in the dump |
| Restore command | `gunzip -c <file> \| docker exec -i -e MYSQL_PWD=… <mysql container> mysql -u<app user> <db>`, the shape documented in the runbook |
| Restore duration | **12.6 s** into an empty database (2026-11-13, shortly after 00:30 WIB; the script records durations, not wall-clock times) |
| Restore over a populated database | **20.8 s**, exit 0 (the production rollback case; the dump drops and recreates each table) |
| Whole scripted rehearsal incl. all checks | 266 s (host under heavy load at the time) |
| Tables | 55 restored = 55 in the dump; all InnoDB |
| Migration state | 63 ran, last `2026_11_11_000004_…`; `migrate:status --pending`: none |
| Referential integrity | 165 foreign keys checked, **0 orphan rows**; every `tenant_id` points to an existing tenant |
| Critical table counts | equal to the live production counts read on the server at 17:04 UTC (25 tables) |
| Warnings and errors | none from the restore |

## 4. Application recovery (current release `2d202e7` code against the restored data)

A recovery-only account (`recovery-check@recovery.test`, role `developer`, tenant 1) was created **inside the restored copy only** and signed in through the browser at `http://localhost:8090`.

- **37 of 37 pages answered 200** with their Inertia components:
  - the dashboard, the Foresight Radar, the driving force library (10 rows rendered from `/driving-force/fetch-data`) and Signals;
  - the exercise list;
  - for each of the 3 restored exercises: overview, driving forces, scoring, critical uncertainties, framework, conditions, narratives, challenges and opportunities, strategic responses, early warning indicators and the report.
- The exercise list showed the three production exercises (screenshot taken in the session).
- Eloquent read 3 exercises across tenants; migrations matched the code.
- No call reached production or an external service. Mail stayed in the `array` mailer; no worker ran.

The product owner also signed in once with their own account against the restored copy (`userId 1` in the stderr log); that session was logged out before the checks.

## 5. Rollback rehearsal

### A. Code-only rollback (previous release `d34343a`)

- The previous release was identified from the backup name, the server reflog and the Actions history.
- It was checked out as a separate git worktree. `composer.lock`, `package*.json`, the frontend sources and the migrations are identical between `d34343a` and `2d202e7`, so the existing `vendor/` and `public/build` are correct for it.
- It ran in a second isolated container (`recovery_app_prev`, port 8091) against the same restored database:
  - **ready in 19 s** after `docker run`;
  - no pending migrations;
  - `schedule:list` shows only `indicators:fetch-external` (no `ops:failed-jobs`, as expected for that release);
  - **37 of 37 pages 200**.

The first attempts failed for rehearsal-setup reasons (mount points and package manifests missing in a fresh worktree). On the server the release directory always has them, so this does not apply to production. On production, a rollback whose frontend changed also needs the matching `public/build` (runbook §6).

### B. Database recovery (restore plus previous code)

- The dump restored over a populated database in 20.8 s; the documented command works with the **application user**.
- An account created after the backup was gone afterwards, which shows exactly what is lost (RPO, below).
- The previous release ran against the restored schema without pending migrations, and all critical pages were readable.

The runbook used the MySQL root account for restore. That account does not authenticate on production (Deploy #51). The runbook now uses the application user, as rehearsed (OPS-06).

## 6. Monitoring and alerts (in isolation)

- `ops:failed-jobs` against the restored database, with one failed job inserted through Laravel:
  - exit 1;
  - `ERROR Queued jobs failed {"count":1,"jobs":{"ExtractSignals":1},"minutes":60}`;
  - a mail to the configured address with the subject `[Foresight Radar (recovery)] Queued jobs failed` and the job counts only, no payload.
- A failed job written with MySQL `NOW()` (UTC) was not counted, because Laravel writes `failed_at` in the app timezone (Asia/Jakarta) and the command compares in that timezone. That is consistent for real failures; keep it in mind when testing by hand.
- The sender was `hello@example.com` because `MAIL_FROM_*` is not set in the recovery stack. Check it in production.
- Production delivery was **not tested**: `OPS_ALERT_EMAIL` is empty there (OPS-07).

## 7. RPO and RTO measured (no targets are defined: OPS-13)

| | Measured | Meaning |
|---|---|---|
| **RPO** | Backups exist only from deploys. The latest backup was 15:29 UTC; live counts were unchanged at 17:04 UTC, so **0 records** would have been lost at that moment. | Not bounded: in general the loss is everything written since the last deploy, which may be days. A daily backup bounds it to 24 h (OPS-02). |
| **RTO, database** | Restore 12.6 to 20.8 s for 4 MB, plus about 1 min to copy and check the file | Excluding detection and decision time; production adds `php artisan down` / `up` |
| **RTO, code rollback** | about 19 s to serve the previous release once its files were present | Production also needs `optimize`, the FPM reload and the queue restart (seconds), and possibly an asset rebuild (a deploy run, about 2–4 min) |

These measure a 4 MB database on a laptop. Re-measure when the database grows.

## 8. Limitations

- The restore target was a laptop, not a server like production. Isolation was verified, but performance is not representative of production at a larger size.
- The production rollback itself (on the VPS) was not executed: by design, nothing destructive ran on production.
- Deploy step logs were not read (no GitHub sign-in). Production signed-in pages were not checked (no production session).
- The rehearsal found and fixed two tooling defects:
  - `php artisan serve` let the web process read the developer's `.env`; the stack now uses `php -S`;
  - this project reads `CACHE_DRIVER`, not `CACHE_STORE`.
