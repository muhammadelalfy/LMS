import { describe, expect, it } from "vitest";
import { formatExamTime, shouldAutoSubmit, warningForExamEvent } from "./examSessionUi";

describe("exam session UI helpers", () => {
  it("formats remaining exam time consistently", () => {
    expect(formatExamTime(125)).toBe("2:05");
    expect(formatExamTime(-1)).toBe("0:00");
  });

  it("warns only for focus or visibility loss", () => {
    expect(warningForExamEvent("focus_lost")).toContain("مغادرة");
    expect(warningForExamEvent("visibility_hidden")).toContain("فوراً");
    expect(warningForExamEvent("focus_restored")).toBe("");
  });

  it("auto-submits exactly when time expires and the runner is still active", () => {
    expect(shouldAutoSubmit(0, false)).toBe(true);
    expect(shouldAutoSubmit(-2, false)).toBe(true);
    expect(shouldAutoSubmit(0, true)).toBe(false);
    expect(shouldAutoSubmit(10, false)).toBe(false);
  });
});
