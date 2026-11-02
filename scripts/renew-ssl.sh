#!/usr/bin/env bash
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$SCRIPT_DIR"

echo "[$(date -u)] Checking SSL certificate renewal..."
docker run --rm \
  -v "$SCRIPT_DIR/docker/certbot/conf:/etc/letsencrypt" \
  -v "$SCRIPT_DIR/docker/certbot/www:/var/www/certbot" \
  certbot/certbot renew --no-random-sleep-on-renew --quiet

docker exec laravel_nginx nginx -s reload
echo "[$(date -u)] SSL check completed and Nginx reloaded."

