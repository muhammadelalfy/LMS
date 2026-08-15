# Al Imtiaz LMS Laravel Backend

This directory contains the Laravel 13 API for the Al Imtiaz Arabic mathematics LMS. It uses Eloquent models and migrations for MySQL and Laravel Sanctum for token-based authentication.

## Domain model

The backend models users, students, student accounts, worksheets, worksheet assignments, attendance records, exam results, and payments. A worksheet assignment belongs to exactly one worksheet and student, and its lifecycle is `assigned`, `in_progress`, `submitted`, or `graded`.

## MySQL configuration

Production should use MySQL 8 or a compatible managed MySQL service. Configure the backend environment with:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.example.com
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=al_imtiaz
DB_USERNAME=...
DB_PASSWORD=...
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
```

Never commit a real `.env` file or credentials. Run `php artisan migrate --force` against the configured MySQL database during deployment.

## API contract

Authentication is exposed through `/api/auth/register`, `/api/auth/login`, `/api/auth/me`, and `/api/auth/logout`. Protected LMS endpoints use Sanctum bearer tokens. Admins and teachers can manage students, create worksheets, assign work, and view reports. Students can submit their own assignments, while parents and students can read only the records authorized by their account relationship.

The current report endpoint is `/api/reports/summary`; it aggregates student count, attendance statuses, exam totals, and payment totals from Eloquent queries.

## Local verification

The feature suite runs against an in-memory SQLite database so it does not require local production credentials:

```bash
php artisan test --compact
```

The test suite currently covers registration/login, teacher assignment, student submission, and role authorization. Production validation must also run the migration and endpoint smoke checks against MySQL.

## Arabic local demo data

The project includes localized factories and a guarded `ArabicDemoSeeder` for local development and automated testing. Run it with:

```bash
APP_ENV=local php artisan db:seed --class=Database\\Seeders\\ArabicDemoSeeder --force
```

The seeder is intentionally blocked outside `local` and `testing`. It is idempotent and creates six Arabic students, parent and student accounts, unique QR tokens, published mathematics worksheets, assignments, five days of attendance history, exam results, and payment records. Development credentials are printed by the command and must not be reused in production.
