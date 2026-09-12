#!/usr/bin/env bash
set -Eeuo pipefail

# A single Compose project keeps database/upload volumes across releases.
RELEASE_DIR="${CLYVERO_RELEASE_DIR:-/srv/clyvero/current}"
exec docker compose --project-name clyvero-vps \
  --env-file /srv/clyvero/shared/production.env \
  --env-file "${RELEASE_DIR}/.release.env" \
  -f "${RELEASE_DIR}/compose.vps.yml" "$@"
