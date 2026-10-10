// S2: the door rush. Staff phones scan QR cards at RATE per second, 10 % of
// them duplicates. Pass: p95 <= 1 s; exactly one attendance row per student and
// day (check afterwards: SELECT student_id, attendance_date, COUNT(*) ... HAVING COUNT(*) > 1).
// QR_TOKENS: comma-separated student qr tokens (GET /students/{id}/qr for each).
import http from 'k6/http';
import { check } from 'k6';
import { BASE, TEACHER, auth, login } from './common.js';

const RATE = Number(__ENV.RATE || 200);
const TOKENS = (__ENV.QR_TOKENS || '').split(',').filter(Boolean);

export const options = {
  scenarios: {
    rush: { executor: 'constant-arrival-rate', rate: RATE, timeUnit: '1s', duration: '5m', preAllocatedVUs: 200, maxVUs: 1500 },
  },
  thresholds: { http_req_failed: ['rate<0.001'], http_req_duration: ['p(95)<1000'] },
};

export function setup() {
  if (TOKENS.length === 0) throw new Error('pass -e QR_TOKENS=tok1,tok2,...');
  return { token: login('teacher', TEACHER.email, TEACHER.password) };
}

export default function (data) {
  const payload = Math.random() < 0.1 ? TOKENS[0] : TOKENS[Math.floor(Math.random() * TOKENS.length)];
  const res = http.post(
    `${BASE}/attendance/scan`,
    JSON.stringify({ payload, now: new Date().toISOString(), confirm_outside_session: true }),
    auth(data.token),
  );
  check(res, { 'recorded or already recorded': (r) => r.status === 200 || r.status === 201 });
}
