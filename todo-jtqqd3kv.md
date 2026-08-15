# Implementation checklist

- [x] Inspect current Home.tsx state, student views, and notification UI.
- [x] Add localStorage-backed roster state in the main dashboard.
- [x] Add create-student form with validation and save/cancel behavior.
- [x] Add edit-student form connected to the student drawer.
- [x] Add delete confirmation and remove students from persisted roster state.
- [x] Add dashboard search and status/group/payment filters.
- [x] Add notifications dropdown with unread state and mark-all-as-read.
- [x] Run type-check and production build.
- [x] Capture a representative preview and save a checkpoint.

## Notes

- Keep the frontend-only architecture; do not modify server or backend files.
- Preserve the existing Arabic “دفء الفصل” visual direction.
- Use sonner for concise action feedback.
- Persist roster and notification read state in localStorage with safe browser guards.

## Verification

- [x] Create a student and confirm it remains after reload.
- [x] Edit a student and confirm the drawer and roster update.
- [x] Delete a student after confirmation and confirm persistence.
- [x] Search and filter students from the dashboard.
- [x] Open notifications, mark all read, and confirm the badge updates.
