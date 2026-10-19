# ADR 0002: Scope `authorizeStaff()`-gated endpoints to a teacher's own groups, not the whole school

- **Status:** Proposed
- **Date:** 2026-10-19
- **Owner:** Architecture (Mudarisy)
- **Related:** `ENGINEERING_CONSTITUTION.md` §6 (Modular Monolith and Module Ownership), §7 (Multi-Tenancy —
  the per-school scoping shape this reuses one level down), §8 (Layering/SOLID — Policies), §13.1/§13.2
  (students, cards, groups), §23 (Security — "a teacher sees their own groups/agenda; a student only their
  own data; a parent only their children"), §27.2 (pre-implementation analysis), §27.6 (ADRs for significant
  decisions)

## Context

A constitution-compliance audit found, twice, that `Modules/Core/Http/Concerns/AuthorizesStaff.php`:

```php
private function authorizeStaff(Request $request): void
{
    abort_unless($request->user()->isAnyRole('admin', 'teacher'), 403);
}
```

is the *only* authorization check in front of a large set of write/read endpoints across six modules. It
answers "is this user staff at all" and nothing else — it does not distinguish an admin from a teacher, nor
one teacher's groups/students from another's. Every one of its call sites was read to establish current
behaviour (not assumed):

| Controller::method | Module | Current behaviour under `authorizeStaff()` alone |
|---|---|---|
| `ClassGroupController::index/store/update/destroy` | Groups | Any teacher lists **every** group (every other teacher's roster size and `teacher_id`), creates a group naming **any** teacher as `teacher_id`, updates **any** group including reassigning `teacher_id` to someone else, deletes **any** group. |
| `DutyController::store/update/destroy` | Learning | Any teacher creates/edits/deletes homework for **any** group string, not only groups they teach. (`index` already filters by student's own group for students/parents; nothing filters it for teachers reading someone else's group's duties.) |
| `AgendaController::index/store/update/destroy` | Learning | Already correct: `visibleTo()` filters to `created_by = $user->id` unless admin. `authorizeStaff()` here is just the staff-role pre-check; no change needed. |
| `HandwritingController::__invoke` | Learning | OCR utility call, no persisted resource with an owner (reads text from an uploaded photo). |
| `ExamResultController::index/store/update/destroy` | Exams | Any teacher lists/creates/edits/deletes exam results for **any** student in the school, not only their own groups' students. |
| `ExamManagementController::departments*/templates*/downloadPdf` | Exams | Shared curriculum catalogue (exam departments, exam templates, question bank is a separate controller below) — no group or student is implicated; any staff member reads/writes the whole catalogue. Session endpoints (`startSession`/`event`/`answer`/`answers`/`submit`) are **not** gated by `authorizeStaff()` at all — they use `authorizeSessionOwner()` (the student's own `student_id`), already correctly scoped. |
| `QuestionBankController::index/store/show/update/destroy` | Exams | Same shape as the exam catalogue: shared question bank, no group/student link. |
| `AttendanceController::store/update/destroy` | Attendance | Any teacher records/edits/deletes an attendance record for **any** student. `index` already filters student/parent to their own record; teacher is unfiltered. |
| `AttendanceController::scan` | Attendance | Any staff member scans a QR/face card at the point of arrival — this is a front-desk/gate action by design, documented as such in the Payments equivalent below. |
| `AttendanceController::sweep/remindSessions` | Attendance | Batch/system triggers covering every group's sessions today; same job the scheduler runs. |
| `IntegrationController::index/connect/disconnect` | Calls | Already self-scoped: filters `ProviderAccount` by `user_id = $request->user()->id`. There is no other teacher's data reachable here; `authorizeStaff()` only gates "is this a staff account." |
| `OnlineSessionController::update/destroy` | Calls | **Already correctly scoped**, and is the reference pattern (see below): in addition to `authorizeStaff()`, both call `abort_unless($this->access->canAccessGroup($request->user(), $group), 403, …)`. |
| `PaymentController::index/store/update/destroy` | Payments | Any teacher lists/creates/edits/deletes **any** student's payment record, school-wide. |
| `QrCheckinController::lookup/payment` | Payments | Front-desk: staff scan a student's QR card and confirm/collect a payment. Explicitly documented in the controller's own docblock as a front-desk action, not a per-teacher one. |
| `SchoolMessageController::send` | Notifications | Any teacher can message "chosen students, a whole group, **or everyone**" (`all=true` sends to every student in the school), regardless of which groups they teach. `registerDevice`/`unregisterDevice` don't use `authorizeStaff()` and are already self-scoped by `user_id`. |
| `FaceTemplateController::index/update/destroy` | Students | Any teacher lists **every** student's biometric face template, or enrols/deletes **any** student's face template, not only their own groups' students. |

**The reference pattern already exists in production, in a different module.** `Modules/Chat/Services/ChatAccess::canAccessGroup()`:

```php
public function canAccessGroup(User $user, ?ClassGroup $group): bool
{
    if ($group === null) return false;
    if ($user->isAnyRole('admin')) return true;
    if ($user->isAnyRole('teacher')) return $group->teacher_id === null || $group->teacher_id === $user->id;
    return $user->isAnyRole('student') && $this->groupOf($user)?->id === $group->id;
}
```

`OnlineSessionController` already calls this in addition to `authorizeStaff()`, and it is the right shape: admin
sees everything, a teacher sees a group they are named on **or a group with no teacher named at all**, a
student sees only their own group. `ChatAccess::contacts()` and `ChatAccess::participants()` use the identical
`teacher_id === null OR teacher_id === $user->id` rule to resolve "this teacher's own + unclaimed groups" by
name, which is exactly the shape needed for `Student`-keyed resources (Payments, Exams, Attendance, Duty,
FaceTemplate), since `Student.group` is a plain string matched against `ClassGroup.name` — **there is no
foreign key from `Student` to `ClassGroup`** (`Modules/Students/Database/Migrations/..._create_students_table.php`
defines `group` as `string`, not `foreignId`). `class_groups.name` is unique
(`Modules/Groups/Http/Controllers/ClassGroupController.php` validation), so the string match is reliable at a
point in time, but it is a pre-existing fragility (a group rename silently detaches its students from every
past string match) that this ADR inherits and does not fix — flagged as a separate future concern, not solved
here.

**Complications found while reading the code (answering the task's specific questions):**

1. **Groups cannot currently distinguish "update my own group's schedule" from "reassign this group to
   someone else"** — confirmed by reading `ClassGroupController::update()`: it is one `$group->update($this->validated($request, $group))` call with no field-level distinction. `teacher_id` is validated the same way as `days`/`start_time`/`end_time`. Today, any teacher can rewrite any group's `teacher_id` to any other teacher, or take over any group, or hand their own group to someone else — this is explicitly a Groups-reassignment problem, not only a "stay in your group" problem, and the fix must separate "schedule fields" from "`teacher_id`" rather than gate the whole `update()` action as one unit.
2. **An unassigned group (`teacher_id === null`) is already, deliberately, open to *any* teacher** — this is not a bug to close, it is existing, intentional behaviour reused from `ChatAccess`/`OnlineSessionController` (a brand-new or temporarily unstaffed group should be pickable by whichever teacher is handling it, rather than locked to nobody). Any new policy must preserve this "null `teacher_id` is open" rule, not accidentally tighten it into "only admin can touch an unassigned group."
3. **Front-desk / gate workflows are a genuine, evidenced exception to "own groups only."** `QrCheckinController` (Payments) is explicitly documented as "staff scan the student's QR card" at a front desk — the point of the feature is that whoever is on duty at the desk, not necessarily that student's own teacher, confirms payment. `AttendanceController::scan` is the same shape for arrival check-in. Scoping either to "only the student's own teacher" breaks the feature outright; there is no evidence in the code or tests of an intended narrower actor (e.g. a "cashier" or "front-desk" role does not exist in the current role set: admin/teacher/student/parent). These two stay staff-wide.
4. **Shared curriculum catalogue has no natural "owner" to scope by.** Exam departments, exam templates and the question bank (`ExamManagementController`'s department/template endpoints, `QuestionBankController`) are not linked to a group or a student at all — they are subject-matter content any teacher may need to read or compose a paper from. There is no "department head" or "subject owner" actor in the current role/data model, and inventing one here would be designing a new business rule, not fixing an authorization gap — out of scope. These stay staff-wide; `created_by` is recorded for audit but not used to gate.
5. **`HandwritingController` and the `Integration`/device-registration endpoints never reach another teacher's or student's data at all** — the former is a stateless OCR call, the latter are already self-scoped by `user_id`. `authorizeStaff()` here is only a "staff-role" gate, already correct as-is.
6. **Two dead `Policy` classes already exist** (`Modules/Learning/Policies/WorksheetPolicy`, `Modules/Students/Policies/StudentPolicy`), registered via `Gate::policy()` in their module's `ServiceProvider`, but every method unconditionally `return false;` and neither is ever invoked via `$this->authorize()`/`Gate::` anywhere in the codebase (confirmed by `grep`). They are generated scaffolding, not a working precedent — but they do confirm the module layout convention (`Modules/<Name>/Policies/`, wired in `<Name>ServiceProvider`) is the one to follow, per §6's recommended layout.
7. **Module-boundary note, not part of this fix:** `class_groups.teacher_id` was added by a **Chat module** migration (`Modules/Chat/Database/Migrations/2026_10_11_100000_create_chat_tables.php`), not a Groups module migration — Chat physically altered another module's table. This is a pre-existing boundary smell (§6: "an unrelated module must not manipulate another module's internals"); it is flagged here because it is exactly the kind of drift this ADR's mechanism must not repeat (the new helper must live in Groups, not wherever first needed it), but fixing the historical migration placement is out of scope for this ADR.
8. **An existing, unrelated precedent for "destructive action escalated above `authorizeStaff()`'s admin-or-teacher" already exists:** `Modules/Students/Http/Controllers/StudentController::destroy()` is gated `abort_unless($request->user()->isAnyRole('admin'), 403)` — admin-only, stricter than `authorizeStaff()` — while `store`/`update` on the same controller stay admin-or-teacher. This confirms that "the same CRUD family does not have to share one gate" is already an accepted shape in this codebase, which this ADR follows for Payments' `destroy` and Groups' `destroy`/`teacher_id` reassignment below.

## Decision

**Replace `authorizeStaff()` as the terminal authorization check wherever the underlying resource belongs to a
specific group/student, with a real module `Policy` built on one shared "does this teacher own this group"
rule. Leave `authorizeStaff()` in place, unchanged, as the terminal check only for the endpoints established
above as genuinely staff-wide.**

### 1. One shared ownership rule, owned by the Groups module

Add to `Modules\Groups\Models\ClassGroup` (the module that owns `teacher_id`, fixing complication 7 by putting
the rule where the column lives rather than wherever next needs it):

```php
/** True for an admin by convention of the caller; here: unassigned, or taught by $user. */
public function isOpenTo(User $user): bool
{
    return $this->teacher_id === null || $this->teacher_id === $user->id;
}

/** Query scope: groups a teacher may act on (their own + unassigned). Admin bypasses this scope entirely. */
public static function scopeOwnedBy(Builder $query, User $user): Builder
{
    return $query->where(fn ($q) => $q->whereNull('teacher_id')->orWhere('teacher_id', $user->id));
}

/** Group names a teacher may act on, for the Student-keyed modules (Payments, Exams, Attendance, Duty, FaceTemplate). */
public static function namesOwnedBy(User $user): array
{
    return static::query()->ownedBy($user)->pluck('name')->all();
}
```

This is the exact rule already proven in `ChatAccess::canAccessGroup()`/`contacts()`/`participants()` — it is
being relocated to its owning module and exposed as a public, documented method other modules may call (§6:
"call its public actions/contracts," not raw cross-module queries). `ChatAccess` should eventually delegate to
this instead of duplicating the condition, but **that refactor of Chat is not part of this ADR** (§27.2: change
only what was asked) — flagged as a follow-up cleanup once this lands.

### 2. A real Policy per module, replacing `authorizeStaff()` for scoped resources

Each policy follows the same shape: `admin` is always allowed; a `teacher` is allowed only when
`ClassGroup::isOpenTo()`/`namesOwnedBy()` says the resource's group is theirs or unassigned; anyone else is
denied. Wired via `Gate::policy()` in each module's `ServiceProvider`, matching the existing (if currently
unused) convention from `WorksheetPolicy`/`StudentPolicy`:

- `Modules\Groups\Policies\ClassGroupPolicy` — `create` (admin or teacher; a teacher may not set `teacher_id`
  to anyone but themselves, or omit it), `update` (admin, or teacher on a group `isOpenTo` them — and even
  then a teacher's update request must **not** change `teacher_id` at all; strip/reject it for non-admins so
  "update my schedule" and "reassign this group" are enforced as the two different operations complication 1
  identified), `delete` (**admin-only** — consistent with `StudentController::destroy` already being
  admin-only in this codebase; deleting a whole group is a structural decision, not a scheduling one).
- `Modules\Exams\Policies\ExamResultPolicy` — `create`/`update`/`delete` require admin, or teacher where
  `$examResult->student->group` is in `ClassGroup::namesOwnedBy($user)`; `index`/`viewAny` likewise filters the
  query to those group names for a teacher (admin unfiltered).
- `Modules\Payments\Policies\PaymentPolicy` — same shape for `index`/`create`/`update`. `delete` is
  **admin-only**, not teacher-own-scope: deleting a payment record destroys the only record of money taken,
  which is a materially different risk than deleting an exam result or a duty — a teacher who collected cash
  and wants to make a shortfall disappear should not be able to do so unilaterally. (Payments already model
  reversal via state, not deletion — `pending → paid | overdue`, §11 — so removing the teacher's ability to
  hard-delete loses no legitimate correction path; they can still correct amount/status.)
- `Modules\Attendance\Policies\AttendanceRecordPolicy` — same shape for `index`/`create`/`update`/`delete`
  (attendance correction, unlike payment deletion, is a routine teacher action with no analogous fraud
  surface, so stays teacher-own-scope through `delete`). `scan` keeps using `authorizeStaff()` only (complication 3).
- `Modules\Students\Policies\FaceTemplatePolicy` — same shape for `index`/`update`/`destroy` on the student's
  own group.
- `Modules\Learning\Policies\DutyPolicy` — same shape for `store`/`update`/`destroy`, scoped by resolving the
  duty's `group` string the same way (`ClassGroup::namesOwnedBy`). `AgendaController` needs **no new policy** —
  its existing `created_by`-based `visibleTo()` scoping already satisfies §23 and is a different, already-correct
  shape (personal ownership, not group ownership); do not force it into the same policy for consistency's sake
  alone (§8: "do not force patterns into simple code").

`SchoolMessageController::send` has no persisted model instance to gate (it is an action that resolves a set of
recipient ids, not a CRUD endpoint on an existing row), so a `Gate::policy()` class is the wrong shape for it
(§8, final rule: simpler solution wins). Instead, scope its **inputs**: a non-admin may not set `all=true`
(admin-only, full-school broadcast); a non-admin's `group` must be one of `ClassGroup::namesOwnedBy($user)`;
a non-admin's `student_ids` must all belong to students whose `group` is in that same set. Reuse the identical
helper from §1; this is validation-time filtering, not a policy object, and matches how the controller already
validates everything else inline.

`authorizeStaff()` itself is **not deleted**. It remains the terminal check, unchanged, for:
`HandwritingController::__invoke`, `IntegrationController::*`, `ExamManagementController`'s
department/template/PDF endpoints, `QuestionBankController::*`, `AttendanceController::scan/sweep/remindSessions`,
`QrCheckinController::lookup/payment`, `DutyController::remind`. It also stays as the first-line "is this a
staff role at all" check *inside* every new policy before the ownership check, exactly as `OnlineSessionController`
already composes it with `ChatAccess::canAccessGroup()` today — that composition is the reference pattern this
ADR generalizes, not a new idea.

### 3. Per-endpoint decision table

| Controller::method | Decision | Reasoning |
|---|---|---|
| `Groups\ClassGroupController::index` | Teacher-own-scope (own + unassigned); admin unfiltered | §23; mirrors `ChatAccess::contacts()`'s existing group filter for teachers |
| `::store` | Teacher-own-scope; `teacher_id` may only be self or omitted for non-admins | Complication 1 |
| `::update` | Teacher-own-scope for schedule fields; `teacher_id` change is admin-only | Complication 1 — this is the core finding |
| `::destroy` | **Admin-only** | Matches `StudentController::destroy` precedent; structural, not scheduling |
| `Learning\DutyController::store/update/destroy` | Teacher-own-scope (via `group` string) | Same defect shape as Groups/Exams/Attendance, found while enumerating |
| `Learning\AgendaController::*` | **No change** | Already correctly scoped via `created_by` |
| `Learning\HandwritingController::__invoke` | **No change — stays staff-wide** | No owned resource; stateless utility |
| `Exams\ExamResultController::index/store/update/destroy` | Teacher-own-scope (via student's group) | Named directly by the audit |
| `Exams\ExamManagementController` (departments/templates/pdf) | **No change — stays staff-wide** | Complication 4: no natural owner, no student/group link |
| `Exams\QuestionBankController::*` | **No change — stays staff-wide** | Same as above |
| `Exams\ExamManagementController` session endpoints | **No change** | Already scoped via `authorizeSessionOwner`, not `authorizeStaff()` |
| `Attendance\AttendanceController::index/store/update/destroy` | Teacher-own-scope (via student's group) | Named directly by the audit's shape |
| `Attendance\AttendanceController::scan` | **No change — stays staff-wide** | Complication 3: front-desk/gate check-in |
| `Attendance\AttendanceController::sweep/remindSessions` | **No change — stays staff-wide** | Batch/system trigger across all groups by design |
| `Calls\IntegrationController::*` | **No change** | Already self-scoped by `user_id`; no cross-teacher data reachable |
| `Calls\OnlineSessionController::update/destroy` | **No change** | Already correctly scoped via `ChatAccess::canAccessGroup` — the reference pattern |
| `Payments\PaymentController::index/store/update` | Teacher-own-scope (via student's group) | Named directly by the audit |
| `Payments\PaymentController::destroy` | **Admin-only** | Financial-integrity reasoning above, distinct from the other three |
| `Payments\QrCheckinController::lookup/payment` | **No change — stays staff-wide** | Complication 3: front-desk workflow, documented in the controller itself |
| `Notifications\SchoolMessageController::send` | Teacher-own-scope for `group`/`student_ids`; `all=true` admin-only | Input-level scoping, not a policy object |
| `Students\FaceTemplateController::index/update/destroy` | Teacher-own-scope (via student's group) | §13.1 biometric sensitivity + §23 |

## Alternatives Considered

### (a) Shared `ClassGroup` ownership helper + real per-module Policies — **chosen**
See Decision. Reuses the exact rule already proven correct in `ChatAccess`/`OnlineSessionController` instead of
inventing a new shape; follows the module layout (`Policies/`, `Gate::policy()`) the codebase already
establishes (if, until now, only with dead stubs); keeps the handful of genuinely staff-wide/front-desk/batch
endpoints explicitly unscoped rather than silently breaking them.

### (b) Inline the "`teacher_id === null OR mine`" check in each controller, no Policy classes
*Tradeoffs:* Fewer new files; faster to land. Rejected: the identical rule would be hand-copied into roughly
nine controllers across six modules — exactly the duplication that let `authorizeStaff()` silently become the
whole check in the first place (one shared helper is easy to audit in one place; nine inline copies are not).
Violates §8's SRP spirit even though no single copy is complex.

### (c) One generic `TeacherOwnedPolicy`/`TeachableResource` interface applied uniformly to every affected model
*Tradeoffs:* Removes the five near-identical per-module Policy classes down to one. Rejected for now: YAGNI —
five models sharing one two-line rule do not yet justify a new abstraction layer, and the constitution's
guidance against reflexive pattern layers (§8: "do not force them into simple code") applies to authorization
exactly as it does to repositories. If a sixth or seventh module needs the same shape, revisit as a
generalization then, with real evidence of the duplication cost.

### (d) Replace `Student.group` (string) with a real `group_id` foreign key as part of this change
*Tradeoffs:* This is the structurally correct fix for the fragility noted in Context (a group rename silently
detaches past string matches) and would make every policy's join exact instead of name-matched. Rejected for
this ADR: it is a materially larger, unrelated migration touching `Students`, `Groups`, `SessionReminders`,
`ChatAccess`, and `SchoolMessageController` simultaneously, with its own backward-compatibility and backfill
concerns (§9: expand → migrate → contract) — a scope change this ADR's task did not ask for (§27.2: change
only what was asked). Flagged as a candidate for its own future ADR.

### (e) Patch only the three modules the audit named (Payments, Groups, Exams), leave Attendance/Duty/Notifications/FaceTemplate as-is
*Tradeoffs:* Smaller diff. Rejected: reading every `authorizeStaff()` call site (as this task required) found
the identical defect shape in Attendance, Duty and FaceTemplate, and a related-but-different one in
`SchoolMessageController`'s `all=true`. Leaving them unfixed because they were not named in the original audit
would leave a predictable third finding for the next audit, and is inconsistent with applying one rule
uniformly across one mechanism.

## Consequences

- **Positive:** Every endpoint that currently gives a teacher near-admin reach across the whole school gets
  scoped to their own groups/students, matching §23 literally, using a mechanism relocated to its correct
  owning module (Groups) rather than duplicated or left in Chat. The handful of endpoints that must stay
  staff-wide for real operational reasons (front desk, gate scan, batch triggers, shared curriculum catalogue,
  personal agenda, OAuth integration) are identified and explicitly preserved, not accidentally broken by a
  blanket "restrict everything" patch.
- **Negative / follow-up work required (not done by this ADR):**
  - Implementation touches controllers/policies/service-provider wiring in six modules
    (Groups, Learning, Exams, Attendance, Payments, Students) plus `Modules/Groups/Models/ClassGroup.php` —
    a backend-engineer task against this decision, not included here.
  - New tests per scoped endpoint: own-group CRUD allowed, another teacher's-group CRUD → 403 with an Arabic
    message, unassigned-group CRUD allowed, admin bypass, and — importantly — regression tests proving the
    deliberately-unscoped endpoints (`scan`, `sweep`, `remindSessions`, `QrCheckinController`, the exam/question
    catalogue, `HandwritingController`, `IntegrationController`) still allow any staff member, so a future
    change does not "fix" them by accident.
  - `Student.group` string-matching fragility (Alternative d) is inherited unchanged; worth its own future ADR
    if group renames start causing visible authorization gaps, not before.
  - The Chat-module-owns-a-Groups-column migration (complication 7) and the two dead Policy stubs
    (`WorksheetPolicy`, `StudentPolicy`) are pre-existing issues surfaced during this audit; recorded here for
    awareness, neither is in scope to fix as part of this change.
- **Risk if not done:** every endpoint in the table above that still relies solely on `authorizeStaff()`
  remains a live case of one teacher reading or mutating another teacher's groups, students, grades, payments
  or biometric data — the exact finding this ADR exists to close.
