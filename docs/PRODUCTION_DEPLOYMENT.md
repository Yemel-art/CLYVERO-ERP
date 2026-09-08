# Clyvero ERP production deployment

> The authoritative command-by-command procedure is `/DEPLOYMENT.md` in the
> release root. Run `scripts/validate-production.sh` before every production
> launch. This document provides the architectural background.

## Recommended first production architecture

For the first schools, run one Ubuntu LTS VPS with at least 2 vCPU, 4 GB RAM, 80 GB SSD, and automated off-site backups. Use Docker Compose for Laravel, PostgreSQL, Redis, the queue worker, and the scheduler. Run the Next.js standalone server on the same VPS or on Vercel. Put Nginx or Caddy in front of every public service and expose only ports 80 and 443.

Use one application and one database for all schools. Tenant isolation is enforced by `school_id`, tenant middleware, policies, scoped queries, validation, and PostgreSQL integrity triggers. Do not create a code/database copy per school.

Suggested DNS:

- `app.example.com` -> Next.js frontend
- `api.example.com` -> Laravel API, if not using a same-origin `/api` proxy
- HTTPS only, with automatic certificate renewal

The simplest and least error-prone arrangement is same-origin: Nginx serves `app.example.com`, proxies `/api/*` and private media to Laravel, and proxies all other paths to Next.js.

## Required production environment

Laravel:

```dotenv
APP_NAME="Clyvero ERP"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://app.example.com
APP_KEY=base64:GENERATE_A_REAL_KEY
LOG_CHANNEL=stack
LOG_LEVEL=warning

DB_CONNECTION=pgsql
DB_HOST=pgsql
DB_PORT=5432
DB_DATABASE=clyvero
DB_USERNAME=clyvero
DB_PASSWORD=GENERATE_A_LONG_RANDOM_PASSWORD

CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
REDIS_HOST=redis
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_ENCRYPTION=tls
MAIL_USERNAME=real-account@example.com
MAIL_PASSWORD=GOOGLE_APP_PASSWORD
MAIL_FROM_ADDRESS=real-account@example.com
MAIL_FROM_NAME="Clyvero ERP"

ADMIN_LOGIN_OTP_ENABLED=true
ADMIN_LOGIN_OTP_TTL_MINUTES=10
ADMIN_LOGIN_OTP_MAX_ATTEMPTS=5
```

Next.js:

```dotenv
NODE_ENV=production
NEXT_PUBLIC_API_URL=https://app.example.com
NEXT_PUBLIC_API_BASE=/api/v1
NEXT_PUBLIC_API_HOSTNAME=app.example.com
```

Never commit `.env`, Gmail app passwords, database passwords, `APP_KEY`, or backup archives. `NEXT_PUBLIC_*` values are public and are embedded during `next build`.

## First deployment sequence

1. Create the VPS, a non-root deploy user, SSH keys, firewall rules, and automatic security updates.
2. Install Docker Engine and the Compose plugin.
3. Clone/copy the application into a release directory owned by the deploy user.
4. Create the production `.env` files and generate secrets.
5. Start PostgreSQL and Redis, then wait for their health checks.
6. Install locked PHP dependencies with `composer install --no-dev --prefer-dist --optimize-autoloader`.
7. Run `php artisan migrate --force`.
8. Seed only roles and permissions. Never run demo/full seeders against production.
9. Create the first platform administrator with `php artisan clyvero:create-platform-administrator`.
10. Run `php artisan storage:link` if the deployment serves public storage locally.
11. Run `php artisan optimize` after all environment values are final.
12. Build Next.js with `npm ci && npm run type-check && npm run build`.
13. Start the Laravel web service, Next.js, one queue worker, and the scheduler under Docker restart policies or systemd.
14. Configure Nginx/Caddy, TLS, request-size limits, timeouts, and access logs.
15. Verify `/up`, login + administrator OTP, a signed private image, one receipt, one report card, and a backup/restore rehearsal.

## Long-running processes

Production requires:

- Laravel web service
- `php artisan queue:work --sleep=1 --tries=3 --timeout=120`
- `php artisan schedule:work`, or cron calling `schedule:run` each minute
- Next.js `node .next/standalone/server.js` or `npm run start`
- PostgreSQL and Redis with persistent volumes

Restart queue workers after each deployment so they load the new code. Use graceful termination and a process supervisor/restart policy.

## School provisioning and yearly rollover

- A platform administrator creates each school from the platform console.
- The school receives a unique, editable school code and isolated administrator.
- Only one academic year may be active per school.
- Before rollover, publish complete final grades and configure the school's promotion policy.
- Preview progression decisions before activating the next year.
- Promoted and repeating students receive a new enrollment; excluded students do not.
- Previous enrollments, report cards, attendance, finance, and parent-child links remain historical records.

Do not activate a new year merely because it exists. Existing 2025/2026 records in the current database do not yet contain enough complete final grades and policy data for a trustworthy automatic rollover.

## Monitoring

Monitor at minimum:

- HTTPS availability and Laravel `/up`
- queue failures and queue age
- HTTP 5xx rate and slow requests
- PostgreSQL connection count, disk, locks, and backup success
- Redis availability and memory
- VPS disk, memory, CPU, and certificate expiry
- Gmail/SMTP delivery failures for administrator OTP

Alert before disk use reaches 80%. Keep `APP_DEBUG=false`; send exception details to protected logs or a monitoring service, never to end users.

## Scaling path

One 4 GB VPS is appropriate for the first small schools when backups are off-site. Scale vertically first. When traffic warrants it, move PostgreSQL to a separate managed/server instance, move uploads to S3-compatible object storage, and run multiple Laravel/Next instances behind the proxy. Multi-instance Next.js requires a stable server-actions encryption key and coordinated cache strategy.

## Official references

- Laravel deployment and optimization: https://laravel.com/docs/12.x/deployment
- Next.js deployment: https://nextjs.org/docs/app/getting-started/deploying
- Next.js self-hosting and reverse proxy: https://nextjs.org/docs/app/guides/self-hosting
- PostgreSQL `pg_dump`: https://www.postgresql.org/docs/current/app-pgdump.html
- PostgreSQL `pg_restore`: https://www.postgresql.org/docs/current/app-pgrestore.html
- Docker volumes: https://docs.docker.com/engine/storage/volumes/
