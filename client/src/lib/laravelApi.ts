import { enqueueMutation, readMutationQueue, replaceMutationQueue } from "./offlineStore";

export type Role = "admin" | "teacher" | "parent" | "student";

export type ApiUser = { id: number; name: string; email: string; role: Role; student_account?: { student?: Student } | null };
export type Student = { id: number; name: string; group: string; grade: string; phone: string; parent_phone?: string | null; status: "excellent" | "average" | "weak"; assignments_count?: number; attendance_records_count?: number; exam_results_count?: number; payments_count?: number };
export type Worksheet = { id: number; title: string; subject: string; grade: string; status: "draft" | "published"; assignments_count?: number; submitted_count?: number; assignments?: Assignment[] };
export type PluginProduct = { id: number; slug: string; name: string; description?: string | null; version: string; module_name: string; price: string; purchased: boolean; installed: boolean; installed_module?: string | null; metadata?: Record<string, unknown> | null };
export type Assignment = { id: number; status: "assigned" | "in_progress" | "submitted" | "graded"; score?: number | null; max_score?: number | null; feedback?: string | null; worksheet?: Worksheet; student?: Student };
export type Attendance = { id: number; student_id: number; date_at: string; attendance_date?: string | null; status: "present" | "absent" | "late"; note?: string | null; student?: Student };
export type StudentQr = { student_id: number; payload: string; generated_at: string };
export type ExamResult = { id: number; student_id: number; title: string; score: number; max_score: number; taken_at: string; student?: Student };
export type Payment = { id: number; student_id: number; amount: number; status: "pending" | "paid" | "overdue"; due_at: string; paid_at?: string | null; note?: string | null; student?: Student };

const API_URL = (import.meta.env.VITE_LARAVEL_API_URL || "/api").replace(/\/$/, "");
const TOKEN_KEY = "al-imtiaz-laravel-token";

export class ApiError extends Error { constructor(public status: number, message: string) { super(message); } }

async function request<T>(path: string, init: RequestInit = {}): Promise<T> {
  const token = window.localStorage.getItem(TOKEN_KEY);
  const method = (init.method || "GET").toUpperCase();
  const headers = { Accept: "application/json", "Content-Type": "application/json", ...(token ? { Authorization: `Bearer ${token}` } : {}), ...(init.headers || {}) };
  if (!navigator.onLine && method !== "GET") {
    enqueueMutation({ path, method, body: typeof init.body === "string" ? init.body : undefined });
    throw new ApiError(0, "تم حفظ العملية محلياً وستتم مزامنتها عند عودة الاتصال.");
  }
  try {
    const response = await fetch(`${API_URL}${path}`, { ...init, headers });
    const body = await response.json().catch(() => null);
    if (!response.ok) throw new ApiError(response.status, body?.message || "تعذر إتمام الطلب");
    return body as T;
  } catch (error) {
    if (method !== "GET" && !(error instanceof ApiError && error.status > 0)) {
      enqueueMutation({ path, method, body: typeof init.body === "string" ? init.body : undefined });
      throw new ApiError(0, "تعذر الاتصال. تم حفظ العملية للمزامنة لاحقاً.");
    }
    throw error;
  }
}

export async function syncOfflineQueue(): Promise<number> {
  if (!navigator.onLine) return 0;
  const queue = readMutationQueue();
  const remaining = [...queue]; let synced = 0;
  for (const mutation of queue) {
    try {
      const token = window.localStorage.getItem(TOKEN_KEY);
      const response = await fetch(`${API_URL}${mutation.path}`, { method: mutation.method, body: mutation.body, headers: { Accept: "application/json", "Content-Type": "application/json", ...(token ? { Authorization: `Bearer ${token}` } : {}) } });
      if (!response.ok) throw new Error("sync failed");
      remaining.splice(remaining.findIndex(item => item.id === mutation.id), 1); synced += 1;
    } catch { break; }
  }
  replaceMutationQueue(remaining);
  return synced;
}

