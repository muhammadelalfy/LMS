import { useEffect, useState } from "react";
import { BookOpen, ClipboardList, Target } from "lucide-react";
import { toast } from "sonner";
import { ApiError, laravelApi, type ExamDepartment, type ExamQuestion, type ExamTemplate } from "@/lib/laravelApi";
import ExamPaperPreview from "@/components/ExamPaperPreview";
import ExamQuestionComposer, { type AuthoringQuestion } from "@/components/ExamQuestionComposer";
import ExamTemplateActions from "@/components/ExamTemplateActions";

type Props = { onRefresh: () => Promise<void> };

export function buildExamTemplatePayload(input: { editingId: number | null; departmentId: string; title: string; grade: string; duration: string; instructions: string; watermark: string; questions: AuthoringQuestion[] }) {
  return {
    department_id: input.departmentId ? Number(input.departmentId) : null,
    title: input.title.trim(),
    grade: input.grade.trim(),
    duration_minutes: Number(input.duration),
    instructions: input.instructions,
    watermark_text: input.watermark,
    watermark_opacity: 12,
    status: "draft" as const,
    questions: input.editingId ? input.questions : input.questions.map(({ id: _id, ...question }) => question),
  };
}

const emptyQuestion: AuthoringQuestion = {
  type: "mcq",
  prompt_html: "<p>اكتب السؤال هنا...</p>",
  options: ["الإجابة الأولى", "الإجابة الثانية"],
  correct_answer: null,
  points: 1,
  sort_order: 0,
};

