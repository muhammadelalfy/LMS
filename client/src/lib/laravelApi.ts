export type Role = "admin" | "teacher" | "parent" | "student";

export type ApiUser = { id: number; name: string; email: string; role: Role; student_account?: { student?: Student } | null };
export type Student = { id: number; name: string; group: string; grade: string; phone: string; parent_phone?: string | null; status: "excellent" | "average" | "weak"; assignments_count?: number; attendance_records_count?: number; exam_results_count?: number; payments_count?: number };
export type Worksheet = { id: number; title: string; subject: string; grade: string; status: "draft" | "published"; assignments_count?: number; submitted_count?: number; assignments?: Assignment[] };
export type Assignment = { id: number; status: "assigned" | "in_progress" | "submitted" | "graded"; score?: number | null; max_score?: number | null; feedback?: string | null; worksheet?: Worksheet; student?: Student };
export type Attendance = { id: number; student_id: number; date_at: string; status: "present" | "absent" | "late"; note?: string | null; student?: Student };
export type ExamResult = { id: number; student_id: number; title: string; score: number; max_score: number; taken_at: string; student?: Student };
export type Payment = { id: number; student_id: number; amount: number; status: "pending" | "paid" | "overdue"; due_at: string; paid_at?: string | null; note?: string | null; student?: Student };

const API_URL = (import.meta.env.VITE_LARAVEL_API_URL || "/api").replace(/\/$/, "");
const TOKEN_KEY = "al-imtiaz-laravel-token";

export class ApiError extends Error { constructor(public status: number, message: string) { super(message); } }

async function request<T>(path: string, init: RequestInit = {}): Promise<T> {
  const token = window.localStorage.getItem(TOKEN_KEY);
  const response = await fetch(`${API_URL}${path}`, { ...init, headers: { Accept: "application/json", "Content-Type": "application/json", ...(token ? { Authorization: `Bearer ${token}` } : {}), ...(init.headers || {}) } });
  const body = await response.json().catch(() => null);
  if (!response.ok) throw new ApiError(response.status, body?.message || "تعذر إتمام الطلب");
  return body as T;
}

export const laravelApi = {
  getToken: () => window.localStorage.getItem(TOKEN_KEY),
  async login(payload: { email: string; password: string }) { const result = await request<{ user: ApiUser; token: string }>("/auth/login", { method: "POST", body: JSON.stringify(payload) }); window.localStorage.setItem(TOKEN_KEY, result.token); return result.user; },
  async register(payload: { name: string; email: string; password: string; password_confirmation: string; role: "parent" | "student" }) { const result = await request<{ user: ApiUser; token: string }>("/auth/register", { method: "POST", body: JSON.stringify(payload) }); window.localStorage.setItem(TOKEN_KEY, result.token); return result.user; },
  async me() { return request<ApiUser>("/auth/me"); },
  async logout() { await request("/auth/logout", { method: "POST" }); window.localStorage.removeItem(TOKEN_KEY); },
  async students() { const result = await request<{ data: Student[] }>("/students"); return result.data; },
  async worksheets() { const result = await request<{ data: Worksheet[] }>("/worksheets"); return result.data; },
  async attendance() { const result = await request<{ data: Attendance[] }>("/attendance"); return result.data; },
  async exams() { const result = await request<{ data: ExamResult[] }>("/exams"); return result.data; },
  async payments() { const result = await request<{ data: Payment[] }>("/payments"); return result.data; },
  async createAttendance(payload: Omit<Attendance, "id" | "student">) { return request<Attendance>("/attendance", { method: "POST", body: JSON.stringify(payload) }); },
  async updateAttendance(id: number, payload: Partial<Attendance>) { return request<Attendance>(`/attendance/${id}`, { method: "PUT", body: JSON.stringify(payload) }); },
  async deleteAttendance(id: number) { return request<void>(`/attendance/${id}`, { method: "DELETE" }); },
  async createExam(payload: Omit<ExamResult, "id" | "student">) { return request<ExamResult>("/exams", { method: "POST", body: JSON.stringify(payload) }); },
  async updateExam(id: number, payload: Partial<ExamResult>) { return request<ExamResult>(`/exams/${id}`, { method: "PUT", body: JSON.stringify(payload) }); },
  async deleteExam(id: number) { return request<void>(`/exams/${id}`, { method: "DELETE" }); },
  async createPayment(payload: Omit<Payment, "id" | "student">) { return request<Payment>("/payments", { method: "POST", body: JSON.stringify(payload) }); },
  async updatePayment(id: number, payload: Partial<Payment>) { return request<Payment>(`/payments/${id}`, { method: "PUT", body: JSON.stringify(payload) }); },
  async deletePayment(id: number) { return request<void>(`/payments/${id}`, { method: "DELETE" }); },
  async reportSummary() { return request<{ students: number; attendance: Record<string, number>; exams: { score: number; max_score: number }; payments: Payment[] }>("/reports/summary"); },
};
