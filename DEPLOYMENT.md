# Clyvero ERP production deployment

Recommended low-cost architecture:

- Next.js frontend on Vercel (`app.yourdomain.com`).
- Laravel API, PostgreSQL, Redis, queue worker, scheduler, and Caddy TLS on one Ubuntu 24.04 VPS (`api.yourdomain.com`).
- Encrypted nightly backups copied off the VPS.

## 1. Prepare the release

1. Store the project in a private Git repository and create a tagged release.
2. On the VPS, install Docker Engine and the Docker Compose plugin.
3. Clone the release to `/srv/clyvero`.
4. Copy `backend/.env.production.example` to `backend/.env.production`.
5. Replace every example value. Generate `APP_KEY` with:

   ```bash
   docker run --rm php:8.4-cli php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"
   ```

6. Use independent random passwords of at least 32 characters for PostgreSQL and Redis. Keep `.env.production` outside Git and in a password manager.
7. Point the DNS `A` record for `api.yourdomain.com` to the VPS.

## 2. Validate and launch the backend

```bash
chmod +x scripts/*.sh
./scripts/validate-production.sh /srv/clyvero
cd backend
docker compose --env-file .env.production -f compose.production.yml build --pull
docker compose --env-file .env.production -f compose.production.yml up -d postgres redis
docker compose --env-file .env.production -f compose.production.yml run --rm app php artisan migrate --force
docker compose --env-file .env.production -f compose.production.yml up -d
docker compose --env-file .env.production -f compose.production.yml exec app php artisan optimize
docker compose --env-file .env.production -f compose.production.yml ps
curl --fail --show-error https://api.yourdomain.com/up
```

Only seed a brand-new empty installation. Never seed an established production database.

## 3. Deploy the frontend to Vercel

Import only the `frontend` directory, use Node.js 22, and configure:

```text
NEXT_PUBLIC_API_BASE=https://api.yourdomain.com/api/v1
NEXT_PUBLIC_API_URL=https://api.yourdomain.com
NEXT_PUBLIC_API_HOSTNAME=api.yourdomain.com
```

Point `app.yourdomain.com` to Vercel, then ensure the backend contains:

```text
FRONTEND_URL=https://app.yourdomain.com
CORS_ALLOWED_ORIGINS=https://app.yourdomain.com
SANCTUM_STATEFUL_DOMAINS=app.yourdomain.com
```

## 4. Backups and restore test

Run nightly:

```bash
/srv/clyvero/scripts/backup-production.sh /srv/clyvero /srv/backups/clyvero
```

The backup includes PostgreSQL and the complete public-upload volume. It intentionally excludes `.env.production`; store that secret separately. Copy backups to encrypted off-site storage and perform a restore rehearsal before launch.

Restore only after verifying the target and exact database name:

```bash
/srv/clyvero/scripts/restore-production.sh \
  /srv/clyvero /srv/backups/clyvero/20260824T010000Z \
  --confirm-db clyvero
```

## 5. Release acceptance

- Confirm HTTPS and `/up`.
- Confirm all services are healthy and queue jobs are processed.
- Test administrator OTP, teacher, secretary, parent, and platform-owner login.
- Create a staging student and verify photo/header upload, fees, payment, receipt, grade entry, report card, timetable, and Excel import/export.
- Verify school isolation with two staging schools.
- Verify password reset email and restore one backup.
- Check browser console, Laravel/container logs, mobile layout, and logout.
- Keep the previous release tag and backup until acceptance is signed off.
