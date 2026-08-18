import React, { useEffect, useMemo, useState } from "react";
import { ArrowDown, ArrowUp, Check, Pencil, Plus, Trash2, X } from "lucide-react";
import { EditorContent, useEditor } from "@tiptap/react";
import StarterKit from "@tiptap/starter-kit";
import type { ExamQuestion } from "@/lib/laravelApi";
import type { GeometryDiagramSpec } from "./GeometryDiagram";

export type AuthoringQuestionType = "mcq" | "true_false" | "essay" | "math" | "geometry";
export type AuthoringQuestion = Omit<ExamQuestion, "id"> & { id?: number };

type Draft = AuthoringQuestion;

type Props = {
  initialQuestions?: ExamQuestion[];
  onChange: (questions: AuthoringQuestion[]) => void;
};

const blankQuestion = (): Draft => ({
  type: "mcq",
  prompt_html: "<p>اكتب السؤال هنا...</p>",
  options: ["الإجابة الأولى", "الإجابة الثانية", "الإجابة الثالثة"],
  correct_answer: null,
  points: 1,
  sort_order: 0,
});

const questionTypeLabels: Record<AuthoringQuestionType, string> = {
  mcq: "اختيار من متعدد",
  true_false: "صح أو خطأ",
  essay: "سؤال مقالي",
  math: "مسألة رياضية",
  geometry: "شكل هندسي بالأبعاد",
};

function RichEditor({ value, onChange }: { value: string; onChange: (value: string) => void }) {
  const editor = useEditor({
    extensions: [StarterKit],
    content: value,
    editorProps: { attributes: { class: "rich-editor", dir: "rtl" } },
    onUpdate: ({ editor: instance }) => onChange(instance.getHTML()),
  });
  useEffect(() => {
    if (editor && value !== editor.getHTML()) editor.commands.setContent(value, { emitUpdate: false });
  }, [editor, value]);
  if (!editor) return <div className="rich-editor">جارٍ تحميل المحرر...</div>;
  return (
    <div className="tiptap-editor">
      <div className="tiptap-toolbar" role="toolbar" aria-label="تنسيق نص السؤال">
        <button type="button" onClick={() => editor.chain().focus().toggleBold().run()} className={editor.isActive("bold") ? "active" : ""}>عريض</button>
        <button type="button" onClick={() => editor.chain().focus().toggleItalic().run()} className={editor.isActive("italic") ? "active" : ""}>مائل</button>
        <button type="button" onClick={() => editor.chain().focus().toggleBulletList().run()}>قائمة</button>
        <button type="button" onClick={() => editor.chain().focus().toggleCodeBlock().run()}>رموز</button>
      </div>
      <EditorContent editor={editor} />
    </div>
  );
}

function geometryFromQuestion(question?: ExamQuestion): { shape: GeometryDiagramSpec["shape"]; dimensions: string } {
  if (!question || question.type !== "geometry" || !question.options || Array.isArray(question.options)) return { shape: "rectangle", dimensions: "width=6\nheight=4" };
  const options = question.options as GeometryDiagramSpec;
  return { shape: options.shape, dimensions: Object.entries(options.dimensions).map(([key, value]) => `${key}=${value}`).join("\n") };
}

export function parseGeometryDimensions(value: string): Record<string, string> {
  return Object.fromEntries(value.split("\n").map(line => line.split("=").map(part => part.trim())).filter(pair => pair.length === 2 && pair[0] && pair[1]));
}

export function serializeAuthoringQuestions(questions: AuthoringQuestion[]) {
  return questions.map(({ id: _id, ...question }, index) => ({ ...question, sort_order: index }));
}

export function replaceAuthoringQuestion(questions: AuthoringQuestion[], index: number, question: AuthoringQuestion) {
  return questions.map((item, itemIndex) => itemIndex === index ? question : item);
}

export function removeAuthoringQuestion(questions: AuthoringQuestion[], index: number) {
  return questions.filter((_, itemIndex) => itemIndex !== index);
}

export function moveAuthoringQuestion(questions: AuthoringQuestion[], index: number, direction: -1 | 1) {
  const target = index + direction;
  if (target < 0 || target >= questions.length) return questions;
  const next = [...questions];
  [next[index], next[target]] = [next[target], next[index]];
  return next;
}

function normalizeQuestion(question: ExamQuestion, index: number): Draft {
  return { id: question.id, type: question.type as AuthoringQuestionType, prompt_html: question.prompt_html, options: question.options, correct_answer: question.correct_answer ?? null, points: question.points, sort_order: index };
}

