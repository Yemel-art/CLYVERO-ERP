# CLYVERO ERP — Production Hardening Audit Report

## Scope and safety

This release extends the existing Laravel 12 and Next.js application. It does not recreate the product, reset the database, fabricate school records, or claim an unsupported Cartes Scolaire API. Database changes are delivered through additive migrations and the installation procedure creates and verifies a PostgreSQL backup before source deployment or migration.

## A. Important findings

- School ownership was not consistently enforced across every school-owned relationship, leaving tenant-isolation and IDOR risks.
- Authentication needed an explicit, server-authorized school context and tenant-bound password-reset records.
- Academic-year handling contained school-specific and year-specific assumptions and lacked a safe transactional rollover path.
- Promotion decisions, progression rules, branding, subjects, and fees needed to be configurable per school.
- Several finance totals and destructive operations required stronger server-side calculation, preservation, and audit safeguards.
- Grade and attendance entry performed repeated row-by-row database writes that would degrade as schools grew.
- Document access, uploads, localization, deployment, backups, and operational guidance needed production hardening.
- No accessible official Cartes Scolaire API was established; implementing credential scraping or an invented API would be unsafe.

## B. Major modifications

- Added platform-administrator school provisioning, unique school codes, tenant activation/deactivation, and secure tenant context.
- Added backend tenant scoping, authorization policies, database cross-tenant guards, and tenant-isolation tests.
- Added configurable school branding, education systems, secondary logos, document headers/footers, and principal information.
- Added configurable promotion policies, persisted academic decisions, and transactional school-year rollover.
- Preserved student identity and history while creating new yearly enrollments for promoted/repeating students.
- Added a supported CSV/XLSX Cartes Scolaire import workflow with preview, mapping, validation, duplicate protection, confirmation, and import history.
- Added guarded permanent deletion for test students while preventing deletion that would corrupt financial history.
- Corrected finance summaries to prioritize money actually collected, strengthened invoice/payment logic, preserved voided records, and localized receipts.
- Added teacher assignment and teacher-grade authorization checks and corrected teacher class visibility.
- Replaced row-by-row grade and attendance writes with bulk upserts and optimized dashboard queries.
- Added signed private-media access, stricter upload validation, security headers, rate limiting, token revocation, and tenant-bound password resets.
- Expanded English/French localization across frontend, backend validation, notifications, and generated documents.
- Added production deployment, backup/restore, rollback, and release-checklist documentation.

## C. Database changes

The release adds additive migrations for:

- multi-school platform hardening and school configuration;
- cross-tenant PostgreSQL integrity triggers;
- official-student import batches and rows;
- tenant-bound password-reset challenges.

The migration rehearsal was run on a clone of the live database. Counts remained unchanged at one school, five users, two students, two enrollments, and two payments. The clone had no duplicate enrollments or null school codes, and 20 tenant-integrity triggers were installed.

## D. Multi-school architecture

Each school has a unique school code. Login resolves the school server-side, authenticates the user inside that tenant, and returns only the roles and permissions belonging to that school. Normal administrators, teachers, parents, students, and secretaries cannot switch tenant by editing browser state or an object ID. Backend policies/scopes enforce ownership, and PostgreSQL relationship guards reject cross-school links. Platform administrators provision and manage schools through platform-only routes; deactivating a school revokes its active tokens.

## E. Academic-year rollover

Only one academic year may be active for a school. Before rollover, the school configures promotion policy and class progression and completes/publishes final marks. The promotion service persists each decision as promoted, repeating, or excluded. Rollover executes in a transaction: promoted students receive an enrollment in the configured next class, repeating students receive an enrollment in the same class, and excluded students are not automatically enrolled. The student record, parent links, admission identity, files, and prior academic/financial history remain unchanged and browsable.

The operational-year initialization command can explicitly purge authorized test students, remove the unused `2025-2026` academic dataset, and activate `2026-2027` without pretending that a real promotion rollover occurred. The system architecture itself is no longer hardcoded to one year.

## F. Cartes Scolaire

No accessible, documented official API was confirmed. Therefore this release does not claim direct API integration and does not scrape Ministry credentials. It implements the safe mechanism available to schools: import of an official CSV/XLSX export with field mapping, preview, validation, duplicate/idempotency protection, conflict reporting, confirmation, transaction safety, and import history. The adapter boundary allows a documented official API to be added later if the Ministry supplies access and specifications.

## G. Verification completed

- Backend: **99 tests passed**, **390 assertions**, **1 skipped**, **0 failures**.
- The one skipped host test requires the GD image extension; the Docker PHP runtime contains image processing support.
- Frontend TypeScript check: passed.
- Next.js production build: passed; 62 of 62 pages generated.
- Fresh migrations on a disposable PostgreSQL database: passed.
- Upgrade rehearsal on a clone of the live database: passed without record-count changes.
- PHP syntax checks: passed.
- PowerShell installer and rollback parser checks: passed.
- Linux backup and restore script syntax checks: passed.
- Laravel health endpoint and frontend login page returned HTTP 200 during local smoke testing.

## H. Remaining operational actions and limitations

- Run the backup-first installer from an Administrator PowerShell window because the project directory is protected from the Codex sandbox.
- Configure or rotate the real platform-administrator account using `php artisan clyvero:platform-admin`; credentials are intentionally not hardcoded or generated silently.
- Configure each school's promotion policy, class progression, active academic year, mail, storage, queue worker, scheduler, SSL, monitoring, and backup retention before production use.
- Perform a final human acceptance test with representative administrator, teacher, secretary, parent, and student accounts.
- Run and document a restore drill on the actual production VPS after deployment; local restore tooling has been syntax-checked and the database-upgrade rehearsal used a disposable clone.
- Direct Cartes Scolaire API synchronization remains unavailable until an official documented integration and authorized credentials are supplied.
- No software can be guaranteed crash-proof; continued monitoring, tested backups, patching, and periodic regression testing remain necessary.
