#!/usr/bin/env bash
# Foresight Radar — browser E2E run against the isolated stack (production readiness 2026-11-13).
#
#   bash scripts/e2e/run.sh                 # up, migrate + seed, Playwright, down
#   KEEP_E2E=1 bash scripts/e2e/run.sh      # leave the stack up afterwards (http://127.0.0.1:8091)
#   bash scripts/e2e/run.sh tests/browser/auth.spec.js   # extra arguments go to `playwright test`
#
# Needs: Docker, the foresight-radar-app image (docker compose build), a frontend build in public/build
# (npm run build), and Playwright's Chromium (npx playwright install chromium). Touches nothing outside
# the foresight-e2e Compose project.

set -euo pipefail
# Git Bash on Windows would rewrite container paths such as /var/www; harmless elsewhere.
export MSYS_NO_PATHCONV=1
cd "$(dirname "$0")/../.."

COMPOSE=(docker compose -p foresight-e2e -f docker-compose.e2e.yml)
ENV_FILE=docker/e2e/e2e.env
env_value() { grep -E "^$1=" "$ENV_FILE" | tail -n 1 | cut -d= -f2-; }

cleanup() {
  if [ "${KEEP_E2E:-0}" != "1" ]; then "${COMPOSE[@]}" down -v --remove-orphans >/dev/null 2>&1 || true; fi
}
trap cleanup EXIT

[ -f public/build/manifest.json ] || { echo "No frontend build: run npm run build first."; exit 1; }

# docker/e2e/e2e.env is mounted over /var/www/.env inside a read-only code mount. Docker can only do that
# when the mount point exists, and a fresh checkout (CI) has no .env. An empty placeholder is enough: the
# containers read e2e.env (mounted over it and passed as env_file), never this file. An existing .env is
# left untouched.
[ -e .env ] || : > .env

echo "[1/5] Starting MySQL and the app..."
"${COMPOSE[@]}" down -v --remove-orphans >/dev/null 2>&1 || true
"${COMPOSE[@]}" up -d e2e_mysql e2e_app
for i in $(seq 1 60); do
  if docker exec -e MYSQL_PWD=e2e-local-only e2e_mysql mysql --protocol=TCP -h 127.0.0.1 -ue2e -e 'SELECT 1' foresight_e2e >/dev/null 2>&1; then break; fi
  sleep 2
  [ "$i" -eq 60 ] && { echo "MySQL did not start"; exit 1; }
done

echo "[2/5] Preparing storage, migrating and seeding (E2eSeeder)..."
docker exec -u 0 e2e_app sh -c 'mkdir -p /var/www/storage/framework/cache/data /var/www/storage/framework/sessions /var/www/storage/framework/views /var/www/storage/logs /var/www/storage/app && chmod -R 777 /var/www/storage'
docker exec -w /var/www e2e_app php artisan migrate:fresh --seed --seeder=E2eSeeder --force >/dev/null

echo "[3/5] Starting the queue worker..."
"${COMPOSE[@]}" up -d e2e_worker

echo "[4/5] Waiting for the app..."
for i in $(seq 1 30); do
  # A readiness probe of the throw-away test stack (not UI verification).
  [ "$(curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:8091/up)" = "200" ] && break
  sleep 1
  [ "$i" -eq 30 ] && { echo "App did not answer /up"; exit 1; }
done

# Each PHP server worker warms its own opcache on first use; warm them before timing-sensitive tests.
for i in $(seq 1 18); do curl -s -o /dev/null http://127.0.0.1:8091/login || true; done

echo "[5/5] Running Playwright..."
E2E_BASE_URL=http://127.0.0.1:8091 E2E_PASSWORD="$(env_value E2E_PASSWORD)" npx playwright test "$@"
