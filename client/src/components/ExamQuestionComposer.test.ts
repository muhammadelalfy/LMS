import { describe, expect, it } from "vitest";
import { moveAuthoringQuestion, parseGeometryDimensions, removeAuthoringQuestion, replaceAuthoringQuestion, serializeAuthoringQuestions, type AuthoringQuestion } from "./ExamQuestionComposer";

describe("ExamQuestionComposer helpers", () => {
  it("parses dimension rows while ignoring malformed input", () => {
    expect(parseGeometryDimensions("width=6\nheight = 4\ninvalid\n = missing" )).toEqual({ width: "6", height: "4" });
  });

  it("supports add, edit, delete, and reorder transformations", () => {
    const first: AuthoringQuestion = { id: 1, type: "essay", prompt_html: "<p>الأول</p>", options: null, correct_answer: null, points: 1, sort_order: 0 };
    const second: AuthoringQuestion = { id: 2, type: "mcq", prompt_html: "<p>الثاني</p>", options: ["أ", "ب"], correct_answer: null, points: 2, sort_order: 1 };
    const added = [...[first], second];
    expect(replaceAuthoringQuestion(added, 0, { ...first, prompt_html: "<p>الأول المعدل</p>" })[0].prompt_html).toContain("المعدل");
    expect(removeAuthoringQuestion(added, 0)).toEqual([second]);
    expect(moveAuthoringQuestion(added, 0, 1).map(question => question.id)).toEqual([2, 1]);
  });

  it("removes client ids and preserves explicit authoring order", () => {
    const questions: AuthoringQuestion[] = [
      { id: 19, type: "essay", prompt_html: "<p>الثاني</p>", options: null, correct_answer: null, points: 2, sort_order: 9 },
      { type: "geometry", prompt_html: "<p>الأول</p>", options: { shape: "rectangle", dimensions: { width: "6" } }, correct_answer: null, points: 3, sort_order: 3 },
    ];
    expect(serializeAuthoringQuestions(questions)).toEqual([
      { type: "essay", prompt_html: "<p>الثاني</p>", options: null, correct_answer: null, points: 2, sort_order: 0 },
      { type: "geometry", prompt_html: "<p>الأول</p>", options: { shape: "rectangle", dimensions: { width: "6" } }, correct_answer: null, points: 3, sort_order: 1 },
    ]);
  });
});
