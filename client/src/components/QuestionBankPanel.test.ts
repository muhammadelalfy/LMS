import { describe, expect, it } from "vitest";
import { questionBankSnapshot } from "./QuestionBankPanel";
import type { QuestionBankQuestion } from "@/lib/laravelApi";

describe("question bank selection", () => {
  it("copies typed question content into an exam snapshot without the bank id", () => {
    const item: QuestionBankQuestion = {
      id: 41,
      type: "geometry",
      title: "مساحة مستطيل",
      grade: "الأول الإعدادي",
      prompt_html: "<p>احسب المساحة.</p>",
      options: { shape: "rectangle", dimensions: { width: "6", height: "4" } },
      correct_answer: "24",
      points: 4,
      sort_order: 7,
      tags: "هندسة",
      is_active: true,
      department_id: 2,
    };
    const snapshot = questionBankSnapshot(item);
    expect(snapshot).toMatchObject({ type: "geometry", prompt_html: item.prompt_html, options: item.options, points: 4, sort_order: 0 });
    expect(snapshot).not.toHaveProperty("id");
  });
});
