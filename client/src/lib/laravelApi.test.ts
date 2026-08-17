import { beforeEach, describe, expect, it, vi } from "vitest";
import { laravelApi } from "./laravelApi";

const storage = new Map<string, string>();

beforeEach(() => {
  storage.clear();
  vi.stubGlobal("window", { localStorage: { getItem: (key: string) => storage.get(key) ?? null, setItem: (key: string, value: string) => storage.set(key, value), removeItem: (key: string) => storage.delete(key) } });
  vi.stubGlobal("navigator", { onLine: true });
  vi.restoreAllMocks();
});

describe("laravelApi", () => {
  it("stores the Sanctum token after login and sends it on protected requests", async () => {
    const fetchMock = vi.fn()
      .mockResolvedValueOnce(new Response(JSON.stringify({ user: { id: 1, name: "Teacher", email: "teacher@example.com", role: "teacher" }, token: "sanctum-token" }), { status: 200 }))
      .mockResolvedValueOnce(new Response(JSON.stringify({ data: [] }), { status: 200 }));
    vi.stubGlobal("fetch", fetchMock);

    await laravelApi.login({ email: "teacher@example.com", password: "secret" });
    await laravelApi.attendance();

    expect(storage.get("al-imtiaz-laravel-token")).toBe("sanctum-token");
    expect(fetchMock.mock.calls[1]?.[1]).toMatchObject({ headers: expect.objectContaining({ Authorization: "Bearer sanctum-token", Accept: "application/json" }) });
  });

  it("unwraps collection responses through the shared collection helper", async () => {
    const fetchMock = vi.fn().mockResolvedValue(new Response(JSON.stringify({ data: [{ id: 1, name: "طالب" }] }), { status: 200 }));
    vi.stubGlobal("fetch", fetchMock);

    await expect(laravelApi.students()).resolves.toEqual([{ id: 1, name: "طالب" }]);
    expect(fetchMock).toHaveBeenCalledWith("/api/students", expect.anything());
  });

  it("maps attendance and exam CRUD operations to the Laravel resources", async () => {
    const fetchMock = vi.fn().mockImplementation(() => new Response(JSON.stringify({ id: 4 }), { status: 200 }));
    vi.stubGlobal("fetch", fetchMock);

    await laravelApi.createAttendance({ student_id: 2, date_at: "2026-08-15", status: "present", note: null });
    await laravelApi.updateExam(7, { score: 18 });
    await laravelApi.deleteAttendance(4);

    expect(fetchMock.mock.calls.map(([url, init]) => [url, init?.method])).toEqual([["/api/attendance", "POST"], ["/api/exams/7", "PUT"], ["/api/attendance/4", "DELETE"]]);
  });

  it("maps QR generation and scan requests to the protected Laravel endpoints", async () => {
    const fetchMock = vi.fn().mockImplementation(() => new Response(JSON.stringify({ student_id: 2, payload: "q".repeat(64), generated_at: "2026-08-15T10:00:00Z", already_recorded: false, attendance: { id: 8 } }), { status: 200 }));
    vi.stubGlobal("fetch", fetchMock);

    await laravelApi.studentQr(2);
    await laravelApi.scanAttendance("q".repeat(64));

    expect(fetchMock.mock.calls.map(([url, init]) => [url, init?.method])).toEqual([["/api/students/2/qr", undefined], ["/api/attendance/scan", "POST"]]);
  });

  it("surfaces Laravel authorization failures as ApiError status values", async () => {
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue(new Response(JSON.stringify({ message: "Forbidden" }), { status: 403 })));
    await expect(laravelApi.exams()).rejects.toMatchObject({ status: 403, message: "Forbidden" });
  });

  it("maps role-specific logins to dedicated Laravel portals", async () => {
    const fetchMock = vi.fn().mockResolvedValue(new Response(JSON.stringify({ user: { id: 1, name: "مدير", email: "admin@test.local", role: "admin" }, token: "admin-token", login_type: "admin" }), { status: 200 }));
    vi.stubGlobal("fetch", fetchMock);
    await laravelApi.loginAsRole("admin", { email: "admin@test.local", password: "Secret123!" });
    expect(fetchMock.mock.calls[0]?.[0]).toBe("/api/auth/admin/login");
  });

  it("queues mutating requests when the browser is offline", async () => {
    vi.stubGlobal("navigator", { onLine: false });
    vi.stubGlobal("fetch", vi.fn());
    await expect(laravelApi.deleteAttendance(10)).rejects.toMatchObject({ status: 0 });
    expect(JSON.parse(storage.get("al-imtiaz-offline-mutations") || "[]")).toHaveLength(1);
  });
});
