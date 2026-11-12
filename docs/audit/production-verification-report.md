# Production Verification Report — Foresight Radar

Production operations validation, 2026-11-12/13 (UTC dates as observed). Companion reports: [backup integrity](backup-integrity-report.md), [recovery rehearsal](recovery-rehearsal-report.md). Procedures: [production operations runbook](../guidelines/production-operations-runbook.md).

## How the evidence was collected

| Source | What | Limits |
|---|---|---|
| `scripts/ops/verify-production.sh` | Read-only script run on the VPS by the product owner, 2026-11-12 17:04 UTC. It prints versions, statuses, sizes, counts and table names only; output pasted into the session. | Its section 5 (Laravel config via tinker) failed with "Writing to directory /var/www/.config/psysh is not allowed"; fixed in the script, not re-run |
| Built-in browser, not signed in | `https://foresight-radar.id/login`: response headers, Ziggy route list, `/build/manifest.json` and bundles | — |
| GitHub Actions run list (public page) | Run numbers, triggers, commits, results | Step logs need a GitHub sign-in, which was not done: **step-level deploy logs NOT READ** |
| Repository | `deploy.yml`, `ci.yml`, compose files, runbooks | — |

Not done: signed-in checks on production pages (no production session in the panel). These pages were instead checked on the restored copy (recovery report §5).

## 1. Release integrity

