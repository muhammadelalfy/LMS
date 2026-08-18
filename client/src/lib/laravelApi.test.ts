import { beforeEach, describe, expect, it, vi } from "vitest";
import { laravelApi } from "./laravelApi";

const storage = new Map<string, string>();

beforeEach(() => {
  storage.clear();
  vi.stubGlobal("window", {
    localStorage: {
      getItem: (key: string) => storage.get(key) ?? null,
      setItem: (key: string, value: string) => storage.set(key, value),
      removeItem: (key: string) => storage.delete(key),
    },
  });
  vi.stubGlobal("navigator", { onLine: true });
  vi.restoreAllMocks();
});

describe("laravelApi", () => {
  it("stores the Sanctum token after login and sends it on protected requests", async () => {
    const fetchMock = vi
      .fn()
      .mockResolvedValueOnce(
        new Response(
          JSON.stringify({
            user: {
              id: 1,
              name: "Teacher",
              email: "teacher@example.com",
              role: "teacher",
            },
            token: "sanctum-token",
          }),
          { status: 200 }
        )
      )
      .mockResolvedValueOnce(
        new Response(JSON.stringify({ data: [] }), { status: 200 })
      );
    vi.stubGlobal("fetch", fetchMock);

    await laravelApi.login({
      email: "teacher@example.com",
      password: "secret",
    });
    await laravelApi.attendance();

    expect(storage.get("al-imtiaz-laravel-token")).toBe("sanctum-token");
    expect(fetchMock.mock.calls[1]?.[1]).toMatchObject({
      headers: expect.objectContaining({
        Authorization: "Bearer sanctum-token",
        Accept: "application/json",
      }),
    });
  });

  it("unwraps collection responses through the shared collection helper", async () => {
    const fetchMock = vi.fn().mockResolvedValue(
      new Response(JSON.stringify({ data: [{ id: 1, name: "طالب" }] }), {
        status: 200,
      })
    );
    vi.stubGlobal("fetch", fetchMock);

    await expect(laravelApi.students()).resolves.toEqual([
      { id: 1, name: "طالب" },
    ]);
    expect(fetchMock).toHaveBeenCalledWith("/api/students", expect.anything());
  });

  it("maps attendance and exam CRUD operations to the Laravel resources", async () => {
    const fetchMock = vi
      .fn()
      .mockImplementation(
        () => new Response(JSON.stringify({ id: 4 }), { status: 200 })
      );
    vi.stubGlobal("fetch", fetchMock);

    await laravelApi.createAttendance({
      student_id: 2,
      date_at: "2026-08-15",
      status: "present",
      note: null,
    });
    await laravelApi.updateExam(7, { score: 18 });
    await laravelApi.deleteAttendance(4);

    expect(
      fetchMock.mock.calls.map(([url, init]) => [url, init?.method])
    ).toEqual([
      ["/api/attendance", "POST"],
      ["/api/exams/7", "PUT"],
      ["/api/attendance/4", "DELETE"],
    ]);
  });

  it("maps QR generation and scan requests to the protected Laravel endpoints", async () => {
    const fetchMock = vi.fn().mockImplementation(
      () =>
        new Response(
          JSON.stringify({
            student_id: 2,
            payload: "q".repeat(64),
            generated_at: "2026-08-15T10:00:00Z",
            already_recorded: false,
            attendance: { id: 8 },
          }),
          { status: 200 }
        )
    );
    vi.stubGlobal("fetch", fetchMock);

    await laravelApi.studentQr(2);
    await laravelApi.scanAttendance("q".repeat(64));

    expect(
      fetchMock.mock.calls.map(([url, init]) => [url, init?.method])
    ).toEqual([
      ["/api/students/2/qr", undefined],
      ["/api/attendance/scan", "POST"],
    ]);
  });

  it("surfaces Laravel authorization failures as ApiError status values", async () => {
    vi.stubGlobal(
      "fetch",
      vi.fn().mockResolvedValue(
        new Response(JSON.stringify({ message: "Forbidden" }), {
          status: 403,
        })
      )
    );
    await expect(laravelApi.exams()).rejects.toMatchObject({
      status: 403,
      message: "Forbidden",
    });
  });

  it("maps role-specific logins to dedicated Laravel portals", async () => {
    const fetchMock = vi.fn().mockResolvedValue(
      new Response(
        JSON.stringify({
          user: {
            id: 1,
            name: "مدير",
            email: "admin@test.local",
            role: "admin",
          },
          token: "admin-token",
          login_type: "admin",
        }),
        { status: 200 }
      )
    );
    vi.stubGlobal("fetch", fetchMock);
    await laravelApi.loginAsRole("admin", {
      email: "admin@test.local",
      password: "Secret123!",
    });
    expect(fetchMock.mock.calls[0]?.[0]).toBe("/api/auth/admin/login");
  });

  it("queues mutating requests when the browser is offline", async () => {
    vi.stubGlobal("navigator", { onLine: false });
    vi.stubGlobal("fetch", vi.fn());
    await expect(laravelApi.deleteAttendance(10)).rejects.toMatchObject({
      status: 0,
    });
    expect(
      JSON.parse(storage.get("al-imtiaz-offline-mutations") || "[]")
    ).toHaveLength(1);
  });
});


  it("maps exam template CRUD and monitored session actions to Laravel endpoints", async () => {
    const fetchMock = vi.fn().mockImplementation(() => new Response(JSON.stringify({ data: [], id: 3, camera_required: true }), { status: 200 }));
    vi.stubGlobal("fetch", fetchMock);
    await laravelApi.examTemplates();
    await laravelApi.createExamTemplate({ department_id: null, title: "جبر", grade: "أولى", duration_minutes: 30, instructions: "ابدأ", watermark_text: "الامتياز", watermark_opacity: 12, status: "draft", questions: [] });
    await laravelApi.updateExamTemplate(3, { status: "published" });
    await laravelApi.deleteExamTemplate(3);
    await laravelApi.startExamSession(3);
    await laravelApi.recordExamEvent(4, "focus_lost");
    await laravelApi.saveExamAnswer(4, 8, "٤");
    await laravelApi.submitExam(4);
    expect(fetchMock.mock.calls.map(([url, init]) => [url, init?.method])).toEqual([
      ["/api/exam-templates", undefined], ["/api/exam-templates", "POST"], ["/api/exam-templates/3", "PUT"], ["/api/exam-templates/3", "DELETE"], ["/api/exam-templates/3/start", "POST"], ["/api/exam-sessions/4/events", "POST"], ["/api/exam-sessions/4/answers", "POST"], ["/api/exam-sessions/4/submit", "POST"],
    ]);
  });

