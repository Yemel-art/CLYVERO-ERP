#!/usr/bin/env bash
set -Eeuo pipefail

APP_DIR="${1:-/srv/clyvero}"
BACKEND_DIR="${APP_DIR}/backend"
FRONTEND_DIR="${APP_DIR}/frontend"
ENV_FILE="${BACKEND_DIR}/.env.production"

failures=0
fail() { echo "[FAIL] $*" >&2; failures=$((failures + 1)); }
pass() { echo "[PASS] $*"; }
read_env() { sed -n "s/^$1=//p" "${ENV_FILE}" | tail -1 | tr -d '\r\"'; }

[[ -f "${ENV_FILE}" ]] || { echo "Missing ${ENV_FILE}" >&2; exit 1; }
[[ -f "${BACKEND_DIR}/compose.production.yml" ]] || fail "production Compose file is missing"
[[ -f "${BACKEND_DIR}/Dockerfile.production" ]] || fail "backend production Dockerfile is missing"
[[ -f "${FRONTEND_DIR}/Dockerfile.production" ]] || fail "frontend production Dockerfile is missing"

[[ "$(read_env APP_ENV)" == "production" ]] && pass "APP_ENV is production" || fail "APP_ENV must be production"
[[ "$(read_env APP_DEBUG)" == "false" ]] && pass "APP_DEBUG is disabled" || fail "APP_DEBUG must be false"
[[ "$(read_env APP_URL)" =~ ^https:// ]] && pass "APP_URL uses HTTPS" || fail "APP_URL must use HTTPS"
[[ "$(read_env FRONTEND_URL)" =~ ^https:// ]] && pass "FRONTEND_URL uses HTTPS" || fail "FRONTEND_URL must use HTTPS"
[[ "$(read_env SESSION_SECURE_COOKIE)" == "true" ]] && pass "secure session cookie enabled" || fail "SESSION_SECURE_COOKIE must be true"
[[ "$(read_env FILESYSTEM_DISK)" == "public" ]] && pass "public uploads disk configured" || fail "FILESYSTEM_DISK must be public"
[[ "$(read_env LOG_CHANNEL)" == "stderr" ]] && pass "container logging uses stderr" || fail "LOG_CHANNEL must be stderr"

APP_KEY="$(read_env APP_KEY)"
DB_PASSWORD="$(read_env DB_PASSWORD)"
REDIS_PASSWORD="$(read_env REDIS_PASSWORD)"
[[ "${APP_KEY}" == base64:* && ${#APP_KEY} -ge 50 ]] && pass "APP_KEY is populated" || fail "generate APP_KEY with php artisan key:generate --show"
[[ ${#DB_PASSWORD} -ge 32 && "${DB_PASSWORD}" != REPLACE_* ]] && pass "database password length" || fail "DB_PASSWORD needs at least 32 random characters"
[[ ${#REDIS_PASSWORD} -ge 32 && "${REDIS_PASSWORD}" != REPLACE_* ]] && pass "Redis password length" || fail "REDIS_PASSWORD needs at least 32 random characters"

if grep -Eq 'example\.com|REPLACE_WITH|CHANGE_ME' "${ENV_FILE}"; then
  fail "environment still contains example placeholders"
else
  pass "no example placeholders remain"
fi

cd "${BACKEND_DIR}"
if docker compose --env-file .env.production -f compose.production.yml config --quiet; then
  pass "production Compose configuration is valid"
else
  fail "production Compose configuration is invalid"
fi

if [[ "${failures}" -gt 0 ]]; then
  echo "Production validation failed with ${failures} issue(s)." >&2
  exit 1
fi

echo "Static production validation passed. Continue with build and staging smoke tests."
