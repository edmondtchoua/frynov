#!/usr/bin/env bash
# =============================================================================
# Frynov ERP — Déploiement A2 Hosting (cPanel / LiteSpeed, sans root)
#
# Architecture :
#   api.frynov.com -> <repo>/backend/public   (API Laravel)
#   frynov.com     -> <repo>/frontend/dist    (SPA Vue statique)
#
# Usage (depuis la racine du repo, en SSH) :
#   bash deploy-a2.sh                 # mise à jour (pull + back + front)
#   bash deploy-a2.sh --seed          # + (re)joue les seeders de référence + backfill modules
#   bash deploy-a2.sh --backend-only  # n'exécute que la partie backend
#   bash deploy-a2.sh --frontend-only # n'exécute que le build frontend
#
# Variables surchargeables :
#   PHP_BIN, NPM_BIN, API_URL, GIT_BRANCH, NODE_ACTIVATE
# Exemple : PHP_BIN=ea-php83 API_URL=https://api.frynov.com bash deploy-a2.sh --seed
# =============================================================================

set -euo pipefail

# ── Configuration ─────────────────────────────────────────────────────────────
REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BACKEND_DIR="$REPO_DIR/backend"
FRONTEND_DIR="$REPO_DIR/frontend"

PHP_BIN="${PHP_BIN:-php}"                 # ex: ea-php83 ou /opt/cpanel/ea-php83/root/usr/bin/php
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
NPM_BIN="${NPM_BIN:-npm}"
API_URL="${API_URL:-https://api.frynov.com}"
GIT_BRANCH="${GIT_BRANCH:-release/v1.0.0}"
# CloudLinux Node Selector : commande "activate" du virtualenv Node (optionnel).
# ex: NODE_ACTIVATE="$HOME/nodevenv/frynov.com/frynov/frontend/20/bin/activate"
NODE_ACTIVATE="${NODE_ACTIVATE:-}"

RUN_SEED=false; RUN_BACKEND=true; RUN_FRONTEND=true
for arg in "$@"; do
  case "$arg" in
    --seed)          RUN_SEED=true ;;
    --backend-only)  RUN_FRONTEND=false ;;
    --frontend-only) RUN_BACKEND=false ;;
    *) echo "Option inconnue : $arg" ; exit 1 ;;
  esac
done

# ── Couleurs / helpers ────────────────────────────────────────────────────────
GREEN='\033[0;32m'; BLUE='\033[0;34m'; YELLOW='\033[1;33m'; RED='\033[0;31m'; NC='\033[0m'
info()    { echo -e "${BLUE}[INFO]${NC}  $*"; }
step()    { echo -e "\n${BLUE}=== $* ===${NC}"; }
success() { echo -e "${GREEN}[OK]${NC}    $*"; }
warn()    { echo -e "${YELLOW}[WARN]${NC}  $*"; }
error()   { echo -e "${RED}[FAIL]${NC}  $*"; exit 1; }

command -v git >/dev/null || error "git introuvable"

# ── 1. Récupération du code ───────────────────────────────────────────────────
step "Récupération du code ($GIT_BRANCH)"
cd "$REPO_DIR"
git fetch origin --quiet
git checkout "$GIT_BRANCH"
git pull --ff-only origin "$GIT_BRANCH"
success "Code à jour : $(git rev-parse --short HEAD)"

# ── 2. Backend (Laravel) ──────────────────────────────────────────────────────
if $RUN_BACKEND; then
  step "Backend — dépendances & migrations"
  cd "$BACKEND_DIR"
  command -v "$PHP_BIN" >/dev/null || error "PHP introuvable ($PHP_BIN). Renseigne PHP_BIN=ea-php83."
  [ -f .env ] || error ".env manquant dans backend/ — crée-le avant le 1er déploiement."

  "$COMPOSER_BIN" install --no-dev --optimize-autoloader --no-interaction

  # Mode maintenance le temps de migrer / recacher (best-effort)
  "$PHP_BIN" artisan down --render="errors::503" >/dev/null 2>&1 || true
  trap '"$PHP_BIN" artisan up >/dev/null 2>&1 || true' EXIT

  "$PHP_BIN" artisan migrate --force

  if $RUN_SEED; then
    step "Backend — seeders de référence (idempotents) + backfill modules"
    for s in RolesAndPermissionsSeeder ErpModulesSeeder PlansSeeder MarketPaymentMethodsSeeder \
             PlanModulesSeeder CountryRulesSeeder AccountingClassesSeeder SuperAdminSeeder; do
      "$PHP_BIN" artisan db:seed --class="$s" --force
    done
    # ⚠️ On ne lance JAMAIS DemoSeeder en prod (garde-fou actif de toute façon).
    # Active les modules manquants pour les tenants existants (fail-closed).
    "$PHP_BIN" artisan tenants:backfill-modules
  fi

  [ -L public/storage ] || "$PHP_BIN" artisan storage:link || true

  step "Backend — caches de prod"
  "$PHP_BIN" artisan config:cache
  "$PHP_BIN" artisan route:cache
  "$PHP_BIN" artisan event:cache

  "$PHP_BIN" artisan up >/dev/null 2>&1 || true
  trap - EXIT
  success "Backend déployé"
fi

# ── 3. Frontend (SPA Vue) ─────────────────────────────────────────────────────
if $RUN_FRONTEND; then
  step "Frontend — build ($API_URL)"
  cd "$FRONTEND_DIR"
  # Active l'environnement Node CloudLinux si fourni
  if [ -n "$NODE_ACTIVATE" ] && [ -f "$NODE_ACTIVATE" ]; then
    # shellcheck disable=SC1090
    source "$NODE_ACTIVATE"
  fi
  command -v "$NPM_BIN" >/dev/null || error "npm introuvable. Active le Node virtualenv (NODE_ACTIVATE) ou renseigne NPM_BIN."

  # Le repo a un conflit de peer-deps (eslint) → --legacy-peer-deps
  "$NPM_BIN" ci --legacy-peer-deps --no-audit --no-fund \
    || "$NPM_BIN" install --legacy-peer-deps --no-audit --no-fund

  # Build (typecheck + vite). Si OOM sur mutualisé, retombe sur vite seul.
  if ! VITE_API_BASE_URL="$API_URL" "$NPM_BIN" run build; then
    warn "Build complet KO (mémoire ?) — nouvelle tentative sans typecheck (npx vite build)"
    VITE_API_BASE_URL="$API_URL" npx vite build
  fi
  success "Frontend buildé → frontend/dist"
fi

echo
success "Déploiement terminé. Pense à : AutoSSL (cPanel) + Force HTTPS + cron 'schedule:run'."
