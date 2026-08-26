# Setup guide

Step-by-step instructions to get The Laureates running on your machine.

## Prerequisites

- **PHP 8.4+** with extensions: `pdo_pgsql`, `mbstring`, `xml`, `bcmath`, `gd`
- **Composer 2.x**
- **Node.js 20+** and **npm**
- **PostgreSQL 16** (or use the bundled `docker-compose.yml` for Sail)
- **Redis 7** (optional — only if you switch `CACHE_STORE`/`QUEUE_CONNECTION` from `database` to `redis`)

Don't have any of these locally? The `backend/docker-compose.yml` ships PHP, PostgreSQL, and Redis via Laravel Sail — just run `./vendor/bin/sail up` after installing Composer dependencies.

## 1. Backend — Laravel API

```bash
cd backend

# Install PHP dependencies (downloads vendor/, ~120 MB)
composer install

# Copy the env template and fill in DB credentials
cp .env.example .env

# Generate APP_KEY (writes it into .env automatically)
php artisan key:generate

# Create the database (manual step — pick the option that fits)
#   Option A (PostgreSQL directly): psql -U postgres -c "CREATE DATABASE laureates;"
#   Option B (Docker via Sail):     ./vendor/bin/sail up -d

# Run all migrations
php artisan migrate

# Seed roles, permissions, the school, and the default admin user
php artisan db:seed

# Start the dev server (http://localhost:8000)
php artisan serve
```

The seeder creates these credentials:

- Email: `admin@the-laureates.test`
- Password: `TheLaureates2026!`

Change `APP_NAME`, `SCHOOL_MOTTO`, and DB credentials in `.env` before seeding for production.

## 2. Frontend — Next.js app

```bash
cd frontend

# Install JavaScript dependencies (downloads node_modules/, ~400 MB)
npm install

# Point the frontend at the backend (default is http://localhost:8000/api/v1)
cp .env.example .env.local
# Edit if your backend runs on a different host/port

# Start the dev server (http://localhost:3000)
npm run dev
```

Open `http://localhost:3000`, log in with the credentials above, and you'll land on the admin dashboard.

## 3. Run the tests

```bash
cd backend
php artisan test
```

This runs the feature tests in `tests/Feature/` against an in-memory SQLite database (configured by `phpunit.xml`).

## Troubleshooting

**"Class not found" on first boot** — run `composer dump-autoload`.

**Migrations fail with "relation does not exist"** — drop the database and re-run `php artisan migrate:fresh --seed`.

**Frontend shows "Network Error"** — check `.env.local` points at the running backend and that CORS is open for `http://localhost:3000` (already configured in `backend/config/cors.php`).

**`storage/` permission denied** — `chmod -R 775 storage bootstrap/cache` and make sure your webserver user owns them.

**PDF report cards fail** — DomPDF needs the GD extension. Verify with `php -m | grep gd`.

## Production deploy notes

1. Set `APP_ENV=production`, `APP_DEBUG=false`, real `APP_URL`.
2. Run `php artisan config:cache route:cache view:cache event:cache`.
3. Set up a queue worker if you use email or async jobs: `php artisan queue:work`.
4. The frontend builds with `npm run build` then `npm start`, or deploy to Vercel.
5. Serve `backend/public/` from Nginx/Apache, not `backend/` itself.
