import React, { useRef, useState } from "react";
import html2canvas from "html2canvas";
import jsPDF from "jspdf";
import { CheckCircle2, Download, X } from "lucide-react";
import type { ExamQuestion, ExamTemplate, MathQuestionOptions } from "@/lib/laravelApi";
import { GeometryDiagram, isGeometryDiagram } from "./GeometryDiagram";

type ExamPaperProps = {
  template: ExamTemplate;
  mode?: "preview" | "print";
  paperRef?: React.RefObject<HTMLElement | null>;
};

type ExamPaperPreviewProps = ExamPaperProps & {
  onClose: () => void;
  onExportPdf?: () => Promise<void>;
};

export function getMathNotation(question: ExamQuestion): string | null {
  if (question.type !== "math" || !question.options || Array.isArray(question.options) || isGeometryDiagram(question.options)) return null;
  return (question.options as MathQuestionOptions).notation || (question.options as MathQuestionOptions).latex || null;
}

type BrowserPdfDependencies = {
  capture: (element: HTMLElement, options: Record<string, unknown>) => Promise<{ width: number; height: number; toDataURL: (type: string) => string }>;
  Pdf: new (options: Record<string, unknown>) => {
    internal: { pageSize: { getWidth: () => number; getHeight: () => number } };
    addPage: () => void;
    addImage: (image: string, format: string, x: number, y: number, width: number, height: number, alias?: string, compression?: "NONE" | "FAST" | "MEDIUM" | "SLOW", rotation?: number) => void;
    save: (filename: string) => void;
  };
};

export async function exportExamPaperFromBrowser(
  element: HTMLElement,
  templateId: number,
  dependencies: BrowserPdfDependencies = { capture: html2canvas, Pdf: jsPDF },
) {
  element.classList.add("exam-paper-capture");
  if (typeof document !== "undefined" && "fonts" in document) await document.fonts.ready;
  try {
    const canvas = await dependencies.capture(element, {
    backgroundColor: "#fffdf8",
    scale: Math.min((typeof window === "undefined" ? 1 : window.devicePixelRatio) * 1.5, 3),
    useCORS: true,
    logging: false,
    windowWidth: element.scrollWidth,
    });
    const image = canvas.toDataURL("image/png");
    const pdf = new dependencies.Pdf({ unit: "pt", format: "a4", orientation: "portrait", compress: true });
  const pageWidth = pdf.internal.pageSize.getWidth();
  const pageHeight = pdf.internal.pageSize.getHeight();
  const imageHeight = canvas.height * (pageWidth / canvas.width);
  const pages = Math.max(1, Math.ceil(imageHeight / pageHeight));
  for (let page = 0; page < pages; page += 1) {
    if (page > 0) pdf.addPage();
    pdf.addImage(image, "PNG", 0, -(page * pageHeight), pageWidth, imageHeight, undefined, "FAST");
  }
    pdf.save(`exam-${templateId}.pdf`);
  } finally {
    element.classList.remove("exam-paper-capture");
  }
}

export async function exportExamPaperWithFallback(
  element: HTMLElement,
  templateId: number,
  exportBrowser: (element: HTMLElement, templateId: number) => Promise<void>,
  fallback: () => Promise<void>,
) {
  try {
    await exportBrowser(element, templateId);
  } catch {
    await fallback();
  }
}

export function ExamPaper({ template, mode = "preview", paperRef }: ExamPaperProps) {
  return (
    <article ref={paperRef} className={`exam-paper exam-paper--${mode}`} dir="rtl">
      <div className="exam-paper-watermark" style={{ opacity: template.watermark_opacity / 100 }} aria-hidden="true">
        {template.watermark_text || "الامتياز في الرياضيات"}
      </div>
      <div className="exam-paper-watermark exam-paper-watermark--secondary" style={{ opacity: template.watermark_opacity / 100 }} aria-hidden="true">
        {template.watermark_text || "الامتياز في الرياضيات"}
      </div>
      <header className="exam-paper-header">
        <div>
          <span className="eyebrow exam-paper-brand" dir="rtl"><CheckCircle2 size={14} aria-hidden="true" /><bdi>الامتياز في الرياضيات</bdi></span>
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
            {question.type === "geometry" && isGeometryDiagram(question.options) && <GeometryDiagram spec={question.options} />}
            {getMathNotation(question) && <div className="exam-paper-math-notation" dir="ltr" aria-label="الترميز الرياضي">{getMathNotation(question)}</div>}
            {question.type === "mcq" && Array.isArray(question.options) && (
              <ol className="exam-paper-options">
                {question.options.map((option) => <li key={option}><span className="exam-option-checkbox" aria-hidden="true" /><span className="exam-option-text" dir="rtl">{option}</span></li>)}
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
  const paperRef = useRef<HTMLElement | null>(null);
  const [exporting, setExporting] = useState(false);
  const exportPdf = async () => {
    if (exporting) return;
    setExporting(true);
    try {
      if (!paperRef.current) throw new Error("paper-not-mounted");
      await exportExamPaperWithFallback(
        paperRef.current,
        template.id,
        exportExamPaperFromBrowser,
        async () => {
          if (onExportPdf) return onExportPdf();
          document.body.classList.add("printing-exam-paper");
          const cleanup = () => document.body.classList.remove("printing-exam-paper");
          window.addEventListener("afterprint", cleanup, { once: true });
          window.setTimeout(() => window.print(), 0);
        },
      );
    } finally {
      setExporting(false);
    }
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
            <button type="button" className="primary" onClick={() => void exportPdf()} disabled={exporting}><Download size={15} aria-hidden="true" /> {exporting ? "جارٍ تجهيز PDF..." : "تحميل PDF من المتصفح"}</button>
            <button type="button" className="text-button" onClick={close}><X size={15} aria-hidden="true" /> إغلاق</button>
          </div>
        </div>
        <div className="exam-preview-scroll"><ExamPaper template={template} paperRef={paperRef} /></div>
      </div>
    </div>
  );
}
