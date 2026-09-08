# Deployment readiness — 2026-08-24

## Validated release gates

- Laravel: 109 tests passed, 529 assertions; one host-only GD skip. The production image installs GD.
- Frontend: ESLint passed with zero warnings.
- Frontend: TypeScript strict check passed.
- Frontend: 11 Vitest tests passed for role routing, API envelopes, and tab-isolated authentication.
- Frontend: optimized Next.js build completed and generated all 67 routes.
- npm locked dependency audit: zero known vulnerabilities.
- Composer manifest/lock validation: passed on the live release.
- Production Compose rendering: passed.
- Production-readiness installer: full dry installation and verification passed.

## Hardening delivered

- Production-specific environment template with HTTPS, secure cookies, public storage, stderr logging, and non-placeholder validation.
- Caddy and Next.js security headers including Content Security Policy.
- Application, PostgreSQL, and Redis health checks.
- Trusted reverse-proxy handling for real client IP and HTTPS detection.
- Correct PostgreSQL and Docker-volume backup/restore procedures with checksums and a mandatory pre-restore safety snapshot.
- Comprehensive secret/environment/backups exclusions in `.gitignore`.
- Automated GitHub quality gates for PHP tests/audit, frontend lint/type/tests/audit/build, and both production Docker images.
- Controlled Windows installer with hash verification and source rollback evidence.

## External launch requirements

Before public launch, the operator must supply the real domain, VPS, SMTP account, and production secrets; create `.env.production`; run `scripts/validate-production.sh`; and obtain a green GitHub CI run. These are environment credentials and infrastructure acceptance steps, not missing application code.
