# VPS Owner Handoff — Foresight Radar

Production readiness closure, 2026-11-14. These checks need the production server or the owner's accounts. Nothing here can be verified from the developer machine. Each requirement stays **BLOCKED** in the readiness matrix until its block has been run and the output reviewed.

**Where:** the VPS, as the deploy user (`roozylabs`), in `/var/www/foresight-radar`.

**Before blocks 2–6:** merge the remediation of 2026-11-14 into `main` and let CI and the gated deploy run. The new `verify-production.sh`, `ops:alert`, the backup lock and the post-deploy check only exist on the server after that.

**Sharing output:**
- the scripts print no passwords, keys, tokens or `.env` values (only set/empty, counts, hashes, versions), and email addresses are masked;
- still read the output before pasting it, and cut any line you would not want in a ticket;
- never paste `.env`, `crontab -e` content containing passwords, or `rclone config` output.

Legend: **[read-only]** changes nothing. **[changes the server]** is marked, says what it changes, and how to undo it.

---

## Block 1 — Release and baseline [read-only]

```bash
cd /var/www/foresight-radar && git rev-parse --short HEAD && git log -1 --format='%ci %s' && tail -n 5 ~/foresight-releases.log
```

Expected:
- HEAD equals the commit the last "Deploy to VPS" run deployed;
- the last release-log line ends in `check=ok` (written by `post-deploy-check.sh` from the first deploy that contains it).

Also paste the log of the deploy run's step "Execute deployment commands via SSH". It shows the backup line, the migrations, "config live" and the `OK` lines of the post-deploy check.

## Block 2 — Production verification [read-only]

```bash
cd /var/www/foresight-radar && bash scripts/ops/verify-production.sh 2>&1 | tee /tmp/verify-$(date +%Y%m%d-%H%M).txt; echo "exit=${PIPESTATUS[0]}"
```

Paste sections **2, 3, 7, 9 and 10**. Section 10 ends with `RESULT: n PASS, n WARN, n FAIL, n SKIP` and the script exits 1 when anything FAILs.

Section 10 covers:
- working copy and release;
- the five containers;
- nginx and PHP loading the repository config;
- `APP_DEBUG` off, `APP_ENV` production, secure session cookie;
- `.env` file mode;
- `/up` through nginx;
- database: the app connects, is not root, its password is not the compose default, root refuses the compose default, no global admin privileges, MySQL bound to localhost only;
- migrations; queue keeping up; failed jobs; scheduler runs;
- `OPS_ALERT_EMAIL` and a delivering mailer;
- backup freshness and integrity, backup crons, off-server copy, backup directory mode;
- disk and memory.

SKIP is never counted as PASS. "alert actually delivered" is always SKIP here: block 3 proves it.

## Block 3 — Alert delivery

1. **[changes the server: `.env`]** Set the alert recipient and a real mailer in `.env`. Use a mailbox of the operations team, not a personal address that may leave.

   ```
   OPS_ALERT_EMAIL=ops-team@your-domain
   MAIL_MAILER=smtp
   MAIL_HOST=...
   MAIL_PORT=587
   MAIL_USERNAME=...
   MAIL_PASSWORD=...
   MAIL_ENCRYPTION=tls
   MAIL_FROM_ADDRESS=no-reply@your-domain
   ```

   Then reload the configuration (same commands as a deploy):

   ```bash
   cd /var/www/foresight-radar && docker exec laravel_app php artisan optimize:clear && docker exec laravel_app php artisan optimize && docker restart laravel_queue laravel_scheduler
   ```

   Undo: restore the previous `.env` lines and run the same reload.

2. **[read-only, sends one mail]** Run the delivery test:

   ```bash
   cd /var/www/foresight-radar && docker exec laravel_app php artisan ops:alert --test; echo "exit=$?"
   ```

   Expected: `Accepted by the mail transport (<time>) for o***@your-domain. Check that mailbox to confirm delivery.` and `exit=0`.

3. Confirm in the mailbox that the mail "[Foresight Radar] Alert delivery test" arrived (check spam). Report its arrival time. **Delivery is proven only by the mailbox**, not by step 2.

4. Optional, a real alert path: the hourly `ops:failed-jobs` mails a summary when a queued job failed. The backup script mails through `ops:alert` when a backup or `--check` fails.

## Block 4 — Scheduled backups

1. **[changes the server: creates a dump]** One backup by hand, then the check:

   ```bash
   cd /var/www/foresight-radar && bash scripts/ops/backup-database.sh scheduled && bash scripts/ops/backup-database.sh --check
   ```

   Expected:
   - `Database backup: ... sha256 ...`;
   - `Off-server copy: not configured` (until block 5);
   - `OK newest backup ... 0 h old`.

