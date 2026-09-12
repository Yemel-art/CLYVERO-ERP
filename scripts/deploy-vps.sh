#!/usr/bin/env bash
set -Eeuo pipefail
umask 077

BASE=/srv/clyvero
SHA="${1:-}"
[[ "${SHA}" =~ ^[0-9a-f]{40}$ ]] || { echo 'Expected a full Git commit SHA.' >&2; exit 1; }
RELEASE_DIR="${BASE}/releases/${SHA}"
ENV_FILE="${BASE}/shared/production.env"
[[ -f "${ENV_FILE}" && -f "${RELEASE_DIR}/.release.env" ]]
[[ "$(readlink -f "$(dirname "$0")/..")" == "${RELEASE_DIR}" ]]
export CLYVERO_RELEASE_DIR="${RELEASE_DIR}"
COMPOSE=(bash "${RELEASE_DIR}/scripts/vps-compose.sh")
read_env() { sed -n "s/^$1=//p" "${2:-${ENV_FILE}}" | tail -1 | tr -d '\r\"'; }

exec 9>"${BASE}/.deploy.lock"
flock -w 600 9
trap 'echo "Deployment failed at line ${LINENO}. Inspect the logs and recovery steps in docs/UBUNTU_VPS_GITHUB_ACTIONS.md. No database rollback was attempted." >&2' ERR

[[ "$(read_env IMAGE_TAG "${RELEASE_DIR}/.release.env")" == "${SHA}" ]]
[[ "$(read_env BUILT_API_DOMAIN "${RELEASE_DIR}/.release.env")" == "$(read_env API_DOMAIN)" ]] || {
  echo 'GitHub API_DOMAIN must match the VPS API_DOMAIN; rebuild after correcting it.' >&2; exit 1;
}
[[ "$(read_env APP_ENV)" == production && "$(read_env APP_DEBUG)" == false ]]
[[ "$(read_env APP_URL)" == "https://$(read_env API_DOMAIN)" ]]
[[ "$(read_env FRONTEND_URL)" == "https://$(read_env APP_DOMAIN)" ]]
[[ "$(read_env CORS_ALLOWED_ORIGINS)" == "https://$(read_env APP_DOMAIN)" ]]
[[ "$(read_env SESSION_SECURE_COOKIE)" == true ]]
[[ "$(read_env DB_PASSWORD)" =~ ^[0-9a-f]{64}$ ]]
[[ "$(read_env REDIS_PASSWORD)" =~ ^[0-9a-f]{64}$ ]]
[[ "$(read_env APP_KEY)" =~ ^base64:[A-Za-z0-9+/]{43}=$ ]]
[[ "$(read_env REDIS_QUEUE_RETRY_AFTER)" =~ ^[0-9]+$ ]]
(( $(read_env REDIS_QUEUE_RETRY_AFTER) > 120 ))
if grep -Eq 'REPLACE_|yourdomain\.com|example\.com' "${ENV_FILE}"; then
  echo 'Replace all production.env placeholders before deployment.' >&2; exit 1
fi
"${COMPOSE[@]}" config --quiet

# Loading and pulling happen before taking the existing site offline.
gzip -dc "${BASE}/incoming/${SHA}/images.tar.gz" | docker load
docker image inspect "clyvero-backend:${SHA}" "clyvero-frontend:${SHA}" >/dev/null
# Routine releases retain infrastructure versions already present on the VPS.
"${COMPOSE[@]}" pull --policy missing postgres redis caddy
"${COMPOSE[@]}" run --rm --no-deps -T --entrypoint caddy caddy validate --config /etc/caddy/Caddyfile --adapter caddyfile
"${COMPOSE[@]}" up -d --wait --wait-timeout 180 postgres redis

PREVIOUS="$(readlink -f "${BASE}/current" || true)"
# Also stop containers left by a first deployment that failed its HTTPS checks.
"${COMPOSE[@]}" stop caddy frontend scheduler queue app
if [[ -n "${PREVIOUS}" && -f "${PREVIOUS}/.release.env" ]]; then
  CLYVERO_RELEASE_DIR="${PREVIOUS}" bash "${RELEASE_DIR}/scripts/backup-vps.sh"
else
  bash "${RELEASE_DIR}/scripts/backup-vps.sh"
fi

# Run once, before web/worker processes start. Never migrate:fresh or demo-seed.
"${COMPOSE[@]}" run --rm --no-deps -T -e AUTORUN_ENABLED=false app php artisan migrate --force --no-interaction
if [[ ! -f "${BASE}/shared/roles-initialized" ]]; then
  "${COMPOSE[@]}" run --rm --no-deps -T -e AUTORUN_ENABLED=false app php artisan db:seed --class=RoleSeeder --force --no-interaction
  "${COMPOSE[@]}" run --rm --no-deps -T -e AUTORUN_ENABLED=false app php artisan db:seed --class=PermissionSeeder --force --no-interaction
  touch "${BASE}/shared/roles-initialized"
fi

"${COMPOSE[@]}" up -d --wait --wait-timeout 240 app frontend
"${COMPOSE[@]}" up -d queue scheduler caddy
sleep 5
for service in queue scheduler caddy; do
  container_id="$("${COMPOSE[@]}" ps -q "$service")"
  [[ -n "$container_id" && "$(docker inspect --format '{{.State.Running}}' "$container_id")" == true ]]
done
# Laravel /up alone is a liveness check; also check DB/Redis through Laravel.
# PHP code must be passed literally to the container.
# shellcheck disable=SC2016
"${COMPOSE[@]}" exec -T app php -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap(); \Illuminate\Support\Facades\DB::select("SELECT 1"); \Illuminate\Support\Facades\Cache::put("deployment-check", "ok", 60); if (\Illuminate\Support\Facades\Cache::get("deployment-check") !== "ok") { throw new \RuntimeException("Cache check failed"); }'
curl --fail --silent --show-error --retry 18 --retry-delay 5 --retry-all-errors --connect-timeout 5 --max-time 15 "https://$(read_env API_DOMAIN)/up" >/dev/null
curl --fail --silent --show-error --retry 18 --retry-delay 5 --retry-all-errors --connect-timeout 5 --max-time 15 "https://$(read_env APP_DOMAIN)/login" >/dev/null

# Record success only after public HTTPS and dependency checks pass.
if [[ -n "${PREVIOUS}" && "${PREVIOUS}" != "${RELEASE_DIR}" && -f "${PREVIOUS}/.release.env" ]]; then
  ln -sfn "${PREVIOUS}" "${BASE}/previous"
fi
ln -sfn "${RELEASE_DIR}" "${BASE}/current-next"
mv -Tf "${BASE}/current-next" "${BASE}/current"
rm -f "${BASE}/incoming/${SHA}/images.tar.gz" "${BASE}/incoming/${SHA}/release.tar.gz"
"${COMPOSE[@]}" ps
echo "Deployment successful: ${SHA}"
