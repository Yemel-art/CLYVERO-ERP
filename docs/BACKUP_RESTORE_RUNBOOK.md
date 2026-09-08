# Backup and recovery runbook

## What must be backed up

A recoverable Clyvero ERP backup has three parts:

1. PostgreSQL custom-format dump (`database.dump`).
2. Laravel uploaded files (`backend/storage/app`).
3. The exact application release and encrypted environment/secrets backup.

Database-only backups do not recover student photos, logos, imports, or generated files. File-only backups do not recover enrollments, grades, finance, or users.

## Schedule and retention

- Database: nightly, plus immediately before every deployment/migration.
- Uploaded files: nightly incremental or daily archive.
- Retain 7 daily, 4 weekly, and 12 monthly recovery points.
- Keep at least one encrypted copy off the VPS in a different provider/location.
- Copy backup checksums with the archives.
- Run a restore rehearsal into a disposable database at least quarterly.

## Backup verification

Every backup job must fail if any required artifact is missing or empty. Verify:

```bash
pg_restore --list database.dump >/dev/null
sha256sum -c SHA256SUMS
tar -tzf storage-app.tar.gz >/dev/null
```

Also record database name, PostgreSQL version, application release, timestamp, and migration status.

## Safe restore procedure

Restoring over production is destructive and must never be automatic.

1. Confirm the exact school impact, target database, backup timestamp, and reason.
2. Announce maintenance and stop writes with `php artisan down`.
3. Take a new pre-restore safety backup.
4. Verify the selected dump with `pg_restore --list` and its checksum.
5. Prefer restoring into a new disposable database first.
6. Validate tenant counts, administrator login, enrollments, finance totals, and report cards.
7. Only then restore/switch production under an explicit database-name confirmation.
8. Restore uploaded files from the matching timestamp; do not mix unrelated snapshots.
9. Run `php artisan optimize:clear`, confirm migrations, restart workers, and run smoke tests.
10. Exit maintenance with `php artisan up` and monitor errors.

Custom-format PostgreSQL archives are restored with `pg_restore`. Do not use a dump from an untrusted server because restoring a dump executes database definitions contained in that source.

## Disaster priorities

1. Preserve the damaged server/volume; do not repeatedly mutate it.
2. Restore database and storage into a clean environment.
3. Configure secrets from the protected secret store, not from source control.
4. Validate tenant isolation and totals before reopening access.
5. Document the incident, cause, recovery point, and data gap.

## Local Windows development note

The local Docker setup uses `docker-compose.override.yml` and the named volume `clyvero-local-vendor` to avoid reading thousands of Composer files from D: on each request. This volume contains dependencies only; it is disposable and is not a data backup. The real PostgreSQL and Redis volumes must never be removed as a troubleshooting shortcut.
