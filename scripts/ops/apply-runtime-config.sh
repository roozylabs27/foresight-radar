#!/usr/bin/env bash
# Foresight Radar — apply the repository's PHP and nginx configuration to the running containers (OPS-01).
#
#   bash scripts/ops/apply-runtime-config.sh            # from the project directory (deploy step 4b/4c)
#
# docker/php/local.ini and docker/nginx/default.conf are bind-mounted as single files. `git merge` replaces a
# changed file (new inode) while a running container keeps the old one, so a reload re-reads the OLD file
# (verified on production 2026-11-13). When the file a container sees differs from the repository's, that
# container is recreated; otherwise it is reloaded. The nginx config is tested before it goes live, and the
# result is verified by comparing hashes again. Exits non-zero when the live config still differs.
#
# Kept out of deploy.yml on purpose: appleboy/ssh-action's `script_stop` checks the exit status after every
# line, so an `else` branch of a multi-line `if` (entered with $? = 1) stopped Deploy #55. One line, one script.

set -euo pipefail

PHP_HOST_FILE="${PHP_HOST_FILE:-docker/php/local.ini}"
NGINX_HOST_FILE="${NGINX_HOST_FILE:-docker/nginx/default.conf}"
PHP_CONTAINER_FILE=/usr/local/etc/php/conf.d/local.ini
NGINX_CONTAINER_FILE=/etc/nginx/conf.d/default.conf

hash_host() { sha256sum "$1" | cut -d' ' -f1; }
hash_container() { docker exec "$1" sha256sum "$2" | cut -d' ' -f1; }
same_file() { [ "$(hash_host "$1")" = "$(hash_container "$2" "$3")" ]; }

# An unreachable container would hash as "" and look changed: check first and stop with a clear message.
for c in laravel_app laravel_nginx; do
  if [ "$(docker inspect -f '{{.State.Running}}' "$c" 2>/dev/null)" != "true" ]; then
    echo "ERROR: container $c is not running (or Docker is unreachable); nothing applied."
    exit 1
  fi
done
for f in "$PHP_HOST_FILE" "$NGINX_HOST_FILE"; do
  [ -f "$f" ] || { echo "ERROR: $f not found; run from the project directory."; exit 1; }
done

# 4b. PHP: opcache.validate_timestamps=0 keeps the previous release's bytecode until PHP-FPM reloads.
if same_file "$PHP_HOST_FILE" laravel_app "$PHP_CONTAINER_FILE"; then
  echo "[4b/5] PHP config unchanged: reloading PHP-FPM to flush opcache..."
  docker exec laravel_app sh -c 'kill -USR2 1'
else
  echo "[4b/5] $PHP_HOST_FILE changed: recreating app, queue and scheduler..."
  docker compose up -d --no-deps --force-recreate app queue scheduler
fi

# 4c. nginx: test the repository's file with the service's own mounts and network, without its ports.
echo "[4c/5] Testing nginx configuration..."
docker compose run --rm --no-deps -T nginx nginx -t
if same_file "$NGINX_HOST_FILE" laravel_nginx "$NGINX_CONTAINER_FILE"; then
  echo "nginx config unchanged: reloading nginx..."
  docker exec laravel_nginx nginx -s reload
else
  echo "$NGINX_HOST_FILE changed: recreating nginx..."
  docker compose up -d --no-deps --force-recreate nginx
fi

# Verify: the containers now see exactly the repository's files.
status=0
if same_file "$PHP_HOST_FILE" laravel_app "$PHP_CONTAINER_FILE"; then echo "PHP config live: matches the repository"; else echo "ERROR: PHP config in laravel_app differs from $PHP_HOST_FILE"; status=1; fi
if same_file "$NGINX_HOST_FILE" laravel_nginx "$NGINX_CONTAINER_FILE"; then echo "nginx config live: matches the repository"; else echo "ERROR: nginx config differs from $NGINX_HOST_FILE"; status=1; fi
echo "nginx live config: $(docker exec laravel_nginx sh -c 'nginx -T 2>/dev/null | grep -c Permissions-Policy' || true) Permissions-Policy line(s)"
echo "PHP expose_php: $(docker exec laravel_app php -r 'echo ini_get("expose_php") ? "On" : "Off";')"
exit "$status"
