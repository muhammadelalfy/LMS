#!/usr/bin/env bash
# Rolls one image forward on the server, smoke-tests it, and rolls back on failure.
#   ./deploy.sh API_IMAGE ghcr.io/<owner>/mudarisy-api:<sha>
#   ./deploy.sh WEB_IMAGE ghcr.io/<owner>/mudarisy-web:<sha>
set -euo pipefail
cd "$(dirname "$0")"

var="${1:?API_IMAGE or WEB_IMAGE}"
image="${2:?image reference}"
case "$var" in
    API_IMAGE) services=(init api reverb worker scheduler) ;;
    WEB_IMAGE) services=(web) ;;
    *) echo "unknown image variable: $var" >&2; exit 2 ;;
esac

set -a; . ./.env; set +a
previous="$(grep "^$var=" .env | cut -d= -f2-)"
set_image() { sed -i "s|^$1=.*|$1=$2|" .env; }

smoke() {
    ./smoke-test.sh "http://127.0.0.1:${HTTP_PORT:-80}" "$CENTRAL_HOST" "${SMOKE_SCHOOL_HOST:?set SMOKE_SCHOOL_HOST in .env}"
}

# Exported variables override .env in compose, so the new tag is tried first and
# only written to .env once it has been pulled.
export "$var=$image"
docker compose pull --quiet "${services[@]}"
set_image "$var" "$image"

if docker compose up -d --wait --remove-orphans && smoke; then
    echo "deployed $var=$image"
    docker image prune -f >/dev/null
else
    echo "deploy of $image failed; rolling back to $previous" >&2
    export "$var=$previous"
    set_image "$var" "$previous"
    docker compose up -d --wait --remove-orphans
    exit 1
fi
