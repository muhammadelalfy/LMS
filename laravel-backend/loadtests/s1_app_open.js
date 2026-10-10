// S1: everyone opens the app at class time. Ramp to RATE users per second;
// each loads what the home page loads. Pass: p95 <= 300 ms, errors < 0.1 %.
import http from 'k6/http';
import { check } from 'k6';
import { BASE, STUDENT, TEACHER, auth, login } from './common.js';

const RATE = Number(__ENV.RATE || 1000);

export const options = {
  scenarios: {
    burst: {
      executor: 'ramping-arrival-rate',
      startRate: 10,
      timeUnit: '1s',
      preAllocatedVUs: 500,
      maxVUs: 5000,
      stages: [
        { target: RATE, duration: '10s' },
        { target: RATE, duration: '60s' },
        { target: 0, duration: '10s' },
      ],
    },
  },
  thresholds: {
    http_req_failed: ['rate<0.001'],
    http_req_duration: ['p(95)<300'],
  },
};

export function setup() {
  // One token per role, reused: this scenario measures the app, not the login hashing.
  return { teacher: login('teacher', TEACHER.email, TEACHER.password), student: login('student', STUDENT.email, STUDENT.password) };
}

export default function (tokens) {
  const staff = Math.random() < 0.2;
  const params = auth(staff ? tokens.teacher : tokens.student);
  const responses = http.batch([
    ['GET', `${BASE}/auth/me`, null, params],
    ['GET', `${BASE}/notifications`, null, params],
    ['GET', staff ? `${BASE}/reports/summary` : `${BASE}/duties`, null, params],
    ['GET', `${BASE}/conversations`, null, params],
  ]);
  responses.forEach((r) => check(r, { 'status 200': (x) => x.status === 200 }));
}
