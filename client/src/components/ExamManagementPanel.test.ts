import { describe, expect, it, vi } from "vitest";
import { appendQuestionToExam, buildExamTemplatePayload, persistExamTemplate } from "./ExamManagementPanel";
import { laravelApi } from "@/lib/laravelApi";
import type { AuthoringQuestion } from "./ExamQuestionComposer";

describe("ExamManagementPanel question payloads", () => {
  it("routes create and edit payloads to the corresponding Laravel mutations", async () => {
    const create = vi.spyOn(laravelApi, "createExamTemplate").mockResolvedValue({} as never);
    const update = vi.spyOn(laravelApi, "updateExamTemplate").mockResolvedValue({} as never);
    const payload = buildExamTemplatePayload({ editingId: null, departmentId: "", title: "امتحان", grade: "أولى", duration: "30", instructions: "", watermark: "الامتياز", questions: [] });
    await persistExamTemplate(null, payload);
    await persistExamTemplate(12, { ...payload, questions: [{ id: 4, type: "math", prompt_html: "<p>من البنك</p>", options: { notation: "س^2" }, correct_answer: null, points: 2, sort_order: 0 }] });
    expect(create).toHaveBeenCalledWith(payload);
    expect(update).toHaveBeenCalledWith(12, expect.objectContaining({ questions: [expect.objectContaining({ id: 4, type: "math" })] }));
    create.mockRestore(); update.mockRestore();
  });

  it("appends a bank snapshot in canonical order and sends it for create/update", () => {
    const existing: AuthoringQuestion = { id: 9, type: "essay", prompt_html: "<p>محفوظ</p>", options: null, correct_answer: null, points: 2, sort_order: 0 };
    const selected = { type: "math" as const, prompt_html: "<p>مسألة من البنك</p>", options: { notation: "س^2" }, correct_answer: "٩", points: 3, sort_order: 0 };
    const questions = appendQuestionToExam([existing], selected);
    expect(questions.map(question => question.sort_order)).toEqual([0, 1]);
    expect(buildExamTemplatePayload({ editingId: 12, departmentId: "", title: "امتحان", grade: "أولى", duration: "30", instructions: "", watermark: "الامتياز", questions }).questions).toEqual(questions);
    expect(buildExamTemplatePayload({ editingId: null, departmentId: "", title: "امتحان", grade: "أولى", duration: "30", instructions: "", watermark: "الامتياز", questions }).questions).toEqual([
      { type: "essay", prompt_html: "<p>محفوظ</p>", options: null, correct_answer: null, points: 2, sort_order: 0 },
      { type: "math", prompt_html: "<p>مسألة من البنك</p>", options: { notation: "س^2" }, correct_answer: "٩", points: 3, sort_order: 1 },
    ]);
  });
});
