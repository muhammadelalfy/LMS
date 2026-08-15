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

- [x] Test Laravel authentication and role-based login from the React frontend.
- [x] Verify attendance CRUD interactions and authorization from the admin dashboard.
- [x] Verify exam-results CRUD interactions and authorization from the admin dashboard.
- [x] Validate restricted student and parent dashboard data and navigation.
- [x] Add regression tests for frontend API failures and role-specific access states.

- [x] Add a local-development-only Laravel admin seeder with temporary credentials.
- [x] Run and verify the local admin account seeding flow without exposing production secrets.

- [x] Diagnose the React «تعذر الاتصال بالخادم» authentication failure.
- [x] Fix the React-to-Laravel API base URL or proxy connection path.
- [x] Verify login, API error handling, frontend build, and Laravel tests after the fix.

- [x] Add unique QR identity and server-side scan validation for each student.
- [x] Record QR attendance with server UTC timestamp and duplicate prevention.
- [x] Add student QR display and admin scanner/scan-result UI.
- [x] Test authorization, invalid QR, repeated scan, timestamp, and responsive flows.

- [x] Add an explicit-permission live camera QR scanner to the admin attendance panel.
- [x] Connect decoded camera payloads to Laravel attendance scan with duplicate/result feedback.
- [x] Preserve manual QR input fallback and test camera permission, cleanup, and responsive states.