| Check | Evidence | Result |
|---|---|---|
| Commit on the server | `git rev-parse HEAD` = `2d202e7` on branch `main`, 0 uncommitted files | PASS |
| Matches the intended release | `origin/main` was `2d202e7` when Deploy #53 ran. `main` has since moved to `9530561`, which only adds the verify script | PASS |
| Tested by CI | CI #105 on `2d202e7` (main): success. Deploy #53 was triggered by `workflow_run` on `2d202e7`: success, 3 m 59 s | PASS |
| Deploy history (server reflog, UTC) | `9ae54b4` → (checkout main) → `88bc5fd` 12:30 (Deploy #51, stopped at backup) → `d34343a` 14:52 (#52) → `2d202e7` 15:29 (#53) | consistent with Actions |
| Divergence | `main` and `development` trees identical apart from merge commits; server clean | PASS |
| Migrations | 63 ran, none pending; last `2026_11_11_000004_add_staleness_to_steps_seven_and_nine` | PASS |
| Backup before migration | `db-20261112-145228-88bc5fd.sql.gz` (#52) and `db-20261112-152935-d34343a.sql.gz` (#53) exist, complete | PASS |
| **All deploy steps took effect** | The frontend bundle and routes of Phase 5 are live. **The PR #21 nginx and PHP configuration is not:** see §3 | **FAIL (OPS-01)** |

## 2. Runtime and infrastructure (2026-11-12 17:04 UTC)

| Item | Observed | Result |
|---|---|---|
| Containers | `laravel_app`, `laravel_queue`, `laravel_scheduler`, `laravel_nginx`, `laravel_mysql` all Up. Restart counts 0 (queue 1, from the deploy restart). No health checks are defined | PASS (no health checks: OPS-11) |
| Host | Load 0.00; disk `/` 14 % of 96 GB used; memory 6.6 GB available of 7.9 GB; swap unused; uptime 14 days | PASS |
| Container resources | MySQL 442 MiB, app 52 MiB, queue 32 MiB, scheduler 30 MiB, nginx 5 MiB | PASS |
| Database | App user connects; MySQL 8.0.46; 55 tables, all InnoDB; 4.0 MB | PASS |
| Queue | `queue:work --tries=1 --timeout=310 --sleep=3 --max-time=3600`; 0 waiting jobs; 0 failed jobs (total and 24 h) | PASS |
| Scheduler | `schedule:work`. `ops:failed-jobs` hourly and `indicators:fetch-external` daily are listed; 4 `ops:failed-jobs` runs in 6 h, each "DONE" | PASS |
| Logs | Daily rotation is active (`laravel-2026-11-13.log`). The old single `laravel.log` (499 KB, last written 2026-11-03) is still there | PASS |
| Application | `/login` answers 200 in about 100 ms. 199 named routes; no Ignition debug routes; `APP_DEBUG=false`, environment `production` | PASS |
| Signed-in pages on production | not checked | NOT TESTED |
| Console on `/login` | Two `401` errors: `IngestionProvider` polls `signals.active-extractions` for guests too | P3 (OPS-10) |

## 3. Security configuration

| Item | Observed on production | Result |
|---|---|---|
| HTTPS, HSTS | `strict-transport-security: max-age=31536000; includeSubDomains` | PASS |
| `X-Frame-Options` / `nosniff` / `Referrer-Policy` | present | PASS |
| Session cookie | not readable by JavaScript (only `XSRF-TOKEN` visible) | PASS |
| Debug | debug off; no Ignition routes | PASS |
| **CSP (report-only), Permissions-Policy** | **absent** | **FAIL (OPS-01)** |
| **Version disclosure** | `Server: nginx/1.31.8`, `X-Powered-By: PHP/8.3.36`; `expose_php` On in the PHP-FPM container | **FAIL (OPS-01)** |

**Root cause (OPS-01, verified):** `docker/nginx/default.conf` and `docker/php/local.ini` are bind-mounted as single files. `git merge` replaces a changed file with a new inode, while the running container keeps the old one. The verify script showed:

- `default.conf`: host sha256 `03d4c03c…`, container `f7588790…`;
- `Permissions-Policy` lines: host 2, container 0;
- `local.ini`: host `16267951…`, container `0b7fc7c5…`.

So `nginx -s reload` in Deploy #53 re-read the old file, and the deploy reported success. Docker Desktop on Windows does not show this behaviour, which is why local checks passed.

**Fix (uncommitted):** `deploy.yml` now compares the host file with the file the container sees. It tests the repository's nginx config with `docker compose run --rm --no-deps -T nginx nginx -t`, which uses the same mounts and network, and recreates the container when the two differ. It then prints the live `Permissions-Policy` count. The commands were checked locally; the change is not deployed.

**Immediate remedy** (needs your authorization; a few seconds of downtime): on the server, run `docker compose run --rm --no-deps -T nginx nginx -t`, then `docker compose up -d --no-deps --force-recreate nginx app queue scheduler`, then re-run the verify script.

## 4. Deployment pipeline (implementation review plus run evidence)

| Expected step | Implementation | Evidence | Result |
|---|---|---|---|
| Merge to `main` starts CI | `ci.yml` on push to `main` | CI #102, #105 | PASS |
| Backend, frontend and e2e checks | jobs `backend`, `frontend`, `e2e` (`needs: [backend, frontend]`, smoke spec) | CI runs green | PASS |
| Deploy only after successful CI | `workflow_run` on CI completed, `branches: [main]`, `if conclusion == success` | #52, #53 started only after CI | PASS |
| Manual deploy bypasses CI | `workflow_dispatch` is accepted from `main` without checking a CI result | review | **GAP (OPS-12, P2)** |
| Exact tested commit | `DEPLOY_SHA = workflow_run.head_sha`; server `git merge --ff-only "$DEPLOY_SHA"` | reflog shows fast-forwards to each SHA | PASS |
| Assets built | built on the runner from `DEPLOY_SHA`, uploaded and extracted | bundles live | PASS |
| Backup before migration, validity check | dump plus "Dump completed" check; stop on failure | #51 stopped at the backup (root password); #52 and #53 made complete dumps | PASS |
| Backup file safety | files 664 and dir 775 (world-readable); the failed 20-byte dump of #51 counts in retention | verify script | **GAP (OPS-04, OPS-05)** — fixed in `deploy.yml`, not deployed |
| Migration failure stops the deploy | `script_stop: true` | #51 proved the stop behaviour (at the backup) | PASS — leaves a half-deployed release; recovery is in the runbook |
| Caches, PHP-FPM, nginx, queue | `optimize`, `USR2`, `nginx -t` + reload, `docker restart laravel_queue` | queue restarted at 16:29 UTC; nginx and PHP config not applied | **FAIL (OPS-01)** |
| Concurrent deploys | `concurrency: deploy-production`, `cancel-in-progress: false` (queued) | review | PASS |
| Retry / idempotency | fast-forward to the same SHA is a no-op; the dump repeats; migrate is a no-op | #52 re-ran after #51 cleanly | PASS |
| Deployment failure notification | GitHub's default e-mail to the actor only | — | OPEN (OPS-11) |

## 5. Unresolved risks

See [production-remediation-backlog.md](production-remediation-backlog.md) for the full list (OPS-01 … OPS-14), and the [checklist](production-readiness-checklist.md).
