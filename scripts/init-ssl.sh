#!/usr/bin/env bash
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$SCRIPT_DIR"

DOMAINS=("foresight-radar.id" "www.foresight-radar.id")
PRIMARY_DOMAIN="${DOMAINS[0]}"
EMAIL="${1:-admin@foresight-radar.id}"

echo "=== Initializing SSL for $PRIMARY_DOMAIN ==="

CERT_DIR="$SCRIPT_DIR/docker/certbot/conf/live/$PRIMARY_DOMAIN"
WWW_DIR="$SCRIPT_DIR/docker/certbot/www"

mkdir -p "$CERT_DIR"
mkdir -p "$WWW_DIR"

# 1. Create temporary dummy certificate if real certificate doesn't exist yet
if [ ! -f "$CERT_DIR/fullchain.pem" ]; then
    echo "Creating temporary self-signed certificate to allow Nginx to start..."
    openssl req -x509 -nodes -newkey rsa:2048 -days 1 \
        -keyout "$CERT_DIR/privkey.pem" \
        -out "$CERT_DIR/fullchain.pem" \
        -subj "/CN=localhost"
    echo "Temporary certificate generated."
fi

# 2. Ensure Nginx container is running
echo "Starting/recreating Nginx container..."
docker compose up -d nginx

# 3. Request genuine Let's Encrypt certificate via Certbot webroot
echo "Requesting Let's Encrypt certificate from ACME server for ${DOMAINS[*]}..."
DOMAIN_ARGS=""
for domain in "${DOMAINS[@]}"; do
    DOMAIN_ARGS="$DOMAIN_ARGS -d $domain"
done

docker run --rm \
    -v "$SCRIPT_DIR/docker/certbot/conf:/etc/letsencrypt" \
    -v "$SCRIPT_DIR/docker/certbot/www:/var/www/certbot" \
    certbot/certbot certonly --webroot \
    -w /var/www/certbot \
    $DOMAIN_ARGS \
    --email "$EMAIL" \
    --agree-tos \
    --no-eff-email \
    --force-renewal

# 4. Reload Nginx with production certificate
echo "Reloading Nginx with new production certificate..."
docker exec laravel_nginx nginx -s reload

echo "=== SSL setup completed successfully for $PRIMARY_DOMAIN! ==="

