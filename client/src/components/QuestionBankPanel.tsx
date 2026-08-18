import { useEffect, useMemo, useState } from "react";
import { BookOpen, Check, Pencil, Plus, Search, Trash2, X } from "lucide-react";
import { toast } from "sonner";
import { ApiError, laravelApi, type ExamDepartment, type ExamQuestion, type MathQuestionOptions, type QuestionBankQuestion } from "@/lib/laravelApi";
import { GeometryDiagram, isGeometryDiagram, type GeometryDiagramSpec } from "@/components/GeometryDiagram";
import { RichEditor, parseGeometryDimensions, type AuthoringQuestionType } from "@/components/ExamQuestionComposer";

type Props = { departments: ExamDepartment[]; onSelect: (question: Omit<ExamQuestion, "id">) => void };

type BankDraft = Omit<QuestionBankQuestion, "id" | "department" | "created_by">;

const blankDraft = (): BankDraft => ({ type: "mcq", title: "", grade: "", prompt_html: "<p>اكتب السؤال هنا...</p>", options: ["الإجابة الأولى", "الإجابة الثانية"], correct_answer: null, points: 1, sort_order: 0, tags: "", is_active: true, department_id: null });
const labels: Record<AuthoringQuestionType, string> = { mcq: "اختيار من متعدد", true_false: "صح أو خطأ", essay: "سؤال مقالي", math: "مسألة رياضية", geometry: "شكل هندسي" };

export function questionBankSnapshot(item: QuestionBankQuestion): Omit<ExamQuestion, "id"> {
  return { type: item.type, prompt_html: item.prompt_html, options: item.options, correct_answer: item.correct_answer ?? null, points: item.points, sort_order: 0 };
}

