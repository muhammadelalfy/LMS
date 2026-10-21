#!/usr/bin/env bash
# Writes .env.production from the example with freshly generated secrets.
#   ./make-env.sh <api-image> <central-host> <tenant-base-domain> [public-ip]
# EXTRA_CENTRAL_DOMAINS=mudarisy.com,www.mudarisy.com adds hosts that serve the central app.
set -euo pipefail
cd "$(dirname "$0")"

if [[ -e .env.production ]]; then
    echo ".env.production already exists; refusing to overwrite its secrets" >&2
    exit 1
fi

image="${1:?api image}" central="${2:?central host}" base="${3:?tenant base domain}" ip="${4:-127.0.0.1}"
app_key="$(docker run --rm --entrypoint php "$image" artisan key:generate --show --no-ansi)"

umask 077
sed -e "s|^APP_KEY=.*|APP_KEY=${app_key}|" \
    -e "s|^APP_URL=.*|APP_URL=http://${central}|" \
    -e "s|^CENTRAL_DOMAINS=.*|CENTRAL_DOMAINS=${central},${ip}${EXTRA_CENTRAL_DOMAINS:+,${EXTRA_CENTRAL_DOMAINS}}|" \
    -e "s|^TENANT_BASE_DOMAIN=.*|TENANT_BASE_DOMAIN=${base}|" \
    -e "s|^CENTRAL_API_TOKEN=.*|CENTRAL_API_TOKEN=$(openssl rand -hex 32)|" \
    -e "s|^REVERB_APP_ID=.*|REVERB_APP_ID=$(shuf -i 100000-999999 -n 1)|" \
    -e "s|^REVERB_APP_KEY=.*|REVERB_APP_KEY=$(openssl rand -hex 16)|" \
    -e "s|^REVERB_APP_SECRET=.*|REVERB_APP_SECRET=$(openssl rand -hex 32)|" \
    .env.production.example > .env.production

echo "wrote .env.production (mode 600)"

./make-certs.sh
