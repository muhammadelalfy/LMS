import { useEffect, useState } from "react";
import { Box, CheckCircle2, Download, PackageCheck, RefreshCw, ShoppingBag, Trash2 } from "lucide-react";
import { toast } from "sonner";
import { ApiError, laravelApi, type PluginProduct } from "@/lib/laravelApi";

type Props = { onRefresh?: () => Promise<void> };

export default function PluginStorePanel({ onRefresh }: Props) {
  const [plugins, setPlugins] = useState<PluginProduct[]>([]);
  const [busy, setBusy] = useState<number | null>(null);
  const [loading, setLoading] = useState(true);

  const load = async () => {
    setLoading(true);
    try { setPlugins(await laravelApi.plugins()); }
    catch (error) { toast(error instanceof ApiError ? error.message : "تعذر تحميل متجر الإضافات"); }
    finally { setLoading(false); }
  };

  useEffect(() => { void load(); }, []);

  const run = async (id: number, action: () => Promise<unknown>, success: string) => {
    setBusy(id);
    try { await action(); toast(success); await load(); await onRefresh?.(); }
    catch (error) { toast(error instanceof ApiError ? error.message : "تعذر تنفيذ العملية"); }
    finally { setBusy(null); }
  };

  return <section className="live-page plugin-store" dir="rtl">
    <div className="page-heading"><div><span className="eyebrow">إضافات الامتياز</span><h2>متجر الوحدات</h2><p>اشترِ وحدات جاهزة وثبّتها في النظام بعد التحقق من ملف ZIP وبيانات module.json.</p></div><button className="outline" onClick={() => void load()}><RefreshCw size={15}/> تحديث المتجر</button></div>
    {loading ? <div className="live-loading"><RefreshCw className="spin"/> جارٍ تحميل الإضافات...</div> : <div className="plugin-grid">{plugins.map(plugin => {
      const working = busy === plugin.id;
      return <article className="plugin-card card" key={plugin.id}>
        <div className="plugin-card-icon"><PackageCheck size={24}/></div>
        <div className="plugin-card-body"><div className="plugin-card-top"><span className="eyebrow">v{plugin.version}</span><span className={plugin.installed ? "plugin-status installed" : "plugin-status"}>{plugin.installed ? "مثبتة" : "متاحة"}</span></div><h3>{plugin.name}</h3><p>{plugin.description || "وحدة تعليمية جاهزة للإضافة إلى لوحة الامتياز."}</p><small><Box size={13}/> {plugin.module_name}</small></div>
        <div className="plugin-card-footer"><strong>{Number(plugin.price) === 0 ? "مجانية" : `${plugin.price} جنيه`}</strong><div className="plugin-actions">{plugin.installed ? <button className="outline danger-text" disabled={working} onClick={() => void run(plugin.id, () => laravelApi.uninstallPlugin(plugin.id), "تم إلغاء تثبيت الإضافة") }><Trash2 size={14}/> إلغاء التثبيت</button> : !plugin.purchased ? <button className="primary" disabled={working} onClick={() => void run(plugin.id, () => laravelApi.purchasePlugin(plugin.id), "تم تسجيل شراء الإضافة") }><ShoppingBag size={14}/> شراء</button> : <button className="primary" disabled={working} onClick={() => void run(plugin.id, () => laravelApi.installPlugin(plugin.id), "تم تثبيت الإضافة وتفعيلها") }>{working ? <RefreshCw className="spin" size={14}/> : <Download size={14}/>} تثبيت</button>}</div></div>
        {plugin.installed && <div className="plugin-installed-note"><CheckCircle2 size={14}/> الوحدة مفعّلة داخل مجلد Modules</div>}
      </article>;
    })}</div>}
    {!loading && plugins.length === 0 && <div className="empty-state">لا توجد إضافات متاحة حالياً.</div>}
  </section>;
}