export const laravelApi = {
  getToken: () => window.localStorage.getItem(TOKEN_KEY),
  async login(payload: { email: string; password: string }) { const result = await request<{ user: ApiUser; token: string }>("/auth/login", { method: "POST", body: JSON.stringify(payload) }); window.localStorage.setItem(TOKEN_KEY, result.token); return result.user; },
  async loginAsRole(role: "admin" | "parent" | "student", payload: { email: string; password: string }) { const result = await request<{ user: ApiUser; token: string; login_type: string }>(`/auth/${role}/login`, { method: "POST", body: JSON.stringify(payload) }); window.localStorage.setItem(TOKEN_KEY, result.token); return result.user; },
  async register(payload: { name: string; email: string; password: string; password_confirmation: string; role: "parent" | "student" }) { const result = await request<{ user: ApiUser; token: string }>("/auth/register", { method: "POST", body: JSON.stringify(payload) }); window.localStorage.setItem(TOKEN_KEY, result.token); return result.user; },
  async me() { return request<ApiUser>("/auth/me"); },
  async logout() { await request("/auth/logout", { method: "POST" }); window.localStorage.removeItem(TOKEN_KEY); },
  async students(filters: { grade?: string; group?: string; search?: string } = {}) { const query = new URLSearchParams(Object.entries(filters).filter(([, value]) => value).map(([key, value]) => [key, String(value)])).toString(); const result = await request<{ data: Student[] }>(`/students${query ? `?${query}` : ""}`); return result.data; },
  async student(studentId: number) { return request<Student>(`/students/${studentId}`); },
  async createStudent(payload: Omit<Student, "id">) { return request<Student>("/students", { method: "POST", body: JSON.stringify(payload) }); },
  async updateStudent(id: number, payload: Partial<Student>) { return request<Student>(`/students/${id}`, { method: "PUT", body: JSON.stringify(payload) }); },
  async deleteStudent(id: number) { return request<void>(`/students/${id}`, { method: "DELETE" }); },
  async studentQr(studentId: number) { return request<StudentQr>(`/students/${studentId}/qr`); },
  async worksheets() { const result = await request<{ data: Worksheet[] }>("/worksheets"); return result.data; },
  async attendance() { const result = await request<{ data: Attendance[] }>("/attendance"); return result.data; },
  async exams() { const result = await request<{ data: ExamResult[] }>("/exams"); return result.data; },
  async payments() { const result = await request<{ data: Payment[] }>("/payments"); return result.data; },
  async createAttendance(payload: Omit<Attendance, "id" | "student">) { return request<Attendance>("/attendance", { method: "POST", body: JSON.stringify(payload) }); },
  async scanAttendance(payload: string) { return request<{ already_recorded: boolean; attendance: Attendance }>("/attendance/scan", { method: "POST", body: JSON.stringify({ payload }) }); },
  async updateAttendance(id: number, payload: Partial<Attendance>) { return request<Attendance>(`/attendance/${id}`, { method: "PUT", body: JSON.stringify(payload) }); },
  async deleteAttendance(id: number) { return request<void>(`/attendance/${id}`, { method: "DELETE" }); },
  async createExam(payload: Omit<ExamResult, "id" | "student">) { return request<ExamResult>("/exams", { method: "POST", body: JSON.stringify(payload) }); },
  async updateExam(id: number, payload: Partial<ExamResult>) { return request<ExamResult>(`/exams/${id}`, { method: "PUT", body: JSON.stringify(payload) }); },
  async deleteExam(id: number) { return request<void>(`/exams/${id}`, { method: "DELETE" }); },
  async createPayment(payload: Omit<Payment, "id" | "student">) { return request<Payment>("/payments", { method: "POST", body: JSON.stringify(payload) }); },
  async updatePayment(id: number, payload: Partial<Payment>) { return request<Payment>(`/payments/${id}`, { method: "PUT", body: JSON.stringify(payload) }); },
  async deletePayment(id: number) { return request<void>(`/payments/${id}`, { method: "DELETE" }); },
  async reportSummary() { return request<{ students: number; attendance: Record<string, number>; exams: { score: number; max_score: number }; payments: Payment[] }>("/reports/summary"); },
  async plugins() { const result = await request<{ data: PluginProduct[] }>("/plugins"); return result.data; },
  async purchasePlugin(id: number) { return request(`/plugins/${id}/purchase`, { method: "POST" }); },
  async installPlugin(id: number) { return request<{ module: { module_name: string; version: string }; message: string }>(`/plugins/${id}/install`, { method: "POST" }); },
  async uninstallPlugin(id: number) { return request(`/plugins/${id}/install`, { method: "DELETE" }); },
};
