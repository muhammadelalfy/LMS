# Continuous Integration

The repository CI workflow runs on every push and pull request through GitHub Actions. It has three jobs:

- **Laravel checks** (**PHP 8.5**, Composer 2, and the extensions Laravel, SQLite tests and ZIP-based plugin installation need).
- **API image + stack smoke test**, which builds the image and rolls it out twice behind the load balancer (see `deploy/README.md`).
- **Deploy to production**, on pushes to `main` only.

The Laravel job installs from `composer.lock`, copies `.env.example`, generates an application key, creates a SQLite database file, sets `DB_CONNECTION=sqlite` and `DB_DATABASE`, runs migrations, checks PHP syntax with `php -l`, and executes the Laravel feature suite. The PHP lint step is syntax validation rather than a style formatter, keeping CI dependency-light and compatible with the existing Laravel codebase.

To reproduce the CI checks locally from the repository root, run:

```bash
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
