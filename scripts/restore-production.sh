#!/usr/bin/env bash
set -Eeuo pipefail

if [[ $# -ne 4 || "$3" != "--confirm-db" ]]; then
  echo "Usage: $0 APP_DIR BACKUP_DIR --confirm-db EXACT_DATABASE_NAME" >&2
  exit 1
fi

APP_DIR="$1"
BACKUP_DIR="$2"
CONFIRMED_DB="$4"
BACKEND_DIR="${APP_DIR}/backend"
ENV_FILE="${BACKEND_DIR}/.env.production"
COMPOSE_FILE="${BACKEND_DIR}/compose.production.yml"

die() {
  echo "Restore failed: $*" >&2
  exit 1
}

read_env() {
  local key="$1"
  sed -n "s/^${key}=//p" "${ENV_FILE}" | tail -1 | tr -d '\r\"'
}

[[ -f "${BACKEND_DIR}/artisan" ]] || die "invalid application directory."
[[ -f "${ENV_FILE}" ]] || die "missing ${ENV_FILE}."
[[ -f "${COMPOSE_FILE}" ]] || die "missing production Compose file."
[[ -s "${BACKUP_DIR}/database.dump" ]] || die "missing database.dump."
[[ -s "${BACKUP_DIR}/public-uploads.tar.gz" ]] || die "missing public-uploads.tar.gz."
[[ -f "${BACKUP_DIR}/SHA256SUMS" ]] || die "missing SHA256SUMS."

(cd "${BACKUP_DIR}" && sha256sum -c SHA256SUMS)
tar -tzf "${BACKUP_DIR}/public-uploads.tar.gz" >/dev/null

DB_NAME="$(read_env DB_DATABASE)"
DB_USER="$(read_env DB_USERNAME)"
[[ "${DB_NAME}" =~ ^[A-Za-z0-9_-]+$ ]] || die "invalid DB_DATABASE."
[[ "${DB_USER}" =~ ^[A-Za-z0-9_-]+$ ]] || die "invalid DB_USERNAME."
[[ "${CONFIRMED_DB}" == "${DB_NAME}" ]] || \
  die "confirmation does not match DB_DATABASE=${DB_NAME}."

echo "This replaces database '${DB_NAME}' and the complete public-upload volume."
read -r -p "Type RESTORE ${DB_NAME} to continue: " ANSWER
[[ "${ANSWER}" == "RESTORE ${DB_NAME}" ]] || die "cancelled."

# A mandatory safety snapshot is stored beside, not inside, the source backup.
SAFETY_ROOT="$(dirname "${BACKUP_DIR}")/pre-restore-safety"
"${APP_DIR}/scripts/backup-production.sh" "${APP_DIR}" "${SAFETY_ROOT}"

cd "${BACKEND_DIR}"
COMPOSE=(docker compose --env-file .env.production -f compose.production.yml)
"${COMPOSE[@]}" stop app queue scheduler

"${COMPOSE[@]}" exec -T postgres psql -v ON_ERROR_STOP=1 -U "${DB_USER}" -d postgres \
  -c "SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname='${DB_NAME}' AND pid <> pg_backend_pid();" \
  -c "DROP DATABASE IF EXISTS \"${DB_NAME}\";" \
  -c "CREATE DATABASE \"${DB_NAME}\" OWNER \"${DB_USER}\" TEMPLATE template0;"

"${COMPOSE[@]}" exec -T postgres pg_restore --list - \
  < "${BACKUP_DIR}/database.dump" >/dev/null
"${COMPOSE[@]}" exec -T postgres pg_restore \
  --exit-on-error --no-owner --no-privileges \
  -U "${DB_USER}" -d "${DB_NAME}" - \
  < "${BACKUP_DIR}/database.dump"

# The explicit confirmed restore permits clearing only the named upload volume.
"${COMPOSE[@]}" run --rm --no-deps -T --user root --entrypoint sh app -lc \
  'find /var/www/html/storage/app/public -mindepth 1 -delete && tar -C /var/www/html/storage -xzf -' \
  < "${BACKUP_DIR}/public-uploads.tar.gz"

"${COMPOSE[@]}" up -d app
"${COMPOSE[@]}" exec -T app php artisan optimize:clear
"${COMPOSE[@]}" exec -T app php artisan migrate:status --no-ansi
"${COMPOSE[@]}" up -d queue scheduler caddy

echo "Restore completed. Run the release smoke tests before reopening user access."
