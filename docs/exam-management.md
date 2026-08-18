# Exam Management

The LMS now has an additive exam-management layer that preserves the existing `/api/exams` result CRUD contract. New entities cover departments, reusable exam templates, rich-text questions, student sessions, autosaved answers, and auditable session events.

Administrators and teachers can create a template from the Arabic dashboard, select a department, set the grade and duration, add a question type, edit the prompt with Tiptap, define multiple-choice options, and configure a custom watermark. Templates remain drafts until a separate publishing action is added; the current authoring flow intentionally saves safely as a draft.

Students and parents can see published templates. Starting a session requests camera permission, records the camera event, requests fullscreen when supported, and begins an exam session. The runner autosaves answers and records visibility changes, focus loss/restoration, fullscreen events, heartbeat events, and final submission. A visible warning appears when the browser loses focus, and the session is flagged in the database.

A browser cannot reliably prevent a user from leaving a tab or switching applications. The implementation therefore uses the strongest available cooperative controls—camera permission, fullscreen request, visibility/focus detection, event auditing, and immediate warnings—without pretending that client-side JavaScript is a secure proctoring boundary. High-stakes assessments should pair this mode with human supervision and server-side review of flagged sessions.

Verification completed locally: 20 Laravel tests with 84 assertions, 8 frontend tests, TypeScript, production build, PHP syntax checks, route discovery, local SQLite migration, and an authenticated Arabic dashboard review of the authoring surface.

## Final verification notes

The admin library now exposes edit, delete, publish, and archive actions for templates, and the editor loads the first question back into the authoring form for revision. Department endpoints now support create, update, and soft-delete. Frontend API tests cover the full template/session request map, while Laravel feature tests cover staff template creation, student session creation, and focus-loss flagging.

The authoring surface was reviewed in Arabic RTL at desktop width with the Tiptap toolbar, watermark fields, question-type selector, and template action group visible. The mobile stylesheet collapses the authoring grid to one column, makes action controls wrap, and keeps the exam runner answer cards and warning banner readable. Form controls use native labels, visible focus rings from the shared design system, keyboard-usable buttons, and semantic alert markup for focus-loss warnings. The runner intentionally does not claim to prevent tab switching absolutely; it records and surfaces those events instead.

### Accessibility and responsive checklist

The authoring form and runner use semantic labels, native inputs/selects, buttons with explicit types, and `role="alert"` for focus-loss warnings. Shared focus-ring styles remain active for keyboard navigation, and the new action groups wrap rather than requiring horizontal scrolling. The mobile authoring grid and department list collapse to one column; runner answer cards and the submit action remain full width.

Contrast was reviewed against the existing ivory card surfaces, dark teal headings, emerald action color, and copper status accents. Warning text is presented in a dedicated high-contrast alert panel rather than only by color. The runner’s camera badge is accompanied by text. Reduced motion is respected by the existing global `prefers-reduced-motion` rules: the animated math background stops, while the exam controls and warning state remain fully functional without animation. Timer behavior is deterministic and is covered by `examSessionUi.test.ts`.

### Verification boundary

The authenticated admin authoring surface was verified in the live preview after the undefined-collection crash was fixed; departments, rich-text authoring, watermark fields, and template actions render in Arabic RTL. The student publish-to-session flow is covered by Laravel feature tests, including camera-required session creation, answer/event persistence, focus-loss auditing, and submission behavior. A browser-level student camera check requires a user-controlled student login because the preview browser retained the admin session; the application does not bypass that authentication step.

Department deletion is now explicit and safe: the UI offers hard delete, while the API refuses deletion when templates still reference the department and instructs administrators to preserve or reassign those templates first.

## Published exam viewer, preview, and PDF export

The student-facing exam library uses the existing authenticated `GET /api/exam-templates` contract. The API applies the role boundary at the controller: students and parents receive only templates whose status is `published`, while administrators and teachers receive the management collection. The shared `ExamPaper` renderer presents the published title, department, grade, duration, instructions, rich-text prompts, multiple-choice options, answer space, and configured watermark before a monitored session begins. Starting the exam still requires the existing `POST /api/exam-templates/{template}/start` flow and does not occur from preview.

Administrators see the same paper renderer from each template row through `معاينة / PDF`. This intentionally reuses the already authorized template payload rather than introducing a second read endpoint or duplicating serialization. The preview is read-only and does not expose correct answers. Draft and archived templates remain available only to staff through the management collection and are never shown in the student portal.

The preview’s primary PDF action is browser-side: `html2canvas` captures the mounted exam paper exactly as rendered, and `jsPDF` writes that image to an A4 PDF. This keeps Arabic typography, watermarks, cards, checkbox placement, and spacing visually identical to the preview. If browser capture is unavailable, the protected `GET /api/exam-templates/{template}/pdf` endpoint remains a fallback using TCPDF’s Unicode/RTL writer. Multiple-choice options use empty checkbox controls positioned on one horizontal line with clear spacing from the option text.

The preview is not a proctoring boundary and does not create an exam session. Camera permission, fullscreen requests, focus-loss monitoring, answer autosave, and submission remain exclusive to the monitored runner. This separation keeps preview safe for inspection and preserves the existing student-session authorization checks.

### Verification for this slice

The `ExamPaper` component has rendering regression tests covering Arabic metadata, watermark text, ordered questions, empty option checkboxes, essay answer space, browser image-PDF generation, and fallback behavior. Laravel feature tests cover the fallback PDF endpoint, PDF signature and headers, published-only student access, and monitored-session behavior. The authenticated browser preview confirms the checkbox and option text remain on one line with visible spacing. The frontend suite passes with 20 tests, TypeScript passes, and the production build completes. The preview overlay uses a responsive one-column layout below 700px, a scrollable paper surface, repeated question watermarks, and browser image-PDF export.

## Dimensioned geometry questions

Geometry questions use a small internal SVG contract rather than embedding a full interactive authoring board. This keeps the model KISS/DRY, produces deterministic browser-PDF captures, and supports accessible SVG output. The current contract supports `rectangle`, `triangle`, `circle`, and `angle` shapes with named dimension values such as `width`, `height`, `base`, `radius`, or `angle`. The authoring form stores one `name=value` dimension per line, and the same renderer is used in administrator preview, student exam mode, and browser image-PDF capture.

During library research, JSXGraph was identified as the strongest future option for interactive geometry authoring because it supports Euclidean/projective geometry, 2D/3D, SVG/canvas, MathJax/KaTeX, accessibility, and assessment workflows. GeoGebra remains useful for teacher-created complex constructions, but both would add more integration and storage complexity than this first deterministic SVG slice. A future interactive authoring module can adopt JSXGraph while retaining the stored geometry contract.
