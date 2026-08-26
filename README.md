# The Laureates · School Management ERP

A production-grade, role-based school management system built for K–12 institutions in Cameroon. Monorepo containing a Laravel 12 + PostgreSQL 16 API backend and a Next.js 15 (App Router) frontend.

> **Author:** Junior (BSc Cybersecurity, ICT University, Yaoundé)
> **Supervisor:** Engr. NEBASI

## What's inside

The Laureates manages every operational concern for a school:

| Module | Capabilities |
| --- | --- |
| **People** | Students, Teachers, Parents/Guardians, User accounts |
| **Academic structure** | Academic years, terms, subjects, classes, class-subject pivots with coefficients |
| **Attendance** | Daily class sessions, four statuses (Present/Absent/Late/Excused), per-student summaries |
| **Grades & Assessment** | Assessments by class+subject+term, weighted averages, dense-rank class rankings |
| **Timetable** | Weekly class schedules, teacher conflict detection |
| **Finance & Cafeteria** | Fee structures, auto-generated invoices, payments (cash, MTN MoMo, Orange Money, bank, cheque) |
| **Report cards** | Per-term PDF report cards with grades + attendance + rank (DomPDF) |
| **Dashboards** | Role-aware: Administrator, Secretary, Teacher, Parent |
| **Notifications** | In-app inbox + broadcast to a role |
| **System** | Settings (school identity), User management, Audit logs (immutable) |

Four roles ship with curated default permissions:
- **Administrator** — every permission.
- **Secretary** — full enrollment + finance + day-to-day operations.
- **Teacher** — own classes, attendance, grading, view timetable, view report cards.
- **Parent** — own children only (scoped by policies): grades, attendance, balance, report card download.

## Stack

**Backend** Laravel 12, PHP 8.4, PostgreSQL 16, Redis 7, Sanctum, DomPDF, Intervention/Image · Laravel Sail (Docker).
**Frontend** Next.js 15 (App Router), React 19, TypeScript, Tailwind, Zustand, TanStack Query v5, react-hook-form + Zod, Sonner, Axios.

## Repository layout

```
the-laureates/
├── backend/                           Laravel 12 application
│   ├── app/
│   │   ├── Enums/                     Backed string enums
│   │   ├── Http/Controllers/Api/V1/   One thin controller per resource
│   │   ├── Http/Requests/             FormRequests grouped by module
│   │   ├── Http/Resources/            API resource transformers
│   │   ├── Models/                    Eloquent models, UUID PKs, AuditObserver
│   │   ├── Policies/                  Permission-aware authorization
│   │   ├── Repositories/              Contracts + Eloquent impls
│   │   ├── Services/                  Business logic (per module)
│   │   └── Traits/                    HasUuid, Auditable
│   ├── database/
│   │   ├── migrations/                Phase-prefixed migrations
│   │   ├── seeders/                   Roles, Permissions, School, AdminUser
│   │   └── factories/
│   ├── routes/
│   │   ├── api.php                    Mounts all v1 route files
│   │   └── api/v1/                    students.php, finance.php, attendance.php, …
│   └── tests/Feature/                 Integration tests per module
│
└── frontend/                          Next.js 15
    └── src/
        ├── app/
        │   ├── (authenticated)/
        │   │   ├── admin/             Administrator pages
        │   │   ├── secretary/         Secretary pages
        │   │   ├── teacher/           Teacher pages
        │   │   └── parent/            Parent pages
        │   └── (public)/              Login + public
        ├── components/
        │   ├── ui/                    Design system primitives
        │   ├── layout/                Sidebar, Topbar, Footer
        │   └── <module>/              Module-specific components
        ├── config/navigation.ts       Permission-gated sidebar map per role
        ├── hooks/<module>/            React Query hooks per module
        ├── lib/
        │   ├── api/                   Axios client + per-module API objects
        │   └── utils/                 cn, debounce, etc.
        ├── store/                     Zustand stores (auth)
        └── types/                     Shared TypeScript types
```

## API conventions

Every endpoint returns the standard envelope:

```json
{ "success": true, "message": "Students retrieved.", "data": [...], "meta": { "page": 1, "per_page": 25, "total": 42, "last_page": 2 } }
```

Errors:

```json
{ "success": false, "message": "Validation failed.", "error_code": "VALIDATION_ERROR", "errors": { "email": ["..."] } }
```

All v1 endpoints are mounted under `/api/v1` and require a Sanctum token via `Authorization: Bearer <token>` except `/auth/login` and `/auth/register`.

## Defaults

After seeding, log in with:

| Email | Password | Role |
| --- | --- | --- |
| admin@the-laureates.test | TheLaureates2026! | Administrator |

The school name, academic-year title and admission-number prefix can be customized in `backend/.env`:

```
APP_NAME="The Laureates"
SCHOOL_MOTTO="Excellence · Integrity · Service"
ADMISSION_NUMBER_PREFIX=TL
```

## Phases delivered

| Phase | Module | Status |
| --- | --- | --- |
| 1 | Foundation (auth, RBAC, audit, UI primitives, sidebar, login) | done |
| 2 | Students | done |
| 3 | Teachers | done |
| 4 | Parents | done |
| 5 | Academic structure (years, terms, subjects, classes) | done |
| 6 | Attendance | done |
| 7 | Grades & Assessment | done |
| 8 | Timetable | done |
| 9 | Finance + Cafeteria | done |
| 10 | Report cards (PDF) | done |
| 11 | Dashboards (4 roles) | done |
| 12 | Settings, Users, Audit log, Notifications | done |
| 13 | Tests + final QA | done |

## License

Coursework project — © 2026 Junior, ICT University Yaoundé. All rights reserved.
