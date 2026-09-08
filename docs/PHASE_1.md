# Phase 1 — Project Foundation

This document explains what Phase 1 delivers, how to run it, and what to expect on first launch.

## What's in Phase 1

**Backend (Laravel 12 + PostgreSQL + Redis)**

- Complete folder architecture per Backend Blueprint §4 (Actions, DTO, Enums, Events, Repositories/{Contracts,Eloquent}, Services, Policies, Rules, Traits, Observers, Helpers, Utils, Jobs, Listeners, Notifications, Mail)
- 10 foundation migrations: schools, academic_years, roles, permissions, role_permissions, users (+ password_reset_tokens + sessions), personal_access_tokens (UUID-patched for Sanctum), audit_logs, activity_logs, cache+jobs+failed_jobs
- UUID primary keys throughout via `HasUuid` trait
- Soft deletes on User, School, AcademicYear (archival pattern)
- `AuditLog` model with hard-blocked update/delete (immutable from app)
- Auth API: `POST /api/v1/login`, `POST /logout`, `GET /me`, `POST /forgot-password`, `POST /reset-password`, `POST /refresh-token`
- Standard response envelope on every endpoint (`success`, `message`, `data`, `meta` / `errors`)
- 12-character strong password policy enforced via `App\Rules\StrongPassword`
- Account lockout after 5 failed login attempts (15-minute lock)
- Rate limiting: 5 login attempts/min, 3 password resets/hour, 100 general/min — all tunable in `.env`
- Permission catalog seeded for all future modules; each role gets a curated default permission set
- Audit infrastructure ready: `Auditable` trait + `AuditObserver` will auto-log every model that uses it
- Test suite: `LoginTest`, `LogoutTest`, `MeTest`, `ForgotPasswordTest`, `ResetPasswordTest`, `StrongPasswordRuleTest`

**Frontend (Next.js 15 App Router + Tailwind)**

- Design system locked to Design Philosophy §14-17 tokens (Modern Blue + Slate Gray, 8pt spacing, 12/10/10/16 radii)
- App shell: role-based Sidebar + Topbar with user menu + Footer
- 14 UI primitives: Button, Input, Card (5 sub-parts), Modal, Drawer (right-side), Pagination, DataTable, Badge, Avatar, Skeleton, EmptyState, Breadcrumb, PageHeader, Spinner
- Auth pages: Login, Forgot password, Reset password — all using react-hook-form + Zod (Zod schemas mirror backend Form Request rules)
- Four role dashboards (Phase 11 will populate them with real analytics)
- AuthGuard component wrapping the `(authenticated)` route group
- Forbidden page for role-mismatched access
- Zustand auth store with localStorage persistence + automatic /me hydration
- TanStack Query installed and provider-mounted
- Sonner toaster (top-right, 5-second auto-dismiss per Design Philosophy)
- Axios client with bearer-token injection + global 401 handling

## Setup (first time)

Prerequisites on your machine:

- Docker Desktop (or Docker Engine + Compose v2)
- PHP 8.4 and Composer (for the initial `composer create-project` step)
- Node.js 20+ and npm
- Git

From the repo root, run:

```bash
bash scripts/setup.sh
```

The script will:

1. Install the Laravel 12 skeleton into `backend/`, then layer the Phase 1 custom code over it
2. Install Sanctum, Sail, predis, dompdf, intervention/image
3. Initialize Sail with PostgreSQL + Redis + Mailpit
4. Install the Next.js 15 skeleton into `frontend/`, layer custom code, install runtime deps
5. Bring up Sail and run `migrate --seed`

Total time: ~5-10 minutes depending on your connection.

## Running the system

Backend (from `backend/`):

```bash
./vendor/bin/sail up -d           # start containers
./vendor/bin/sail artisan migrate # if you didn't seed during setup
./vendor/bin/sail artisan db:seed
```

Frontend (from `frontend/`):

```bash
npm run dev
```

Then open http://localhost:3000.

## First login

The default administrator is seeded from `.env`:

| Field | Value |
|---|---|
| Email | `admin@the-laureates.test` |
| Password | `TheLaureates2026!` |

After signing in you'll land on `/admin/dashboard`. **Change the admin password immediately** — the seeded credentials are public knowledge.

## What to test

1. **Login (happy path)** — submit valid credentials, get redirected to `/admin/dashboard`.
2. **Wrong password** — error banner appears, account counter increments.
3. **5 wrong passwords** — account locks for 15 minutes, error becomes "account_locked".
4. **Inactive account** — flip `is_active = false` in DB, login returns 403 "account_inactive".
5. **Forgot password** — submit any email, Mailpit (http://localhost:8025) catches the reset email.
6. **Reset password link** — click it, set a new strong password, sign in.
7. **Logout** — token revoked server-side; refreshing requires sign-in again.
8. **Role gating** — create a user with `role = teacher`, sign in, observe sidebar shows only teacher items.

## Running tests

```bash
cd backend
./vendor/bin/sail artisan test --testsuite=Feature
./vendor/bin/sail artisan test --testsuite=Unit
```

## What's NOT in Phase 1

Everything below is scoped to later phases — please don't be surprised when the corresponding sidebar links return 404:

- Student / Teacher / Parent profiles (Phase 2-4)
- Classes / Subjects / Academic structure (Phase 5)
- Attendance (Phase 6), Grades (Phase 7), Timetable (Phase 8)
- Finance + Cafeteria (Phase 9)
- Report Cards (Phase 10)
- Dashboard analytics with real numbers (Phase 11)
- Notifications + Settings UI (Phase 12)

## Troubleshooting

**`SQLSTATE[HY000] connection refused`** — Sail's postgres container isn't up. Run `./vendor/bin/sail up -d` and wait 5 seconds.

**CORS errors in the browser** — confirm `FRONTEND_URL=http://localhost:3000` is in `backend/.env` and that `config/cors.php` includes it.

**`401 unauthenticated` immediately after login** — the bearer token isn't being attached. Check the Network tab and verify the `Authorization: Bearer …` header is present.

**Mailpit not catching email** — verify `MAIL_HOST=mailpit` in `backend/.env` and that the mailpit container is up.
