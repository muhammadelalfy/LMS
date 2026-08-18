import React from "react";
import { describe, expect, it } from "vitest";
import { renderToStaticMarkup } from "react-dom/server";
import { ExamPaper, exportExamPaperFromBrowser, exportExamPaperWithFallback } from "./ExamPaperPreview";
import type { ExamTemplate } from "@/lib/laravelApi";

const template: ExamTemplate = {
  id: 1,
  title: "اختبار الجبر",
  grade: "الأول الإعدادي",
  duration_minutes: 45,
  instructions: "أجب عن جميع الأسئلة.",
  watermark_text: "نسخة الطالب",
  watermark_opacity: 12,
  status: "published",
  questions: [
    { id: 10, type: "mcq", prompt_html: "<p>اختر الإجابة الصحيحة</p>", options: ["أ", "ب"], points: 2 },
    { id: 11, type: "essay", prompt_html: "<p>اشرح خطوات الحل</p>", options: null, points: 3 },
  ],
};

describe("ExamPaper", () => {
  it("renders the student-facing paper metadata, questions, options, and watermark", () => {
    const html = renderToStaticMarkup(<ExamPaper template={template} />);
    expect(html).toContain("اختبار الجبر");
    expect(html).toContain("الأول الإعدادي");
    expect(html).toContain("نسخة الطالب");
    expect(html).toContain("السؤال 1");
    expect(html).toContain("أ");
    expect(html).toContain("exam-option-checkbox");
    expect(html).toContain("exam-paper-answer-lines");
  });
});

it("exports the exact paper image through the browser PDF helper", async () => {
  const calls: string[] = [];
  const fakePdf = class {
    internal = { pageSize: { getWidth: () => 595, getHeight: () => 842 } };
    addPage() { calls.push("page"); }
    addImage() { calls.push("image"); }
    save(filename: string) { calls.push(filename); }
  };
  await exportExamPaperFromBrowser({ scrollWidth: 600 } as HTMLElement, 7, {
    capture: async () => ({ width: 600, height: 1200, toDataURL: () => "data:image/png;base64,exam" }),
    Pdf: fakePdf,
  });
  expect(calls).toContain("image");
  expect(calls).toContain("exam-7.pdf");
});

it("uses the fallback only when browser capture fails", async () => {
  const calls: string[] = [];
  await exportExamPaperWithFallback(
    {} as HTMLElement,
    7,
    async () => { calls.push("browser"); throw new Error("capture failed"); },
    async () => { calls.push("fallback"); },
  );
  expect(calls).toEqual(["browser", "fallback"]);
});
