#!/usr/bin/env bash
# The Laureates — One-shot bootstrap script
# Run from repo root: bash scripts/setup.sh

set -e

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$REPO_ROOT"

echo "═══════════════════════════════════════════════════════════════"
echo "  The Laureates — Phase 1 Bootstrap"
echo "═══════════════════════════════════════════════════════════════"

# ──────────────────────────────────────────────────────────────
# Preflight: verify required tools
# ──────────────────────────────────────────────────────────────
need() { command -v "$1" >/dev/null 2>&1 || { echo "ERROR: '$1' is required but not installed."; exit 1; }; }
need php
need composer
need node
need npm
need docker

PHP_VERSION=$(php -r 'echo PHP_VERSION;')
NODE_VERSION=$(node -v)
echo "PHP:    $PHP_VERSION (require 8.4+)"
echo "Node:   $NODE_VERSION (require 20+)"
echo ""

# ──────────────────────────────────────────────────────────────
# Backend scaffold
# ──────────────────────────────────────────────────────────────
echo "→ Step 1/4: Installing Laravel 12 skeleton..."

if [ ! -f "backend/artisan" ]; then
  # Move custom files aside, install Laravel, restore custom files
  mv backend backend.custom
  composer create-project laravel/laravel backend "^12.0" --no-interaction --prefer-dist
  # Overlay our custom files
  cp -rn backend.custom/* backend/ 2>/dev/null || true
  cp -rn backend.custom/.[!.]* backend/ 2>/dev/null || true
  rm -rf backend.custom
else
  echo "  backend/artisan already exists — skipping Laravel install"
fi

cd backend

# Install runtime dependencies
echo "  Installing Sanctum, Sail, Redis, DomPDF, Intervention/Image..."
composer require \
  laravel/sanctum \
  laravel/sail --dev \
  predis/predis \
  barryvdh/laravel-dompdf \
  intervention/image-laravel \
  --no-interaction

# Publish Sanctum & Sail
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider" --tag=sanctum-migrations --force
php artisan sail:install --with=pgsql,redis,mailpit --no-interaction || true

# Copy our .env if user hasn't already
if [ ! -f .env ]; then
  cp .env.example .env
fi

# Generate app key
php artisan key:generate --ansi

cd "$REPO_ROOT"
echo "  ✓ Backend ready"
echo ""

# ──────────────────────────────────────────────────────────────
# Frontend scaffold
# ──────────────────────────────────────────────────────────────
echo "→ Step 2/4: Installing Next.js 15 skeleton..."

if [ ! -f "frontend/package.json" ] || ! grep -q "\"next\"" frontend/package.json 2>/dev/null; then
  mv frontend frontend.custom
  npx --yes create-next-app@latest frontend \
    --typescript \
    --tailwind \
    --app \
    --src-dir \
    --eslint \
    --import-alias "@/*" \
    --no-turbopack \
    --use-npm
  cp -rn frontend.custom/* frontend/ 2>/dev/null || true
  cp -rn frontend.custom/.[!.]* frontend/ 2>/dev/null || true
  rm -rf frontend.custom
else
  echo "  frontend/package.json already exists — skipping Next.js install"
fi

cd frontend
echo "  Installing axios, zustand, react-hook-form, zod, @tanstack/react-query, lucide-react, sonner..."
npm install \
  axios \
  zustand \
  react-hook-form \
  zod \
  @hookform/resolvers \
  @tanstack/react-query \
  lucide-react \
  sonner \
  clsx \
  tailwind-merge

if [ ! -f .env.local ]; then
  cp .env.local.example .env.local
fi

cd "$REPO_ROOT"
echo "  ✓ Frontend ready"
echo ""

# ──────────────────────────────────────────────────────────────
# Boot Sail + migrate
# ──────────────────────────────────────────────────────────────
echo "→ Step 3/4: Bringing up Docker services (Postgres, Redis, Mailpit)..."
cd backend
./vendor/bin/sail up -d
sleep 5
echo "  Running migrations + seeders..."
./vendor/bin/sail artisan migrate:fresh --seed
cd "$REPO_ROOT"
echo "  ✓ Database ready"
echo ""

# ──────────────────────────────────────────────────────────────
# Summary
# ──────────────────────────────────────────────────────────────
echo "→ Step 4/4: Done."
echo ""
echo "═══════════════════════════════════════════════════════════════"
echo "  Phase 1 setup complete."
echo "═══════════════════════════════════════════════════════════════"
echo ""
echo "  Backend API:  http://localhost/api/v1"
echo "  Mailpit:      http://localhost:8025"
echo ""
echo "  Start the frontend:"
echo "    cd frontend && npm run dev"
echo ""
echo "  Default administrator:"
echo "    Email:    admin@the-laureates.test"
echo "    Password: TheLaureates2026!"
echo ""
echo "  Change this password immediately after first login."
echo ""
