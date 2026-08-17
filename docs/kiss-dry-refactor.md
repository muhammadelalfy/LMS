# KISS and DRY Refactor Note

## Scope

This refactor follows two constraints: keep application behavior simple and visible, and remove repetition only when a small shared abstraction improves consistency. Existing Laravel API routes, response shapes, Arabic labels, offline behavior, and module boundaries remain stable.

## Audit findings

The largest application files were `client/src/pages/LiveDashboard.tsx`, `client/src/pages/Home.tsx`, and `client/src/lib/laravelApi.ts`. The API client repeated token persistence and collection-response unwrapping. Several Laravel API controllers repeated the same admin/teacher authorization check. These were selected because they are local, low-risk patterns with clear contracts.

The generated shadcn UI files and the large dashboard screens were intentionally not rewritten in this slice. They are either generated infrastructure or contain feature-specific orchestration where premature extraction would make the code harder to follow. The remaining student, worksheet, exam, payment, and report controllers also remain on their stable routes until each domain has a focused module migration and regression suite.

## Refactors applied

The frontend API client now uses `saveToken()` for authentication token persistence and `requestCollection()` for `{ data: T[] }` responses. The backend API controllers use the small `AuthorizesStaff` concern for the shared admin/teacher authorization rule. Attendance remains behind its explicit domain service, preserving the existing QR and CRUD behavior.

## Verification

The refactor is accepted only when PHP lint, the Laravel feature suite, frontend TypeScript, and Vitest pass. The production build remains the final compatibility check before checkpointing.
