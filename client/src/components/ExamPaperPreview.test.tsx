import React from "react";
import { describe, expect, it } from "vitest";
import { renderToStaticMarkup } from "react-dom/server";
import { ExamPaper } from "./ExamPaperPreview";
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
