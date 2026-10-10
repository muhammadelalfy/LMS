// Shared helpers for the k6 scenarios in docs/SYSTEM_DESIGN.md section 7.
// Run:  k6 run -e BASE_URL=https://staging.example.com/api loadtests/s1_app_open.js
import http from 'k6/http';
import { check, fail } from 'k6';

export const BASE = __ENV.BASE_URL || 'http://127.0.0.1:8010/api';
export const JSON_HEADERS = { 'Content-Type': 'application/json', Accept: 'application/json' };

export function login(role, email, password) {
  const res = http.post(`${BASE}/auth/${role}/login`, JSON.stringify({ email, password }), { headers: JSON_HEADERS });
  if (!check(res, { [`${role} login 200`]: (r) => r.status === 200 })) fail(`login failed for ${role}: ${res.status}`);
  return res.json('token');
}

export function auth(token) {
  return { headers: { ...JSON_HEADERS, Authorization: `Bearer ${token}` } };
}

// Credentials of seeded demo accounts; override for staging with -e TEACHER_EMAIL=...
export const TEACHER = { email: __ENV.TEACHER_EMAIL || 'teacher@local.test', password: __ENV.TEACHER_PASSWORD || 'TeacherLocal!2026' };
export const STUDENT = { email: __ENV.STUDENT_EMAIL || 'student0@local.test', password: __ENV.STUDENT_PASSWORD || 'StudentLocal!2026' };
