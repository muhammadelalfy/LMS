export type ExamEventType = "focus_lost" | "visibility_hidden" | "visibility_visible" | "focus_restored" | string;

export function formatExamTime(totalSeconds: number): string {
  const safeSeconds = Math.max(0, Math.floor(totalSeconds));
  return `${Math.floor(safeSeconds / 60)}:${String(safeSeconds % 60).padStart(2, "0")}`;
}

export function warningForExamEvent(type: ExamEventType): string {
  return type === "focus_lost" || type === "visibility_hidden"
    ? "تم تسجيل مغادرة نافذة الامتحان. عد إلى الامتحان فوراً."
    : "";
}

export function shouldAutoSubmit(remainingSeconds: number, submitted: boolean): boolean {
  return remainingSeconds <= 0 && !submitted;
}