2. **[changes the server: crontab]** Run `crontab -e` and add these two lines (server clock UTC; backup at 01:15 WIB, freshness check every 6 hours):

   ```
   15 18 * * * cd /var/www/foresight-radar && bash scripts/ops/backup-database.sh scheduled >> $HOME/foresight-backups/backup.log 2>&1
   45 */6 * * * cd /var/www/foresight-radar && bash scripts/ops/backup-database.sh --check >> $HOME/foresight-backups/backup.log 2>&1
   ```

   Undo: delete the two lines with `crontab -e`.

3. **[read-only]** The next day:

   ```bash
   crontab -l | grep backup-database && ls -la ~/foresight-backups | tail -n 5 && tail -n 5 ~/foresight-backups/backup.log
   ```

   Expected: a `db-*-scheduled.sql.gz` with its `.sha256` from 18:15 UTC, and `OK newest backup` lines from the check.

## Block 5 — Off-server copy (needs an account of the owner)

Until this is done the backups share the VPS failure domain. Disaster recovery is then **not** ready, and the matrix says so.

1. **[changes the server: installs rclone and writes its config]**

   ```bash
   sudo apt install rclone && rclone config
   ```

   Create a storage remote (for example Backblaze B2, Cloudflare R2 or S3, with a bucket used only for these backups and lifecycle rules for deletion), then a `crypt` remote on top of it named `foresight-offsite`. Keep the crypt password in the password manager.

2. **[changes the server: settings file]**

   ```bash
   umask 077 && mkdir -p ~/.config && printf 'OFFSITE_REMOTE="foresight-offsite:db"\n' >> ~/.config/foresight-backup.env && chmod 600 ~/.config/foresight-backup.env
   ```

3. Run block 4 step 1 again. It must print `Off-server copy verified: foresight-offsite:db/db-....sql.gz`.

4. Restore test from the remote copy (on the developer machine, with the isolated recovery stack): `rclone copy foresight-offsite:db/<file> ./restore/`, then `scripts/ops/restore-rehearsal.sh` with that file (Ops runbook §5). Report the rehearsal summary.

## Block 6 — Database credentials

Block 2 section 10 reports the five database verdicts without printing a password. When **"root does not accept the compose default"** or **"app password is not the compose default"** is FAIL, rotate before the release.

Rotation plan:
- downtime: a few seconds;
- connection pools: PHP-FPM opens connections per request, so after the reload no old connection survives;
- the queue worker reconnects after its restart.

**Root** (the app does not use it):

1. **[changes the server: MySQL root password]** Choose a long random password and store it in the password manager. Then:

   ```bash
   docker exec -it laravel_mysql mysql -uroot -p
   ```

   At the prompt, enter the current root password. Then in MySQL (type the new password; it stays out of shell history):

   ```sql
   ALTER USER 'root'@'%' IDENTIFIED BY '<new>'; ALTER USER 'root'@'localhost' IDENTIFIED BY '<new>'; FLUSH PRIVILEGES;
   ```

2. Run block 2 again: "root does not accept the compose default" must be PASS.

**App user** (verify the new password before the old one is gone):

1. **[changes the server]** In MySQL as root:

   ```sql
   ALTER USER '<app user>'@'%' IDENTIFIED BY '<new>';
   ```

2. Put the new value in `.env` as `DB_PASSWORD`.
3. Reload with the block 3 step 1 command.
4. Run block 2. The database verdicts and `/up` must PASS, and `backup-database.sh --check` must still work: it reads the same `.env`.

If something fails, set the old password back with the same `ALTER USER` and `.env` edit.

`docker-compose.yml` still holds the compose defaults. They only apply when the MySQL volume is first created; known risk R-13 tracks moving them to variables.

## Block 7 — GitHub settings (owner, in the browser)

- **Branch protection for `main`:** Settings → Rules → Rulesets → New branch ruleset, target `main`. Enable:
  - "Restrict deletions";
  - "Block force pushes";
  - "Require a pull request before merging";
  - "Require status checks to pass": the CI jobs `Backend Tests & Security Audit`, `Frontend Build & Security Audit` and `Browser E2E (Playwright, isolated stack)`.

  No ruleset existed on 2026-11-14.
- **Environment `production`:** Settings → Environments → production. Optionally add a required reviewer, so a deploy waits for an approval.
- **Failure notifications:** GitHub mails the person who triggered a failed run by default. Confirm under your notification settings, Actions.

---

## What to send back

| Block | Paste |
|---|---|
| 1 | three output lines, plus the deploy log of the SSH step |
| 2 | sections 2, 3, 7, 9, 10 (or the whole file after reading it) |
| 3 | the `ops:alert --test` output, and "arrived at hh:mm" |
| 4 | outputs of steps 1 and 3 |
| 5 | the "Off-server copy verified" line and the rehearsal summary |
| 6 | the section 10 database lines after any rotation |
| 7 | "done", or a screenshot of the ruleset summary |
