// S3: an exam. SESSIONS students each save a batch of answers every 10 s for 30
// minutes. Pass: zero lost answers (compare the answer count afterwards), p95 <= 500 ms.
// Needs SESSIONS pre-started sessions: pass -e SESSION_IDS=1,2,... and
// -e QUESTION_IDS=11,12,13 (question ids of the template), and one student token
// per session via -e TOKENS=t1,t2,... (or reuse one token for a smoke run).
import http from 'k6/http';
import { check, sleep } from 'k6';
import { BASE, auth } from './common.js';

const SESSION_IDS = (__ENV.SESSION_IDS || '').split(',').filter(Boolean);
const QUESTION_IDS = (__ENV.QUESTION_IDS || '').split(',').filter(Boolean).map(Number);
const TOKENS = (__ENV.TOKENS || '').split(',').filter(Boolean);

export const options = {
  scenarios: {
    exam: { executor: 'per-vu-iterations', vus: SESSION_IDS.length || 1, iterations: 180, maxDuration: '35m' },
  },
  thresholds: { http_req_failed: ['rate<0.001'], http_req_duration: ['p(95)<500'] },
};

export default function () {
  const i = (__VU - 1) % Math.max(SESSION_IDS.length, 1);
  const params = auth(TOKENS[i % Math.max(TOKENS.length, 1)] || TOKENS[0]);
  const changed = QUESTION_IDS.slice(0, 1 + (__ITER % QUESTION_IDS.length)).map((id) => ({ question_id: id, answer: `answer-${__ITER}` }));
  const res = http.post(`${BASE}/exam-sessions/${SESSION_IDS[i]}/answers/batch`, JSON.stringify({ answers: changed }), params);
  check(res, { saved: (r) => r.status === 200 });
  sleep(10);
}