export default function ExamQuestionComposer({ initialQuestions = [], onChange }: Props) {
  const [questions, setQuestions] = useState<Draft[]>(() => initialQuestions.map(normalizeQuestion));
  const [draft, setDraft] = useState<Draft | null>(null);
  const [editingIndex, setEditingIndex] = useState<number | null>(null);
  const [error, setError] = useState("");

  useEffect(() => {
    setQuestions(initialQuestions.map(normalizeQuestion));
    setDraft(null);
    setEditingIndex(null);
  }, [initialQuestions]);

  const commit = (next: Draft[]) => {
    const normalized = serializeAuthoringQuestions(next);
    setQuestions(normalized);
    onChange(normalized);
  };

  const beginAdd = () => { setError(""); setEditingIndex(null); setDraft(blankQuestion()); };
  const beginEdit = (index: number) => { setError(""); setEditingIndex(index); setDraft({ ...questions[index] }); };
  const cancel = () => { setDraft(null); setEditingIndex(null); setError(""); };
  const saveDraft = () => {
    if (!draft || !draft.prompt_html.replace(/<[^>]+>/g, "").trim()) return setError("اكتب نص السؤال أولاً.");
    if (draft.type === "mcq" && (!Array.isArray(draft.options) || draft.options.length < 2)) return setError("أضف خيارين على الأقل للسؤال.");
    const next = editingIndex === null ? [...questions, draft] : replaceAuthoringQuestion(questions, editingIndex, draft);
    commit(next);
    cancel();
  };
  const remove = (index: number) => commit(removeAuthoringQuestion(questions, index));
  const move = (index: number, direction: -1 | 1) => commit(moveAuthoringQuestion(questions, index, direction));
  const geometry = useMemo(() => geometryFromQuestion(draft?.type === "geometry" ? draft as ExamQuestion : undefined), [draft]);

  return (
    <div className="exam-question-composer">
      <div className="exam-question-composer-head">
        <div><span className="eyebrow">بناء الأسئلة</span><h3>سؤال واحد في كل مرة</h3><p className="muted">أضف السؤال واحفظه، ثم استخدم زر + لإضافة السؤال التالي.</p></div>
        <button type="button" className="secondary-button" onClick={beginAdd}><Plus size={16} /> إضافة سؤال</button>
      </div>
      {questions.length === 0 && !draft && <div className="exam-question-empty">لم تتم إضافة أسئلة بعد. ابدأ من زر «إضافة سؤال».</div>}
      <div className="exam-question-list">
        {questions.map((question, index) => (
          <article className="exam-question-summary" key={question.id ?? `draft-${index}`}>
            <div><span className="question-number">{index + 1}</span><div><strong>{questionTypeLabels[question.type as AuthoringQuestionType]}</strong><p dangerouslySetInnerHTML={{ __html: question.prompt_html }} /></div></div>
            <div className="exam-question-summary-actions"><button type="button" className="icon-button" title="نقل لأعلى" onClick={() => move(index, -1)} disabled={index === 0}><ArrowUp size={15} /></button><button type="button" className="icon-button" title="نقل لأسفل" onClick={() => move(index, 1)} disabled={index === questions.length - 1}><ArrowDown size={15} /></button><button type="button" className="text-button" onClick={() => beginEdit(index)}><Pencil size={14} /> تعديل</button><button type="button" className="text-button danger-text" onClick={() => remove(index)}><Trash2 size={14} /> حذف</button></div>
          </article>
        ))}
      </div>
      {draft && (
        <div className="exam-question-editor card">
          <div className="card-head"><div><span className="eyebrow">{editingIndex === null ? "سؤال جديد" : `تعديل السؤال ${editingIndex + 1}`}</span><h3>بيانات السؤال</h3></div><button type="button" className="icon-button" onClick={cancel} title="إلغاء"><X size={16} /></button></div>
          <div className="exam-question-editor-grid">
            <label>نوع السؤال<select value={draft.type} onChange={event => setDraft(current => current ? { ...current, type: event.target.value as AuthoringQuestionType, options: event.target.value === "mcq" ? ["الإجابة الأولى", "الإجابة الثانية"] : null } : current)}>{Object.entries(questionTypeLabels).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select></label>
            <label>الدرجة<input type="number" min="1" max="100" value={draft.points} onChange={event => setDraft(current => current ? { ...current, points: Number(event.target.value) } : current)} /></label>
          </div>
          <label>نص السؤال — محرر غني<RichEditor value={draft.prompt_html} onChange={value => setDraft(current => current ? { ...current, prompt_html: value } : current)} /></label>
          {draft.type === "mcq" && <label>الخيارات، خيار في كل سطر<textarea value={Array.isArray(draft.options) ? draft.options.join("\n") : ""} onChange={event => setDraft(current => current ? { ...current, options: event.target.value.split("\n").map(item => item.trim()).filter(Boolean) } : current)} placeholder="الخيار الأول\nالخيار الثاني" /></label>}
          {draft.type === "geometry" && <div className="geometry-authoring-fields"><label>نوع الشكل<select value={geometry.shape} onChange={event => setDraft(current => current ? { ...current, options: { shape: event.target.value as GeometryDiagramSpec["shape"], dimensions: parseGeometryDimensions(geometry.dimensions) } } : current)}><option value="rectangle">مستطيل</option><option value="triangle">مثلث</option><option value="circle">دائرة</option><option value="angle">زاوية</option></select></label><label>الأبعاد، اسم=قيمة في كل سطر<textarea value={geometry.dimensions} onChange={event => setDraft(current => current ? { ...current, options: { shape: geometry.shape, dimensions: parseGeometryDimensions(event.target.value) } } : current)} placeholder="width=6\nheight=4" /></label></div>}
          {error && <p className="live-error">{error}</p>}
          <div className="exam-question-editor-actions"><button type="button" className="primary" onClick={saveDraft}><Check size={15} /> حفظ السؤال</button><button type="button" className="text-button" onClick={cancel}>إلغاء</button></div>
        </div>
      )}
    </div>
  );
}
