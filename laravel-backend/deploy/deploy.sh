#!/usr/bin/env bash
# Rolls one image forward without downtime, smoke-tests it, and rolls back on failure.
#   ./deploy.sh API_IMAGE ghcr.io/<owner>/mudarisy-api:<sha>
#   ./deploy.sh WEB_IMAGE ghcr.io/<owner>/mudarisy-web:<sha>
#
# New replicas start beside the old ones and get no traffic until they are healthy.
# nginx is then switched to them with a graceful reload and the old ones are retired,
# so a broken image never takes the site down.
#
# Note: bash ignores `set -e` inside functions called from an `if`/`&&` chain, so every
# step that matters below checks its own status.
set -euo pipefail
cd "$(dirname "$0")"

var="${1:?API_IMAGE or WEB_IMAGE}"
image="${2:?image reference}"
case "$var" in
    API_IMAGE) service=api; port=8000; companions=(reverb worker scheduler) ;;
    WEB_IMAGE) service=web; port=80; companions=() ;;
    *) echo "unknown image variable: $var" >&2; exit 2 ;;
esac

set -a; . ./.env; set +a
# Checked now: a missing setting discovered mid-rollout would abort the script with the
# new version half-switched and no rollback.
: "${CENTRAL_HOST:?set CENTRAL_HOST in .env}" "${SMOKE_SCHOOL_HOST:?set SMOKE_SCHOOL_HOST in .env}"
previous="${!var}"
replicas=1
[[ "$service" == api ]] && replicas="${API_REPLICAS:-3}"
upstream_file="nginx/upstreams/$service.inc"

log() { printf '==> %s\n' "$*"; }

set_image() { sed -i "s|^$1=.*|$1=$2|" .env; export "$1=$2"; }

smoke() {
    ./smoke-test.sh "http://127.0.0.1:${HTTP_PORT:-80}" "$CENTRAL_HOST" "$SMOKE_SCHOOL_HOST"
}

# Re-render the template (it may have changed) and reload without dropping connections.
reload_lb() {
    docker compose exec -T lb sh -c \
        '/docker-entrypoint.d/20-envsubst-on-templates.sh >/dev/null && nginx -t -q && nginx -s reload'
}

# Send $service traffic to exactly these containers, or to the whole service when given none.
route_to() {
    local name tmp
    (($# > 0)) || set -- "$service"
    tmp="$(mktemp "$upstream_file.XXXX")"
    for name in "$@"; do
        printf 'server %s:%s resolve max_fails=3 fail_timeout=10s;\n' "$name" "$port" >> "$tmp"
    done
    chmod 644 "$tmp" && mv "$tmp" "$upstream_file" && reload_lb
}

names_of() { docker inspect -f '{{.Name}}' "$@" | sed 's|^/||'; }

# Containers of $service that are not in the caller's `old` array.
added_since() { docker compose ps "$@" -q "$service" | grep -vxF -f <(printf '%s\n' "${old[@]}") || true; }

start_companions() { ((${#companions[@]} == 0)) || docker compose up -d --no-deps --wait "${companions[@]}"; }

# The two generations of $service during a rollout.
old=() new=()

# Start replicas of the current image beside the running ones and move traffic to them.
# The old replicas keep running, so a failed check can switch straight back.
roll_start() {
    local names=()
    old=() new=()
    mapfile -t old < <(docker compose ps -q "$service")

    if ((${#old[@]} == 0)); then
        docker compose up -d --no-deps --wait --wait-timeout 180 --scale "$service=$replicas" "$service" || return 1
        mapfile -t new < <(docker compose ps -q "$service")
        route_to || return 1
        return 0
    fi

    mapfile -t names < <(names_of "${old[@]}")
    route_to "${names[@]}" || return 1                # pin traffic while new replicas boot

    if ! docker compose up -d --no-deps --no-recreate --wait --wait-timeout 180 \
            --scale "$service=$((replicas * 2))" "$service"; then
        log "new $service replicas never became healthy; keeping the old ones"
        mapfile -t new < <(added_since -a)
        ((${#new[@]} == 0)) || docker rm -f "${new[@]}" >/dev/null
        new=()
        route_to
        return 1
    fi
    mapfile -t new < <(added_since)

    mapfile -t names < <(names_of "${new[@]}")
    if ! route_to "${names[@]}"; then                 # switch; old workers drain in-flight requests
        docker rm -f "${new[@]}" >/dev/null
        new=()
        route_to
        return 1
    fi
}

# The new generation passed: retire the old one.
roll_commit() {
    ((${#old[@]} > 0)) || return 0
    sleep 5                                           # let old nginx workers finish in-flight requests
    docker stop -t 30 "${old[@]}" >/dev/null || true
    docker rm -f "${old[@]}" >/dev/null || true
    old=()
    route_to || log "warning: nginx still names the new containers explicitly; reset on the next reload"
}

# The new generation failed after taking traffic: switch straight back and drop it.
roll_abort() {
    local names=()
    if ((${#old[@]} == 0)); then
        log "no previous replicas to return to; leaving the new ones running"
        return 1
    fi
    mapfile -t names < <(names_of "${old[@]}")
    route_to "${names[@]}" || log "warning: could not switch nginx back"
    sleep 5
    ((${#new[@]} == 0)) || docker rm -f "${new[@]}" >/dev/null
    new=()
    route_to
}

log "checking nginx config"
docker compose run --rm --no-deps -e NGINX_ENTRYPOINT_QUIET_LOGS=1 lb nginx -t -q

export "$var=$image"
log "pulling $image"
docker compose pull --quiet "$service" "${companions[@]}"
set_image "$var" "$image"

# Converge the load balancer first; it is recreated only if its definition changed.
docker compose up -d --no-deps --wait lb

if [[ "$service" == api ]]; then
    log "migrating"
    if ! docker compose up --no-deps --force-recreate --exit-code-from init init; then
        log "migrations failed; nothing was switched"
        set_image "$var" "$previous"
        exit 1
    fi
fi

log "rolling $service to $image"
if ! roll_start; then
    set_image "$var" "$previous"
    log "deploy of $image failed before taking traffic; still on $previous"
    exit 1
fi

if ! smoke; then
    log "smoke test failed; switching back to $previous"
    roll_abort || true
    set_image "$var" "$previous"
    exit 1
fi

roll_commit
if ! start_companions; then
    log "companions failed on $image; rolling everything back to $previous"
    set_image "$var" "$previous"
    if roll_start; then roll_commit; fi
    start_companions || log "ROLLBACK INCOMPLETE: check 'docker compose ps' by hand"
    exit 1
fi

log "deployed $var=$image"
docker image prune -f >/dev/null || true
