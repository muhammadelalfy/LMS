import React from "react";
import { CheckCircle2, Download, X } from "lucide-react";
import type { ExamTemplate } from "@/lib/laravelApi";

type ExamPaperProps = {
  template: ExamTemplate;
  mode?: "preview" | "print";
};

type ExamPaperPreviewProps = ExamPaperProps & {
  onClose: () => void;
  onExportPdf?: () => Promise<void>;
};

export function ExamPaper({ template, mode = "preview" }: ExamPaperProps) {
  return (
    <article className={`exam-paper exam-paper--${mode}`} dir="rtl">
      <div className="exam-paper-watermark" style={{ opacity: template.watermark_opacity / 100 }} aria-hidden="true">
        {template.watermark_text || "الامتياز في الرياضيات"}
      </div>
      <div className="exam-paper-watermark exam-paper-watermark--secondary" style={{ opacity: template.watermark_opacity / 100 }} aria-hidden="true">
        {template.watermark_text || "الامتياز في الرياضيات"}
      </div>
      <header className="exam-paper-header">
        <div>
          <span className="eyebrow"><CheckCircle2 size={14} aria-hidden="true" /> الامتياز في الرياضيات</span>
          <h2>{template.title}</h2>
          <p>{template.department?.name || "اختبار رياضيات"}</p>
        </div>
        <div className="exam-paper-meta">
          <span><CheckCircle2 size={13} aria-hidden="true" /> الصف: {template.grade || "كل الصفوف"}</span>
          <span><CheckCircle2 size={13} aria-hidden="true" /> المدة: {template.duration_minutes} دقيقة</span>
          <span><CheckCircle2 size={13} aria-hidden="true" /> عدد الأسئلة: {template.questions.length}</span>
        </div>
      </header>
      {template.instructions && (
        <section className="exam-paper-instructions">
          <strong>تعليمات الامتحان</strong>
          <p>{template.instructions}</p>
        </section>
      )}
      <div className="exam-paper-questions">
        {template.questions.map((question, index) => (
          <section className="exam-paper-question" key={question.id || `${question.sort_order}-${index}`}>
            <div className="exam-paper-question-watermark" aria-hidden="true">{template.watermark_text || "الامتياز في الرياضيات"}</div>
            <div className="exam-paper-question-head">
              <strong><CheckCircle2 size={17} aria-hidden="true" /> السؤال {index + 1}</strong>
              <span>{question.points} درجة</span>
            </div>
            <div className="exam-question-prompt" dangerouslySetInnerHTML={{ __html: question.prompt_html }} />
            {question.type === "mcq" && (
              <ol className="exam-paper-options">
                {(question.options || []).map((option) => <li key={option}><span className="exam-option-checkbox" aria-hidden="true" />{option}</li>)}
              </ol>
            )}
            {question.type !== "mcq" && <div className="exam-paper-answer-lines" aria-label="مساحة الإجابة" />}
          </section>
        ))}
      </div>
    </article>
  );
}

export default function ExamPaperPreview({ template, onClose, onExportPdf }: ExamPaperPreviewProps) {
  const exportPdf = async () => {
    if (onExportPdf) {
      await onExportPdf();
      return;
    }
    document.body.classList.add("printing-exam-paper");
    const cleanup = () => document.body.classList.remove("printing-exam-paper");
    window.addEventListener("afterprint", cleanup, { once: true });
    window.setTimeout(() => window.print(), 0);
  };

  const close = () => {
    document.body.classList.remove("printing-exam-paper");
    onClose();
  };

  return (
    <div className="exam-preview-overlay" role="dialog" aria-modal="true" aria-label={`معاينة ${template.title}`}>
      <div className="exam-preview-shell">
        <div className="exam-preview-toolbar">
          <div>
            <span className="eyebrow">معاينة الامتحان</span>
            <strong>{template.title}</strong>
          </div>
          <div className="exam-preview-actions">
            <button type="button" className="primary" onClick={() => void exportPdf()}><Download size={15} aria-hidden="true" /> تحميل PDF</button>
            <button type="button" className="text-button" onClick={close}><X size={15} aria-hidden="true" /> إغلاق</button>
          </div>
        </div>
        <div className="exam-preview-scroll"><ExamPaper template={template} /></div>
      </div>
    </div>
  );
}
