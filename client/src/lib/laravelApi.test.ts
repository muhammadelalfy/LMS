import { beforeEach, describe, expect, it, vi } from "vitest";
import { laravelApi } from "./laravelApi";

const storage = new Map<string, string>();

beforeEach(() => {
  storage.clear();
  vi.stubGlobal("window", { localStorage: { getItem: (key: string) => storage.get(key) ?? null, setItem: (key: string, value: string) => storage.set(key, value), removeItem: (key: string) => storage.delete(key) } });
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

  it("maps attendance and exam CRUD operations to the Laravel resources", async () => {
    const fetchMock = vi.fn().mockImplementation(() => new Response(JSON.stringify({ id: 4 }), { status: 200 }));
    vi.stubGlobal("fetch", fetchMock);

    await laravelApi.createAttendance({ student_id: 2, date_at: "2026-08-15", status: "present", note: null });
    await laravelApi.updateExam(7, { score: 18 });
    await laravelApi.deleteAttendance(4);

    expect(fetchMock.mock.calls.map(([url, init]) => [url, init?.method])).toEqual([["/api/attendance", "POST"], ["/api/exams/7", "PUT"], ["/api/attendance/4", "DELETE"]]);
  });

  it("surfaces Laravel authorization failures as ApiError status values", async () => {
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue(new Response(JSON.stringify({ message: "Forbidden" }), { status: 403 })));
    await expect(laravelApi.exams()).rejects.toMatchObject({ status: 403, message: "Forbidden" });
  });
});
