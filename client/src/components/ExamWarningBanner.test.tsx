import React from "react";
import { renderToStaticMarkup } from "react-dom/server";
import { describe, expect, it } from "vitest";
import ExamWarningBanner from "./ExamWarningBanner";

describe("ExamWarningBanner", () => {
  it("renders no alert when there is no warning", () => {
    expect(renderToStaticMarkup(<ExamWarningBanner message="" />)).toBe("");
  });

  it("renders an assertive accessible alert when focus is lost", () => {
    const html = renderToStaticMarkup(<ExamWarningBanner message="تم تسجيل مغادرة نافذة الامتحان." />);
    expect(html).toContain('role="alert"');
    expect(html).toContain('aria-live="assertive"');
    expect(html).toContain("مغادرة نافذة الامتحان");
  });
});
