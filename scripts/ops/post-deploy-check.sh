#!/usr/bin/env bash
# Foresight Radar — post-deploy health check (production readiness 2026-11-13). Last step of deploy.yml.
#
#   bash scripts/ops/post-deploy-check.sh <deployed sha> <previous sha>
#
# Run from the project directory. Read-only apart from one line appended to the release log. Fails (exit 1)
# when the working copy is not the deployed commit, a container is down, a migration is pending, or the
# application's health route (/up, through nginx and PHP-FPM) does not answer 200. Prints no secrets.
#
# The release log (RELEASE_LOG, default ~/foresight-releases.log) keeps "time deployed previous status"
# per deploy, so the previous release is known for a rollback (docs/production-readiness/
# deployment-and-recovery-runbook.md). /up is probed with curl on purpose: this is a liveness probe,
# not UI verification (AGENTS.md §11 is about verifying UI).

set -uo pipefail

DEPLOYED="${1:?usage: post-deploy-check.sh <deployed sha> <previous sha>}"
PREVIOUS="${2:-unknown}"
RELEASE_LOG="${RELEASE_LOG:-$HOME/foresight-releases.log}"
status=0
fail() { echo "CHECK FAILED: $*"; status=1; }
ok() { echo "OK  $*"; }

# 1. The working copy is the commit CI tested.
HEAD_SHA="$(git rev-parse HEAD)"
case "$HEAD_SHA" in "$DEPLOYED"*) ok "release $(git rev-parse --short HEAD)";; *) fail "HEAD is $HEAD_SHA, expected $DEPLOYED";; esac

# 2. Every service container runs.
for c in laravel_app laravel_nginx laravel_mysql laravel_queue laravel_scheduler; do
  if [ "$(docker inspect -f '{{.State.Running}}' "$c" 2>/dev/null)" = "true" ]; then ok "container $c running"; else fail "container $c is not running"; fi
done

# 3. No migration is pending (the deploy ran them after the backup).
if PENDING="$(docker exec laravel_app php artisan migrate:status 2>/dev/null)"; then
  if echo "$PENDING" | grep -q "Pending"; then fail "pending migrations: $(echo "$PENDING" | grep -c Pending)"; else ok "no pending migrations"; fi
else
  fail "migrate:status failed (database unreachable?)"
fi

# 4. The health route answers through nginx and PHP-FPM, with the host name the certificate is for.
APP_HOST="$(grep -E '^APP_URL=' .env | tail -n 1 | cut -d= -f2- | tr -d '"' | sed -E 's#^https?://##; s#/.*$##')"
APP_HOST="${APP_HOST:-localhost}"
CODE="000"
for attempt in 1 2 3 4 5; do
  CODE="$(curl -sk -o /dev/null -w '%{http_code}' --max-time 15 --resolve "$APP_HOST:443:127.0.0.1" "https://$APP_HOST/up" || true)"
  [ "$CODE" = "200" ] && break
  sleep 3
done
if [ "$CODE" = "200" ]; then ok "GET https://$APP_HOST/up -> 200"; else fail "GET https://$APP_HOST/up -> $CODE"; fi

# 5. Release log for rollback.
RESULT=$([ "$status" -eq 0 ] && echo ok || echo FAILED)
printf '%s deployed=%s previous=%s check=%s\n' "$(date -u '+%Y-%m-%dT%H:%M:%SZ')" "$(git rev-parse --short HEAD)" "$PREVIOUS" "$RESULT" >> "$RELEASE_LOG"
echo "Release log: $RELEASE_LOG"
exit "$status"
