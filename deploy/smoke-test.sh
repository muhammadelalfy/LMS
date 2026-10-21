#!/usr/bin/env bash
# End-to-end checks through the load balancer. Run from deploy/ after `docker compose up -d --wait`.
#   ./smoke-test.sh <base-url> <central-host> <school-host>
# With SMOKE_LOGIN_EMAIL / SMOKE_LOGIN_PASSWORD set, also signs in to the school.
# With SMOKE_TLS_URL set (https://host:port), also checks the origin's HTTPS listener.
set -uo pipefail

BASE="${1:-http://localhost}"
CENTRAL="${2:?central host}"
SCHOOL="${3:?school host}"
fail=0

check() { # name expected actual
    if [[ "$3" == "$2" ]]; then echo "ok   $1 ($3)"; else echo "FAIL $1: expected $2, got $3"; fail=1; fi
}
status() { curl -s -o /dev/null -w '%{http_code}' --max-time 10 "$@"; }
# "yes" when the page at $2 (on host $1) answers 200 and contains $3, otherwise why not.
# The body is read in full before matching: `curl | grep -q` under pipefail fails at random
# when grep exits early and curl dies on the broken pipe.
serves() {
    local out code body
    out="$(curl -s --max-time 10 -H "Host: $1" -w '\n%{http_code}' "$BASE$2")" || { echo "no (curl failed)"; return; }
    code="${out##*$'\n'}" body="${out%$'\n'*}"
    if [[ "$code" != 200 ]]; then echo "no (HTTP $code)"
    elif [[ "$body" == *"$3"* ]]; then echo yes
    else echo "no (HTTP 200, page lacks $3)"; fi
}

LANDING_MARK='id="download"'
APP_MARK='<div id="root">'

check "central opens on landing page" yes "$(serves "$CENTRAL" / "$LANDING_MARK")"
check "school opens on landing page"  yes "$(serves "$SCHOOL" / "$LANDING_MARK")"
check "landing assets on school host" 200 "$(status -H "Host: $SCHOOL" "$BASE/landing/motion.css")"
check "landing offers school sign-in" yes "$(serves "$SCHOOL" / 'href="/login"')"
if [[ -z "${SMOKE_SKIP_WEB:-}" ]]; then
    check "school sign-in is the web app" yes "$(serves "$SCHOOL" /login "$APP_MARK")"
    check "school deep link fallback"     yes "$(serves "$SCHOOL" /admin/login "$APP_MARK")"
fi
check "api health through lb"      200 "$(status -H "Host: $SCHOOL" "$BASE/up")"
if [[ -n "${SMOKE_TLS_URL:-}" ]]; then
    # The origin's own certificate is Cloudflare-only (or self-signed), so it is not verified here.
    check "origin serves HTTPS"        200 "$(status -k -H "Host: $SCHOOL" "$SMOKE_TLS_URL/up")"
    check "landing over HTTPS"         200 "$(status -k -H "Host: $SCHOOL" "$SMOKE_TLS_URL/")"
fi
check "unknown school is refused"  404 "$(status -H "Host: no-such-school-$$.${SCHOOL#*.}" -H 'Accept: application/json' "$BASE/api/auth/me")"
check "anonymous api is refused"   401 "$(status -H "Host: $SCHOOL" -H 'Accept: application/json' "$BASE/api/auth/me")"

if [[ -n "${SMOKE_LOGIN_EMAIL:-}" && -n "${SMOKE_LOGIN_PASSWORD:-}" ]]; then
    token=$(curl -s --max-time 10 -H "Host: $SCHOOL" -H 'Accept: application/json' -X POST "$BASE/api/auth/login" \
        --data-urlencode "email=$SMOKE_LOGIN_EMAIL" --data-urlencode "password=$SMOKE_LOGIN_PASSWORD" \
        | python3 -c 'import sys,json; print(json.load(sys.stdin).get("token",""))' 2>/dev/null)
    check "school sign-in returns a token" yes "$([[ -n "$token" ]] && echo yes || echo no)"
    check "token works on its own school"  200 "$(status -H "Host: $SCHOOL" -H 'Accept: application/json' -H "Authorization: Bearer $token" "$BASE/api/auth/me")"
    check "token refused on central host"  404 "$(status -H "Host: $CENTRAL" -H 'Accept: application/json' -H "Authorization: Bearer $token" "$BASE/api/auth/me")"
fi

exit $fail
