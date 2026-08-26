#!/usr/bin/env bash
set -Eeuo pipefail

APP_DIR="${1:-/srv/clyvero}"
BACKUP_ROOT="${2:-/srv/backups/clyvero}"
BACKEND_DIR="${APP_DIR}/backend"
ENV_FILE="${BACKEND_DIR}/.env.production"
COMPOSE_FILE="${BACKEND_DIR}/compose.production.yml"
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
DESTINATION="${BACKUP_ROOT}/${STAMP}"

die() {
  echo "Backup failed: $*" >&2
  exit 1
}

read_env() {
  local key="$1"
  sed -n "s/^${key}=//p" "${ENV_FILE}" | tail -1 | tr -d '\r\"'
}

[[ -f "${BACKEND_DIR}/artisan" ]] || die "${APP_DIR} is not a Clyvero release."
[[ -f "${ENV_FILE}" ]] || die "missing ${ENV_FILE}."
[[ -f "${COMPOSE_FILE}" ]] || die "missing ${COMPOSE_FILE}."

DB_NAME="$(read_env DB_DATABASE)"
DB_USER="$(read_env DB_USERNAME)"
[[ "${DB_NAME}" =~ ^[A-Za-z0-9_-]+$ ]] || die "invalid DB_DATABASE."
[[ "${DB_USER}" =~ ^[A-Za-z0-9_-]+$ ]] || die "invalid DB_USERNAME."

mkdir -p "${DESTINATION}"
chmod 700 "${DESTINATION}"
cd "${BACKEND_DIR}"
COMPOSE=(docker compose --env-file .env.production -f compose.production.yml)

"${COMPOSE[@]}" up -d postgres redis >/dev/null
for attempt in {1..30}; do
  if "${COMPOSE[@]}" exec -T postgres pg_isready -U "${DB_USER}" -d "${DB_NAME}" >/dev/null 2>&1; then
    break
  fi
  [[ "${attempt}" -lt 30 ]] || die "PostgreSQL did not become ready."
  sleep 2
done

"${COMPOSE[@]}" exec -T postgres \
  pg_dump -U "${DB_USER}" -d "${DB_NAME}" -Fc > "${DESTINATION}/database.dump"
[[ -s "${DESTINATION}/database.dump" ]] || die "database dump is empty."
"${COMPOSE[@]}" exec -T postgres pg_restore --list - \
  < "${DESTINATION}/database.dump" >/dev/null

# Uploaded school logos, headers, student photos, and generated public assets
# live in the named production volume, not in the host source directory.
"${COMPOSE[@]}" run --rm --no-deps -T --entrypoint tar app \
  -C /var/www/html/storage -czf - app/public \
  > "${DESTINATION}/public-uploads.tar.gz"
[[ -s "${DESTINATION}/public-uploads.tar.gz" ]] || die "uploads archive is empty."
tar -tzf "${DESTINATION}/public-uploads.tar.gz" >/dev/null

(
  cd "${DESTINATION}"
  sha256sum database.dump public-uploads.tar.gz > SHA256SUMS
)

{
  printf 'release=%s\n' "$(git -C "${APP_DIR}" rev-parse HEAD 2>/dev/null || echo unversioned)"
  printf 'database=%s\ncreated_utc=%s\n' "${DB_NAME}" "${STAMP}"
  printf 'environment_file=%s (not copied; store secrets separately)\n' "${ENV_FILE}"
} > "${DESTINATION}/manifest.txt"

echo "Backup completed and verified: ${DESTINATION}"
echo "Copy this directory to encrypted off-site storage."
