# عناصر التنفيذ الجديدة

- [x] إضافة شاشة منشئ نماذج الامتحانات داخل صفحة الامتحانات.
- [x] دعم أنواع أسئلة متعددة: اختيار من متعدد، صح أو خطأ، سؤال مقالي، ومسألة رياضية.
- [x] إضافة ترتيب الأسئلة وتحديد الدرجة والزمن والتعليمات.
- [x] إضافة زر حفظ النموذج ومعاينة النموذج.
- [x] إضافة البحث بالاسم في قائمة الطلاب.
- [x] إضافة التصفية حسب حالة الطالب وحالة الدفع والمجموعة.
- [x] إضافة أزرار تصدير قائمة الطلاب والتقرير الفردي إلى Excel وPDF.
- [x] التحقق من الواجهة على سطح المكتب والموبايل.
- [ ] حفظ checkpoint بعد اكتمال التحسينات.

- [ ] Upgrade static project to full-stack with secure role-based authentication and persisted LMS data.
- [ ] Refactor shared LMS domain types, storage access, and repeated UI patterns using KISS/DRY principles.
- [ ] Connect worksheets to student assignments and submission status workflows.
- [ ] Add persisted attendance, exam results, and payment records with data-backed reports.
- [ ] Verify authorization boundaries, migrations, responsive flows, typecheck, build, and production behavior.

- [x] Pivot backend architecture to latest Laravel with Eloquent and MySQL.
- [x] Add Laravel authentication and role-aware account workflows.
- [x] Implement Eloquent models and migrations for students, worksheets, assignments, submissions, attendance, exams, payments, and reporting.
- [ ] Preserve the Arabic LMS frontend contract while switching data access to Laravel endpoints.
- [x] Verify Laravel tests, local migrations, authorization, and frontend build health; production MySQL deployment remains pending.

- [x] Add a typed React client for Laravel authentication and LMS API requests.
- [x] Replace the demo login and local-only dashboard state with live Laravel authentication/data states.
- [x] Build admin CRUD screens for attendance, exam results, and payments.
- [x] Build restricted student and parent dashboards with role-specific navigation and data.
- [x] Verify API errors, loading/empty states, role boundaries, responsive screens, and builds.