export default function ExamManagementPanel({ onRefresh }: Props) {
  const [templates, setTemplates] = useState<ExamTemplate[]>([]);
  const [departments, setDepartments] = useState<ExamDepartment[]>([]);
  const [previewTemplate, setPreviewTemplate] = useState<ExamTemplate | null>(null);
  const [title, setTitle] = useState("");
  const [departmentId, setDepartmentId] = useState("");
  const [grade, setGrade] = useState("");
  const [duration, setDuration] = useState("60");
  const [watermark, setWatermark] = useState("الامتياز في الرياضيات");
  const [instructions, setInstructions] = useState("");
  const [questions, setQuestions] = useState<AuthoringQuestion[]>([]);
  const [initialQuestions, setInitialQuestions] = useState<ExamQuestion[]>([]);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [saving, setSaving] = useState(false);
  const [message, setMessage] = useState("");
  const [deptName, setDeptName] = useState("");
  const [deptSlug, setDeptSlug] = useState("");
  const [editingDeptId, setEditingDeptId] = useState<number | null>(null);

  const load = async () => {
    try {
      const [nextTemplates, nextDepartments] = await Promise.all([laravelApi.examTemplates(), laravelApi.examDepartments()]);
      setTemplates(nextTemplates || []);
      setDepartments(nextDepartments || []);
    } catch (caught) {
      toast(caught instanceof ApiError ? caught.message : "تعذر تحميل قوالب الامتحانات");
    }
  };

  useEffect(() => { void load(); }, []);

  const resetAuthoring = () => {
    setEditingId(null);
    setTitle("");
    setDepartmentId("");
    setGrade("");
    setDuration("60");
    setWatermark("الامتياز في الرياضيات");
    setInstructions("");
    setQuestions([]);
    setInitialQuestions([]);
    setMessage("");
  };

  const save = async (event: React.FormEvent) => {
    event.preventDefault();
    if (!title.trim()) return setMessage("أدخل عنوان الامتحان أولاً.");
    if (!questions.length) return setMessage("أضف سؤالاً واحداً على الأقل قبل الحفظ.");
    setSaving(true);
    setMessage("");
    const payload = buildExamTemplatePayload({ editingId, departmentId, title, grade, duration, instructions, watermark, questions });
    try {
      if (editingId) {
        await laravelApi.updateExamTemplate(editingId, payload);
        setMessage("تم تحديث الامتحان والأسئلة وترتيبها.");
      } else {
        await laravelApi.createExamTemplate(payload);
        setMessage("تم حفظ قالب الامتحان كمسودة.");
        resetAuthoring();
      }
      await load();
      await onRefresh();
    } catch (caught) {
      setMessage(caught instanceof ApiError ? caught.message : "تعذر حفظ القالب");
    } finally {
      setSaving(false);
    }
  };

  const editTemplate = (template: ExamTemplate) => {
    setEditingId(template.id);
    setTitle(template.title);
    setDepartmentId(template.department_id ? String(template.department_id) : "");
    setGrade(template.grade || "");
    setDuration(String(template.duration_minutes));
    setWatermark(template.watermark_text || "");
    setInstructions(template.instructions || "");
    setInitialQuestions(template.questions || []);
    setQuestions((template.questions || []).map((question, index) => ({ ...question, sort_order: index })));
    setMessage("");
    window.scrollTo({ top: 0, behavior: "smooth" });
  };

  const setTemplateStatus = async (template: ExamTemplate, status: "published" | "archived") => {
    try {
      await laravelApi.updateExamTemplate(template.id, { status });
      await load();
      toast(status === "published" ? "تم نشر الامتحان للطلاب" : "تم أرشفة الامتحان");
    } catch (caught) {
      toast(caught instanceof ApiError ? caught.message : "تعذر تحديث حالة القالب");
    }
  };

  const removeTemplate = async (id: number) => {
    if (!window.confirm("هل تريد حذف هذا القالب؟")) return;
    try {
      await laravelApi.deleteExamTemplate(id);
      await load();
      toast("تم حذف القالب");
    } catch (caught) {
      toast(caught instanceof ApiError ? caught.message : "تعذر حذف القالب");
    }
  };

  const saveDepartment = async (event: React.FormEvent) => {
    event.preventDefault();
    if (!deptName.trim() || !deptSlug.trim()) return;
    try {
      if (editingDeptId) await laravelApi.updateExamDepartment(editingDeptId, { name: deptName.trim(), slug: deptSlug.trim() });
      else await laravelApi.createExamDepartment({ name: deptName.trim(), slug: deptSlug.trim() });
      setDeptName(""); setDeptSlug(""); setEditingDeptId(null);
      await load();
      toast("تم حفظ القسم");
    } catch (caught) {
      toast(caught instanceof ApiError ? caught.message : "تعذر حفظ القسم");
    }
  };

  const removeDepartment = async (id: number) => {
    if (!window.confirm("هل تريد حذف هذا القسم؟ لا يمكن حذف الأقسام المرتبطة بقوالب.")) return;
    try {
      await laravelApi.deleteExamDepartment(id);
      await load();
      toast("تم حذف القسم");
    } catch (caught) {
      toast(caught instanceof ApiError ? caught.message : "تعذر حذف القسم");
    }
  };

  return (
    <section className="live-page exam-management">
      <div className="page-head"><div><span className="eyebrow">إدارة الامتحانات</span><h2>قوالب امتحانات جاهزة للطلاب</h2><p className="muted">أنشئ الامتحان خطوة بخطوة، ثم انشره للطلاب مع علامة مائية وإعدادات مراقبة واضحة.</p></div><span className="live-count">{templates.length} قالب</span></div>
      <div className="card exam-departments-card"><div className="card-head"><div><span className="eyebrow">التنظيم</span><h3>أقسام الامتحانات</h3></div><Target size={20} /></div><form className="department-form" onSubmit={saveDepartment}><input value={deptName} onChange={event => setDeptName(event.target.value)} placeholder="اسم القسم" aria-label="اسم القسم" /><input value={deptSlug} onChange={event => setDeptSlug(event.target.value)} placeholder="slug" aria-label="معرف القسم" /><button className="primary">{editingDeptId ? "تحديث القسم" : "إضافة قسم"}</button></form><div className="department-list">{departments.map(department => <div className="department-row" key={department.id}><div><b>{department.name}</b><small>{department.slug}</small></div><span><button type="button" className="text-button" onClick={() => { setEditingDeptId(department.id); setDeptName(department.name); setDeptSlug(department.slug); }}>تعديل</button><button type="button" className="text-button danger-text" onClick={() => void removeDepartment(department.id)}>حذف</button></span></div>)}</div></div>
      <div className="exam-management-grid">
        <form className="card exam-authoring-card" onSubmit={save}>
          <div className="card-head"><div><span className="eyebrow">{editingId ? "تعديل مسودة" : "قالب جديد"}</span><h3>{editingId ? "تعديل الامتحان" : "إنشاء امتحان"}</h3></div><ClipboardList size={20} /></div>
          {editingId && <button type="button" className="text-button" onClick={resetAuthoring}>بدء قالب جديد</button>}
          <div className="exam-form-grid"><label>عنوان الامتحان<input required value={title} onChange={event => setTitle(event.target.value)} placeholder="اختبار الوحدة الأولى" /></label><label>القسم<select value={departmentId} onChange={event => setDepartmentId(event.target.value)}><option value="">بدون قسم</option>{departments.map(item => <option key={item.id} value={item.id}>{item.name}</option>)}</select></label><label>الصف<input value={grade} onChange={event => setGrade(event.target.value)} placeholder="الأول الإعدادي" /></label><label>المدة بالدقائق<input type="number" min="1" max="600" value={duration} onChange={event => setDuration(event.target.value)} /></label><label>العلامة المائية<input value={watermark} onChange={event => setWatermark(event.target.value)} /></label></div>
          <label>التعليمات<textarea value={instructions} onChange={event => setInstructions(event.target.value)} placeholder="تعليمات الطالب قبل البدء" /></label>
          <ExamQuestionComposer initialQuestions={initialQuestions} onChange={setQuestions} />
          {message && <p className="qr-result">{message}</p>}
          <button className="primary" disabled={saving}>{saving ? "جارٍ الحفظ..." : editingId ? "حفظ بيانات الامتحان" : "حفظ الامتحان كمسودة"}</button>
        </form>
        <div className="card exam-template-list"><div className="card-head"><div><span className="eyebrow">المكتبة</span><h3>القوالب الجاهزة</h3></div><BookOpen size={20} /></div>{templates.length ? templates.map(template => <div className="exam-template-row" key={template.id}><div><b>{template.title}</b><small>{template.grade || "كل الصفوف"} · {template.duration_minutes} دقيقة · {template.questions?.length || 0} سؤال</small></div><ExamTemplateActions status={template.status} onPreview={() => setPreviewTemplate(template)} onEdit={() => editTemplate(template)} onToggleStatus={() => void setTemplateStatus(template, template.status === "published" ? "archived" : "published")} onDelete={() => void removeTemplate(template.id)} /></div>) : <p className="muted">لم يتم إنشاء قوالب بعد.</p>}</div>
      </div>
      {previewTemplate && <ExamPaperPreview template={previewTemplate} onClose={() => setPreviewTemplate(null)} onExportPdf={() => laravelApi.downloadExamPdf(previewTemplate.id)} />}
    </section>
  );
}
