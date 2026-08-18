# LMS

**Al-Imtiaz Math Platform** is an Arabic RTL learning-management system for mathematics education. The platform combines a Laravel 13 API with Eloquent models, a React 19/Vite frontend, monitored exams, attendance QR workflows, reports, payments, worksheets, and dimensioned geometry questions.

## Architecture

| Area | Stack and responsibility |
|---|---|
| Frontend | React 19, TypeScript, Vite, Tailwind CSS 4, Arabic RTL UI, browser-side exam PDF capture |
| Backend | Laravel 13, PHP 8.3, Eloquent ORM, Sanctum role-specific authentication |
| Modules | `Modules/Attendance` and plugin-store boundaries using `nwidart/laravel-modules` |
| Data | MySQL-compatible production configuration and SQLite test workflow |
| Quality | Vitest, TypeScript checks, Laravel feature tests, PHP syntax checks, and Vite production builds |

## Local development

Install Node dependencies with `pnpm install`, then run the frontend and server with `pnpm dev`. For the Laravel API, copy `laravel-backend/.env.example` to `laravel-backend/.env`, configure the database, run `php artisan key:generate`, and apply migrations with `php artisan migrate`.

Run the standard checks with `pnpm lint`, `pnpm check`, `pnpm test`, and `pnpm build`. Run Laravel checks from `laravel-backend` with `php artisan test` and the PHP syntax command documented in `.github/workflows/ci.yml`.

## Agile Git workflow

`main` is the protected, releasable branch. The long-lived integration branches are `backend` and `frontend`; each contains only changes for its corresponding application layer and is merged into `main` through review. Every feature starts from the relevant integration branch using a short-lived branch such as `feature/backend-exam-grading` or `feature/frontend-exam-preview`.

Pull Requests are required for all merges. A PR should describe the user story, acceptance criteria, implementation notes, tests, screenshots or PDF evidence when relevant, and any migration or rollback considerations. CI must pass before review. The preferred delivery cycle is: backlog item, feature branch, small commits, PR, automated checks, reviewer approval, squash merge, and branch deletion.

## Branch convention

| Branch | Purpose |
|---|---|
| `main` | Protected release branch |
| `backend` | Laravel, Eloquent, migrations, modules, API, and backend tests |
| `frontend` | React, styling, client API integration, and frontend tests |
| `feature/backend-*` | Short-lived backend feature branch |
| `feature/frontend-*` | Short-lived frontend feature branch |
| `bugfix/*` | Reproducible defect fix |
| `chore/*` | Tooling, documentation, or maintenance |

See [`docs/git-agile-workflow.md`](docs/git-agile-workflow.md) and the PR template for the complete process. CI runs on every push and Pull Request through `.github/workflows/ci.yml`.
