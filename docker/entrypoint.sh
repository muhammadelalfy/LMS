#!/bin/sh
set -eu

# Config/route/event/view caches live in this container's own bootstrap/cache,
# so each replica builds them from its own environment on start.
php artisan optimize --no-ansi >/dev/null

exec "$@"
