# Deployment and Rollback — Foresight Radar

Written for PRD-01 of the [production readiness validation](../audit/production-readiness-report.md) (2026-11-12).

## How a release reaches production

1. A commit is pushed or merged to `main`. Pushes to `development` or feature branches no longer deploy.
2. The CI quality gate runs: `.github/workflows/ci.yml` (Pint, Larastan, PHPUnit on MySQL, npm audit, ESLint, build).
3. Only when that CI run **succeeds**, `.github/workflows/deploy.yml` starts through `workflow_run`. It deploys exactly the commit CI tested (`DEPLOY_SHA`). On the server, `main` is fast-forwarded to that commit, and the deploy stops if history diverged.
4. Before `migrate --force`, the database is dumped to `~/foresight-backups/db-<date>-<previous commit>.sql.gz`. If the dump fails or is empty, the deploy stops before migrating. The 14 most recent dumps are kept.
5. The deploy then caches config, reloads PHP-FPM and restarts the queue worker.

A manual run (Actions → Deploy to VPS → Run workflow) is accepted only from `main`. It does not wait for CI, so use it only for a commit whose CI run already passed.

The first time, check on the server that `~/foresight-backups` can be written and that the first dump has a sensible size.

## Rollback

Use rollback when a release breaks production. Decide first whether the release ran a migration: the deploy log shows `Running migrations` with names, or says `Nothing to migrate`.

### A. No migration ran (code only)

```bash
cd "$VPS_PROJECT_PATH"
git log --oneline -5                       # find the previous release (also in the deploy log: "previous release: <sha>")
git checkout --detach <previous-sha>
docker exec laravel_app php artisan optimize:clear && docker exec laravel_app php artisan optimize
docker exec laravel_app sh -c 'kill -USR2 1'
docker restart laravel_queue
```

The frontend bundle in `public/build` belongs to the broken release. Re-run the deploy workflow for the previous commit, or build it locally and copy `public/build`. Then fix forward on `main`; the next deploy fast-forwards `main` again. Run `git checkout main` on the server first.

### B. A migration ran

1. Put the application in maintenance mode: `docker exec laravel_app php artisan down`.
2. Restore the dump taken just before this deploy, which is the newest file in `~/foresight-backups`:
   ```bash
   gunzip -c ~/foresight-backups/db-<date>-<previous-sha>.sql.gz | docker exec -i laravel_mysql sh -c 'exec mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"'
   ```
   Data written between the deploy and the restore is lost. Note the time window and tell affected users.
3. Check out the previous commit and clear caches as in A.
4. `docker exec laravel_app php artisan up`.

Prefer restoring the dump over `migrate:rollback`. Some migrations move data (for example `2026_11_11_000001`), and their `down()` refuses to run when it would lose data.

## Still open

- Production also serves as staging (PRD-02). Validate a release with data-moving migrations on a copy of the production database before merging it to `main`.
- Dumps stay on the same server. Copy them off the server (object storage) for disaster recovery.
