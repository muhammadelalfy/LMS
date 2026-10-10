# Load tests

k6 scenarios for the acceptance targets in `zewal-mobile/docs/SYSTEM_DESIGN.md` §7
(NFR-PERF-01…07). **They have not been run**: k6 is not installed on the
development machine, and the numbers only mean something on a staging cluster
sized like §3 of the design with production-like data. Run them there and
correct the design where they disagree.

| Script | Scenario | Pass |
|---|---|---|
| `s1_app_open.js` | 1,000 users/s opening the app | p95 ≤ 300 ms, errors < 0.1 % |
| `s2_qr_rush.js` | 200 scans/s, 10 % duplicates | p95 ≤ 1 s, one row per student per day |
| `s3_exam.js` | thousands of sessions saving answer batches every 10 s | zero lost answers, p95 ≤ 500 ms |
| `s5_chat_sockets.js` | 1,000+ sockets, 2 messages/s | p95 delivery ≤ 500 ms |

S4 (announcement fan-out) is driven from the queue side: send one notice to
20,000 seeded accounts and watch `critical`/`chat`/`whatsapp`/`sms` queue depth
and age. S6 (online classes) uses `lk load-test` from LiveKit's CLI. S7 and S8
(failure drills, soak) are operational runs: kill a node, run S1 for six hours.

```
k6 run -e BASE_URL=https://staging.example.com/api -e RATE=1000 loadtests/s1_app_open.js
```
