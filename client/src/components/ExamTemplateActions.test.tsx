import React from "react";
import { renderToStaticMarkup } from "react-dom/server";
import { describe, expect, it } from "vitest";
import ExamTemplateActions from "./ExamTemplateActions";

describe("ExamTemplateActions", () => {
  it("renders publish controls for draft templates", () => {
    const html = renderToStaticMarkup(<ExamTemplateActions status="draft" onEdit={() => undefined} onToggleStatus={() => undefined} onDelete={() => undefined} />);
    expect(html).toContain("مسودة");
    expect(html).toContain("نشر");
    expect(html).toContain("تعديل");
    expect(html).toContain("حذف");
  });

  it("renders archive controls for published templates", () => {
    const html = renderToStaticMarkup(<ExamTemplateActions status="published" onEdit={() => undefined} onToggleStatus={() => undefined} onDelete={() => undefined} />);
    expect(html).toContain("منشور");
    expect(html).toContain("أرشفة");
  });
});
