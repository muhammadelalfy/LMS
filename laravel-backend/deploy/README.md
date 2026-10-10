# Deploying for scale

Templates for the architecture in `zewal-mobile/docs/SYSTEM_DESIGN.md` §2–3.
**None of these files has been run** — Docker, Redis and k6 are not installed on
the development machine. Treat them as a starting point to adapt and then prove with
`loadtests/`.

| File | What it is |
|---|---|
| `docker-compose.yml` | API nodes (Octane), Reverb WebSocket nodes, scheduler, one worker pool per queue, MySQL, three Redis roles, LiveKit |
| `nginx.conf` | edge: WebSockets on `/app`, API everywhere else |
| `livekit.yaml` | LiveKit server with TURN fallback and the webhook to this API |

## Environment (production)

```
APP_ENV=production            CACHE_STORE=redis        QUEUE_CONNECTION=redis     SESSION_DRIVER=redis
BROADCAST_CONNECTION=reverb   REVERB_APP_ID= REVERB_APP_KEY= REVERB_APP_SECRET= REVERB_HOST= REVERB_PORT=443 REVERB_SCHEME=https
LIVEKIT_URL=wss://media.example.com  LIVEKIT_API_KEY=  LIVEKIT_API_SECRET=
FIREBASE_CREDENTIALS=         SMS_*  WHATSAPP_*        (see .env.example)
CENTRAL_DOMAINS=api.example.com   TENANT_BASE_DOMAIN=example.com   CENTRAL_API_TOKEN=<long random>
TENANCY_DB_PREFIX=school_     TENANCY_DB_SUFFIX=          (MySQL user needs CREATE/DROP DATABASE)
```

## Queues

Jobs name their queue; run a worker pool for each so they cannot starve one another:

| Queue | What runs on it | Pool |
|---|---|---|
| `critical` | call invitations and endings | small, always warm |
| `chat` | chat broadcasts | sized by message rate |
| `whatsapp`, `sms` | paid-channel sends (rate-limited per channel in the job) | sized to the provider's rate |
| `default`, `low` | everything else | small |

## Before the first deploy

1. `php artisan migrate --force` (the central tables), then `php artisan tenants:migrate --force` to bring every school up to date. An existing single-school install becomes a school with `php artisan school:adopt <code> --database=<file>`; new ones are opened with `school:create` or the operator API.
2. Set one teacher on each group (`class_groups.teacher_id`) — until then every teacher may talk to that group's students.
3. `php artisan config:cache route:cache event:cache`.
4. Point the LiveKit webhook at the **central** address, `https://api.example.com/api/webhooks/livekit` (one URL for all schools), register `https://api.example.com/api/integrations/{google,zoom}/callback` as the OAuth redirects, and verify with a test call.
5. Run `loadtests/` against staging and compare with the targets.
6. Wildcard DNS (`*.example.com` to the load balancer) and a wildcard TLS certificate, so a new school's address works the moment it is opened. SMS, WhatsApp and Fawry callbacks are set to each school's own address (`https://<school>.example.com/api/webhooks/...`). Keep one queue worker running: the scheduler queues one job per school.
