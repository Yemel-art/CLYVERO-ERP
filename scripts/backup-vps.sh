#!/usr/bin/env bash
set -Eeuo pipefail
umask 077

# Caller must hold /srv/clyvero/.deploy.lock (see the deployment script/runbook).
RELEASE_DIR="${CLYVERO_RELEASE_DIR:-/srv/clyvero/current}"
COMPOSE=(bash "${RELEASE_DIR}/scripts/vps-compose.sh")
DESTINATION="/srv/clyvero/backups/$(date -u +%Y%m%dT%H%M%S)-${RANDOM}"
mkdir -p "${DESTINATION}"

# PostgreSQL variables expand inside the container.
# shellcheck disable=SC2016
"${COMPOSE[@]}" exec -T postgres sh -c \
  'exec pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" -Fc' > "${DESTINATION}/database.dump"
[[ -s "${DESTINATION}/database.dump" ]]
"${COMPOSE[@]}" exec -T postgres pg_restore --list < "${DESTINATION}/database.dump" >/dev/null

"${COMPOSE[@]}" run --rm --no-deps -T --entrypoint tar app \
  -C /var/www/html/storage -czf - app > "${DESTINATION}/uploads.tar.gz"
tar -tzf "${DESTINATION}/uploads.tar.gz" >/dev/null
(cd "${DESTINATION}" && sha256sum database.dump uploads.tar.gz > SHA256SUMS)
cp "${RELEASE_DIR}/.release.env" "${DESTINATION}/release.env"
printf 'Backup verified: %s\n' "${DESTINATION}"
