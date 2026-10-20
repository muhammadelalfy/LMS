#!/usr/bin/env bash
# End-to-end checks through the load balancer. Run from deploy/ after `docker compose up -d --wait`.
#   ./smoke-test.sh <base-url> <central-host> <school-host>
# With SMOKE_LOGIN_EMAIL / SMOKE_LOGIN_PASSWORD set, also signs in to the school.
set -uo pipefail

BASE="${1:-http://localhost}"
CENTRAL="${2:?central host}"
SCHOOL="${3:?school host}"
fail=0

check() { # name expected actual
    if [[ "$3" == "$2" ]]; then echo "ok   $1 ($3)"; else echo "FAIL $1: expected $2, got $3"; fail=1; fi
}
status() { curl -s -o /dev/null -w '%{http_code}' --max-time 10 "$@"; }
# "yes" when the page at $2 (on host $1) is served and contains $3.
serves() { curl -sf --max-time 10 -H "Host: $1" "$BASE$2" | grep -qF "$3" && echo yes || echo no; }

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
