# Continuous Integration

The repository CI workflow runs on every push and pull request through GitHub Actions. It uses an Ubuntu runner with **Node.js 22**, **pnpm 10.4.1**, **PHP 8.3**, Composer 2, and the PHP extensions required by Laravel, SQLite tests, and ZIP-based plugin installation.

The frontend job installs dependencies with `pnpm install --frozen-lockfile`, then runs the scoped `pnpm lint` script, TypeScript checks, Vitest, and the production build. The lint scope intentionally covers maintained application-client/API files and CI documentation/configuration. Generated shadcn components and legacy template files are not included in the gate until they are normalized in a dedicated formatting pass.

The Laravel job installs from `composer.lock`, copies `.env.example`, generates an application key, creates a SQLite database file, sets `DB_CONNECTION=sqlite` and `DB_DATABASE`, runs migrations, checks PHP syntax with `php -l`, and executes the Laravel feature suite. The PHP lint step is syntax validation rather than a style formatter, keeping CI dependency-light and compatible with the existing Laravel codebase.

To reproduce the CI checks locally from the repository root, run:

```bash
pnpm install --frozen-lockfile
pnpm lint
pnpm check
pnpm test -- --run
pnpm build

cd laravel-backend
composer install --no-interaction --prefer-dist --no-progress
cp .env.example .env
php artisan key:generate
mkdir -p database
touch database/database.sqlite
printf '\nDB_CONNECTION=sqlite\nDB_DATABASE=%s\n' "$PWD/database/database.sqlite" >> .env
php artisan migrate --force
find app Modules tests -type f -name '*.php' -print0 | xargs -0 -n1 php -l
php artisan test --compact
```