describe("exam PDF download", () => {
  it("downloads the protected PDF blob with the Sanctum token", async () => {
    storage.set("al-imtiaz-laravel-token", "student-token");
    const fetchMock = vi.fn().mockResolvedValue(new Response(new Blob(["%PDF-1.7"]), { status: 200 }));
    const click = vi.fn();
    vi.stubGlobal("fetch", fetchMock);
    vi.stubGlobal("URL", { createObjectURL: vi.fn(() => "blob:exam"), revokeObjectURL: vi.fn() });
    vi.stubGlobal("document", { createElement: vi.fn(() => ({ click })) });

    await laravelApi.downloadExamPdf(12);

    expect(fetchMock).toHaveBeenCalledWith("/api/exam-templates/12/pdf", expect.objectContaining({
      headers: { Accept: "application/pdf", Authorization: "Bearer student-token" },
    }));
    expect(click).toHaveBeenCalled();
  });
});

describe("question bank API", () => {
  it("maps searchable CRUD operations to the Laravel resource", async () => {
    const fetchMock = vi.fn().mockImplementation(() => new Response(JSON.stringify({ data: [], id: 11 }), { status: 200 }));
    vi.stubGlobal("fetch", fetchMock);
    await laravelApi.questionBank({ search: "هندسة", type: "geometry" });
    await laravelApi.createQuestionBankQuestion({ type: "math", title: "جبر", grade: "أولى", prompt_html: "<p>سؤال</p>", options: { notation: "س^2" }, correct_answer: "٩", points: 2, sort_order: 0, tags: "جبر", is_active: true, department_id: null });
    await laravelApi.updateQuestionBankQuestion(11, { title: "معدل" });
    await laravelApi.deleteQuestionBankQuestion(11);
    expect(fetchMock.mock.calls.map(([url, init]) => [url, init?.method])).toEqual([
      ["/api/question-bank?search=%D9%87%D9%86%D8%AF%D8%B3%D8%A9&type=geometry", undefined],
      ["/api/question-bank", "POST"],
      ["/api/question-bank/11", "PUT"],
      ["/api/question-bank/11", "DELETE"],
    ]);
  });
});