export default function QuestionBankPanel({ departments, onSelect }: Props) {
  const [items, setItems] = useState<QuestionBankQuestion[]>([]);
  const [search, setSearch] = useState("");
  const [type, setType] = useState<AuthoringQuestionType | "">("");
  const [draft, setDraft] = useState<BankDraft | null>(null);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [saving, setSaving] = useState(false);
  const [loading, setLoading] = useState(false);

  const load = async () => {
    setLoading(true);
    try { setItems(await laravelApi.questionBank({ search, type: type || undefined })); }
    catch (caught) { toast(caught instanceof ApiError ? caught.message : "تعذر تحميل بنك الأسئلة"); }
    finally { setLoading(false); }
  };
  useEffect(() => { void load(); }, [search, type]);
  const geometry = useMemo(() => draft?.type === "geometry" && isGeometryDiagram(draft.options) ? draft.options : { shape: "rectangle" as GeometryDiagramSpec["shape"], dimensions: { width: "6", height: "4" } }, [draft]);
  const math = draft?.type === "math" && draft.options && !Array.isArray(draft.options) && !isGeometryDiagram(draft.options) ? draft.options as MathQuestionOptions : { notation: "" };
  const reset = () => { setDraft(null); setEditingId(null); };
  const startNew = () => { setEditingId(null); setDraft(blankDraft()); };
  const edit = (item: QuestionBankQuestion) => { setEditingId(item.id); setDraft({ ...item }); };
  const select = (item: QuestionBankQuestion) => onSelect(questionBankSnapshot(item));
  const save = async (event: React.FormEvent) => {
    event.preventDefault(); if (!draft) return; setSaving(true);
    try { if (editingId) await laravelApi.updateQuestionBankQuestion(editingId, draft); else await laravelApi.createQuestionBankQuestion(draft); reset(); await load(); toast("تم حفظ السؤال في البنك"); }
    catch (caught) { toast(caught instanceof ApiError ? caught.message : "تعذر حفظ سؤال البنك"); }
    finally { setSaving(false); }
  };
  const remove = async (id: number) => { if (!window.confirm("هل تريد حذف هذا السؤال من البنك؟")) return; try { await laravelApi.deleteQuestionBankQuestion(id); await load(); toast("تم حذف السؤال"); } catch (caught) { toast(caught instanceof ApiError ? caught.message : "تعذر حذف السؤال"); } };
  const changeType = (nextType: AuthoringQuestionType) => setDraft(current => current ? { ...current, type: nextType, options: nextType === "mcq" ? ["الإجابة الأولى", "الإجابة الثانية"] : nextType === "geometry" ? { shape: "rectangle", dimensions: { width: "6", height: "4" } } : nextType === "math" ? { notation: "" } : null } : current);

  return <section className="question-bank-panel card"><div className="card-head"><div><span className="eyebrow">المكتبة التعليمية</span><h3>بنك الأسئلة</h3><p className="muted">ابحث عن سؤال محفوظ وأضفه إلى الامتحان كنسخة مستقلة قابلة لإعادة الترتيب.</p></div><BookOpen size={20} /></div>
    <div className="question-bank-toolbar"><label className="question-bank-search"><Search size={16} /><input value={search} onChange={event => setSearch(event.target.value)} placeholder="بحث في العنوان أو نص السؤال أو الوسوم" /></label><select value={type} onChange={event => setType(event.target.value as AuthoringQuestionType | "")}><option value="">كل الأنواع</option>{Object.entries(labels).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select><button type="button" className="secondary-button" onClick={startNew}><Plus size={15} /> سؤال جديد</button></div>
    <div className="question-bank-list">{loading ? <p className="muted question-bank-empty">جارٍ تحميل بنك الأسئلة...</p> : items.length === 0 ? <p className="muted question-bank-empty">لا توجد أسئلة مطابقة. أنشئ سؤالاً جديداً أو غيّر كلمات البحث.</p> : items.map(item => <article className="question-bank-row" key={item.id}><div><strong>{item.title || "سؤال بدون عنوان"}</strong><small>{labels[item.type]} · {item.grade || "كل الصفوف"} · {item.points} درجة</small><p dangerouslySetInnerHTML={{ __html: item.prompt_html }} /></div><div className="question-bank-actions"><button type="button" className="primary" onClick={() => select(item)}><Check size={14} /> إضافة للامتحان</button><button type="button" className="text-button" onClick={() => edit(item)}><Pencil size={14} /> تعديل</button><button type="button" className="text-button danger-text" onClick={() => void remove(item.id)}><Trash2 size={14} /> حذف</button></div></article>)}</div>
    {draft && <form className="question-bank-editor card" onSubmit={save}><div className="card-head"><div><span className="eyebrow">{editingId ? "تعديل سؤال البنك" : "سؤال جديد في البنك"}</span><h3>بيانات السؤال</h3></div><button type="button" className="icon-button" onClick={reset} title="إلغاء"><X size={16} /></button></div><div className="exam-form-grid"><label>عنوان مختصر<input value={draft.title || ""} onChange={event => setDraft({ ...draft, title: event.target.value })} /></label><label>الصف<input value={draft.grade || ""} onChange={event => setDraft({ ...draft, grade: event.target.value })} /></label><label>القسم<select value={draft.department_id ? String(draft.department_id) : ""} onChange={event => setDraft({ ...draft, department_id: event.target.value ? Number(event.target.value) : null })}><option value="">بدون قسم</option>{departments.map(department => <option key={department.id} value={department.id}>{department.name}</option>)}</select></label><label>النوع<select value={draft.type} onChange={event => changeType(event.target.value as AuthoringQuestionType)}>{Object.entries(labels).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select></label><label>الدرجة<input type="number" min="1" max="100" value={draft.points} onChange={event => setDraft({ ...draft, points: Number(event.target.value) })} /></label></div><label>نص السؤال — محرر غني<RichEditor value={draft.prompt_html} onChange={value => setDraft({ ...draft, prompt_html: value })} /></label>{draft.type === "mcq" && <label>الخيارات، خيار في كل سطر<textarea value={Array.isArray(draft.options) ? draft.options.join("\n") : ""} onChange={event => setDraft({ ...draft, options: event.target.value.split("\n").filter(Boolean) })} /></label>}{draft.type === "math" && <label>الترميز الرياضي<textarea dir="ltr" value={math.notation || ""} onChange={event => setDraft({ ...draft, options: { ...math, notation: event.target.value } })} placeholder="س^2 + 2س + 1" /></label>}{draft.type === "geometry" && <div className="geometry-authoring-fields"><div><label>نوع الشكل<select value={geometry.shape} onChange={event => setDraft({ ...draft, options: { shape: event.target.value as GeometryDiagramSpec["shape"], dimensions: geometry.dimensions } })}><option value="rectangle">مستطيل</option><option value="triangle">مثلث</option><option value="circle">دائرة</option><option value="angle">زاوية</option></select></label><GeometryDiagram spec={geometry} /></div><label>الأبعاد، اسم=قيمة في كل سطر<textarea value={Object.entries(geometry.dimensions).map(([key, value]) => `${key}=${value}`).join("\n")} onChange={event => setDraft({ ...draft, options: { shape: geometry.shape, dimensions: parseGeometryDimensions(event.target.value) } })} /></label></div>}<label>الوسوم<input value={draft.tags || ""} onChange={event => setDraft({ ...draft, tags: event.target.value })} placeholder="جبر، هندسة" /></label><button className="primary" disabled={saving}>{saving ? "جارٍ الحفظ..." : "حفظ في بنك الأسئلة"}</button></form>}
  </section>;
}
