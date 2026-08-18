import React from "react";

type ExamTemplateActionsProps = {
  status: "draft" | "published" | "archived" | string;
  onEdit: () => void;
  onToggleStatus: () => void;
  onDelete: () => void;
};

export default function ExamTemplateActions({ status, onEdit, onToggleStatus, onDelete }: ExamTemplateActionsProps) {
  return <div className="exam-template-actions">
    <span className={`template-status template-status--${status}`}>{status === "published" ? "منشور" : status === "archived" ? "مؤرشف" : "مسودة"}</span>
    <button type="button" className="text-button" onClick={onEdit}>تعديل</button>
    <button type="button" className="text-button" onClick={onToggleStatus}>{status === "published" ? "أرشفة" : "نشر"}</button>
    <button type="button" className="text-button danger-text" onClick={onDelete}>حذف</button>
  </div>;
}
