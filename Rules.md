# Coding Rules — The Laureates

Read this file before generating any code. These rules apply to every phase, every module, every file.

## Architecture

1. Follow the Backend Blueprint exactly: **Controller → Form Request → Service → Repository → Model → PostgreSQL**.
2. Controllers are entry points only. They receive requests, call Form Requests, call Services, return API responses. **Never** put database queries, calculations, email sending, PDF generation, or business validation in a controller.
3. Keep controllers under ~100 lines whenever practical.
4. Business logic belongs in Services. Services may call multiple Repositories.
5. Database access belongs in Repositories. **Never** write SQL or Eloquent queries in Controllers or Services.
6. Validation belongs in Form Requests. Never duplicate validation in Controllers.
7. Authorization belongs in Policies/Gates. Check both role-level permission AND resource ownership where applicable.
8. Use Repository **interfaces** (`StudentRepositoryInterface`) bound to concrete implementations (`EloquentStudentRepository`) in `RepositoryServiceProvider`.
9. Pass complex data between Services using **DTOs**, not arrays or Request objects.
10. Use Events/Listeners for post-action side effects (welcome email, receipt generation, parent notifications).
11. Use Queues for heavy work (email, PDF, image processing, large reports). Never block an HTTP request.
12. Use Observers for audit logging — never sprinkle audit calls through Services.

## SOLID & Code Quality

13. Never duplicate code. Extract shared logic to traits, base classes, or helpers.
14. Single Responsibility — one class, one reason to change.
15. Open/Closed — extend, don't modify.
16. Dependency Injection through constructors; never `new ServiceClass()` inside another class.
17. Prefer composition over inheritance for business logic.
18. Write meaningful variable, method, and class names. No `$data`, `$item`, `$tmp`.
19. Add PHPDoc to public methods and JSDoc/TSDoc to exported functions and components.
20. Functions do one thing. If a method exceeds ~40 lines, split it.
21. No magic numbers or magic strings — use Enums or named constants.
22. Use PHP 8.4 features: readonly properties, constructor promotion, enums, match expressions, named arguments where they aid clarity.

## Database

23. UUID primary keys on every business table.
24. Soft deletes on entities that should be archived, not destroyed (students, teachers, parents, classes).
25. Cascade rules per Database Specification — Parent/Class/Teacher deletes **never** cascade to students or grades.
26. Index every foreign key and every column used in `WHERE`, `ORDER BY`, or `JOIN`.
27. Uniqueness enforced at DB level on `admission_number`, `employee_number`, `email`, `receipt_number`.
28. Derived values (averages, rankings, attendance %, outstanding balances) are **never** stored — they're computed in Services.
29. Use database transactions for any operation that writes to more than one table.
30. Eager-load relationships to prevent N+1 queries.

## API

31. All endpoints under `/api/v1`. Future breaking changes go to `/api/v2`.
32. Use the standard response envelope:
    ```json
    { "success": true, "message": "...", "data": {}, "meta": {} }
    { "success": false, "message": "...", "errors": {} }
    ```
33. Use Laravel API Resources to shape response payloads. Never `return $model->toArray()` directly.
34. Use correct HTTP status codes — 200/201/204/400/401/403/404/409/422/429/500.
35. Pagination uses `?page=`, `?per_page=`, returns `meta.{page, per_page, total, last_page}`.
36. Search/sort/filter via consistent query parameters: `?q=`, `?sort=`, `?order=`, `?filter[key]=value`.
37. Never expose stack traces, SQL errors, or internal paths in API responses.

## Frontend

38. TypeScript throughout — no `any` unless documented and justified.
39. Use Next.js **App Router** with React Server Components by default; mark `'use client'` only where interactivity is needed.
40. Tailwind CSS only — no styled-components, no CSS modules, no inline `style=` except for dynamic computed values.
41. Build reusable, composable components. A component does one thing.
42. Components in `src/components/ui/` must be pure (presentational, no data fetching).
43. Components in `src/components/[feature]/` may compose UI components and call hooks/stores.
44. No business logic in components — push it into hooks, stores, or API client functions.
45. Forms use `react-hook-form` + Zod schemas matching the backend Form Request rules.
46. Global state via Zustand stores in `src/store/`. Server state via TanStack Query in `src/lib/api/`.
47. Every interactive element must be keyboard-accessible with visible focus state and ARIA label where icon-only.

## UX Rules (from Design Philosophy)

48. Every page: breadcrumb → title → primary action → filters → search → table → pagination. No deviation.
49. Add/edit are full **pages** (`/students/create`, `/students/{id}/edit`), never modals or drawers.
50. After creating a resource, redirect to its profile page — never back to the list.
51. After editing, stay on the profile page with a success toast.
52. Filters open in a **drawer from the right**, with Apply and Reset.
53. Search debounces at 300ms.
54. Toasts: top-right, 5-second auto-dismiss, fade in.
55. Destructive actions require a confirmation dialog. Permanent deletes require double confirmation.
56. Every module has an empty state with an illustration and a CTA.
57. Every async area shows a skeleton loader — never a blank page.
58. Error messages explain what happened and how to fix it. Never expose technical errors.

## Security

59. Every endpoint authenticated via Sanctum Bearer token (except `/login`, `/forgot-password`, `/reset-password`).
60. Every authenticated endpoint passes through Policy authorization.
61. Password policy: min 12 chars, upper, lower, number, special — enforced via `App\Rules\StrongPassword`.
62. Rate limit: 5 login attempts/min/IP, 3 password resets/hour/email, 100 general requests/min/user.
63. All inputs validated via Form Requests. Never trust frontend validation.
64. File uploads: whitelist MIME types, max size enforced, files renamed to UUIDs, stored via `Storage::` facade.
65. Audit logs are immutable from the application layer.
66. Secrets only in `.env`. Never commit credentials.
67. HTTPS enforced in production. Cookies: Secure, HttpOnly, SameSite.

## Testing

68. Every Service has unit tests covering happy path and edge cases.
69. Every API endpoint has a Feature test covering 200, 401, 403, 422 cases.
70. Repositories have integration tests against a real PostgreSQL test DB.
71. Business-critical workflows (registration, payment, report card generation) have end-to-end tests.
72. PHPUnit + Pest acceptable on backend. Vitest + React Testing Library on frontend.

## Asking for Clarification

73. If a requirement is ambiguous or contradicts another document, **stop and ask** — never guess.
74. The Documentation is the contract. It always overrides preference.
75. Do not invent features. Do not rename modules. Do not simplify what is specified.
