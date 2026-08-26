# Release checklist

## Before deployment

- [ ] Backup database, uploaded files, `.env`, and overwritten source files.
- [ ] Verify backup checksum and `pg_restore --list`.
- [ ] Confirm `APP_ENV=production`, `APP_DEBUG=false`, HTTPS URLs, Redis, mail, and strong secrets.
- [ ] Confirm only roles/permissions seeders will run.
- [ ] Run backend tests against a disposable database, never production.
- [ ] Run `npm run type-check` and `npm run build`.
- [ ] Review pending migrations and rollback limitations.

## After deployment

- [ ] `php artisan migrate:status` shows all migrations ran.
- [ ] `php artisan optimize` succeeds.
- [ ] Laravel `/up` is HTTP 200.
- [ ] Administrator login sends and accepts one OTP.
- [ ] A normal user cannot enter another school's code/workspace.
- [ ] Platform administrator can provision and deactivate a test tenant.
- [ ] Student photo uses a signed private URL.
- [ ] Grade sheet saves a complete class/subject batch.
- [ ] Attendance rejects changes after closure.
- [ ] Finance dashboard equals invoice/payment details.
- [ ] English and French report cards render with school branding.
- [ ] Receipt displays amount paid and remaining balance.
- [ ] Queue worker and scheduler are running.
- [ ] Backup job reports success and off-site copy exists.

## Academic-year gate

Do not activate the next year until each active school has:

- a promotion policy;
- complete, published final marks;
- reviewed academic decisions;
- verified class progression mappings;
- a successful database backup.
