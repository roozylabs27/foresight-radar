# Backup Integrity Report — Foresight Radar

Production operations validation, 2026-11-12/13. Evidence: the read-only verify script on the VPS (17:04 UTC); the copy of the latest backup restored in isolation ([recovery rehearsal](recovery-rehearsal-report.md)); `deploy.yml` review. No backup was restored into production.

## 1. Mechanism (as deployed in `2d202e7`)

| Aspect | Implementation | Assessment |
|---|---|---|
| When | Only during a deploy, before `migrate --force` (`.github/workflows/deploy.yml`) | **No scheduled backup**: the recovery point is the last deploy (OPS-02) |
| Command | `docker exec -e MYSQL_PWD=… laravel_mysql mysqldump --protocol=TCP -h 127.0.0.1 -u<app user> --single-transaction --no-tablespaces --triggers <db> \| gzip` | sound |
| Authentication | `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` read from the server's `.env`; password passed as `MYSQL_PWD`, never on the command line | PASS; values not printed |
| Privileges | `GRANT ALL PRIVILEGES ON foresight\_radar.* TO <app-user>@'%'` (user name masked) | sufficient for dump and for a table-by-table restore |
| Database selection | `DB_DATABASE` = `foresight_radar`; dump header "Database: foresight_radar" | PASS |
| Compression | gzip | PASS (`gzip -t` ok) |
| Naming | `db-<UTC date>-<time>-<previous release sha>.sql.gz` in `~/foresight-backups` | identifies the release to roll back to |
| Completion detection | last line must contain "Dump completed", else the deploy stops before migrating | PASS (Deploy #51 stopped on an auth failure) |
| Permissions | directory `drwxrwxr-x`, files `-rw-rw-r--` | **FAIL**: readable by every account on the host (OPS-04) |
| Retention | newest 14 `db-*.sql.gz` kept, older removed after a successful dump | a failed dump stays and counts as one of the 14 (OPS-05) |
| Off-server copy | none. No backup cron; `rclone` and `aws` are not installed | **FAIL**: a disk or VPS loss loses the database and every backup (OPS-03) |

## 2. Files present (2026-11-12 17:04 UTC)

| File | Bytes | Time (UTC) | Note |
|---|---|---|---|
| `db-20261112-123050-9ae54b4.sql.gz` | 20 | 12:30 | **empty**: the failed dump of Deploy #51 (root authentication) |
| `db-20261112-145228-88bc5fd.sql.gz` | 28,142 | 14:52 | Deploy #52 |
| `db-20261112-152935-d34343a.sql.gz` | 28,141 | 15:29 | Deploy #53, **latest** |

## 3. Integrity of the latest backup

| Check | Result |
|---|---|
| sha256 on the server | `f60b015886160c4d4f25c72981ac043535593c3b88c96256e92519f6e866e81a` |
| sha256 of the copy used for the rehearsal | identical (copied with `scp`, host key fingerprint checked against the server) |
| `gzip -t` | ok |
| Last line | `-- Dump completed on 2026-11-12 15:29:35` |
| Header | `MySQL dump 10.13 Distrib 8.0.46`, `Host: 127.0.0.1`, `Database: foresight_radar` |
| Tables | 55 `CREATE TABLE`, 55 `DROP TABLE IF EXISTS`, no `CREATE DATABASE` or `USE` (so it restores into a database named at restore time); all `ENGINE=InnoDB` |
| Coverage | equals the live schema: 55 tables. Includes tenants, users, roles and permissions, `tenant_invitations`, scenario planning (projects, forces, assessments, critical uncertainties, scenarios, quadrants, conditions, narrative revisions, implications, strategic responses, indicators, observations, threshold events, project decisions, participants), signals, sources, citations, driving forces and ratings, AI run and usage tables, `jobs`, `failed_jobs`, `migrations` |
| Truncation | none: completion marker present, and the restore created all 55 tables with 0 orphaned foreign keys |
| Row counts after restore = live production counts at 17:04 UTC | identical for all 25 critical tables checked (e.g. users 3, scenario_planning_projects 3, indicator_observations 22, project_decisions 18), so nothing was written between 15:29 and 17:04 |

No business records are reproduced in this report.

## 4. Consistency

All 55 tables are InnoDB, and the dump uses `--single-transaction`, so it is one consistent snapshot even while the application writes. No `LOCK TABLES` and no MyISAM tables are involved. Triggers are included; the schema has no routines or events, so none are needed.

## 5. Changes made (uncommitted, in `deploy.yml`)

- **OPS-04:** `chmod 700` on the backup directory and `chmod 600` on existing dumps every deploy; new dumps are written under `umask 077`.
- **OPS-05:** a dump is written as `<name>.partial` and renamed only after the completion check. A failed dump never counts toward retention, and the next run removes it. Checked locally with a deliberately failing dump: exit 2 and only a `.partial` file left; the next good run exit 0 and the `.partial` file removed.
- The log line now shows each new dump's size and sha256 prefix.

**Manual, needs your go-ahead on the server:**
- `chmod 700 ~/foresight-backups && chmod 600 ~/foresight-backups/db-*.sql.gz`;
- remove the empty `db-20261112-123050-9ae54b4.sql.gz` (20 bytes; not a backup).

## 6. Scheduled and off-server backups: tooling ready (2026-11-13)

`scripts/ops/backup-database.sh` replaces the inline dump in `deploy.yml` and serves the daily cron job. It has the same command and checks plus:
- age-based retention (14 days, never fewer than 3 valid dumps);
- an optional off-server copy via rclone, verified with `rclone check --download`;
- a `--check` mode for backup age and completeness;
- an optional heartbeat URL.

Tested locally:
- success;
- freshness check;
- invalid label;
- dump failure: exit 1, only `.partial` left, removed by the next run;
- retention: 4 old dumps removed, 3 newest kept;
- off-server configured without rclone: fails, local dump kept;
- stale backup and empty directory: `--check` fails.

Installation steps are in the [runbook §4b](../guidelines/production-operations-runbook.md). Installing the cron job and choosing the off-server storage need the owner.

## 7. Recommendations (need authorization)

1. **OPS-02, scheduled backups:** a host cron job (for example daily at 01:00 WIB) that runs the same dump command into `~/foresight-backups`. Retention should be by age (for example 14 daily plus the deploy dumps), not by count.
2. **OPS-03, off-server copy:** push each new dump to storage outside the VPS. Options:
   - an object-storage bucket via `rclone` (needs a provider account and credentials);
   - the VPS provider's snapshot or backup service;
   - a pull from another machine with `scp` (the method used in this rehearsal).

   Encrypt at rest if the storage is third-party.
3. Alert when a scheduled backup fails or is older than 26 h (OPS-11).
