import React from "react";

type ExamWarningBannerProps = { message: string };

export default function ExamWarningBanner({ message }: ExamWarningBannerProps) {
  if (!message) return null;
  return <div className="exam-warning" role="alert" aria-live="assertive">{message}</div>;
}
