#!/usr/bin/env bash
# =============================================================================
# FryNov ERP — Script de déploiement VPS (Ubuntu 22.04 / Debian 12)
# Usage :
#   Première fois : bash deploy.sh --install
#   Mise à jour   : bash deploy.sh
# =============================================================================

set -euo pipefail

# ── Configuration ─────────────────────────────────────────────────────────────
APP_DIR="/var/www/frynov"
BACKEND_DIR="$APP_DIR/backend"
FRONTEND_DIR="$APP_DIR/frontend"
PHP="php8.3"
COMPOSER="composer"
NODE="node"
NPM="npm"
GIT_BRANCH="main"

# Couleurs
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; BLUE='\033[0;34m'; NC='\033[0m'
info()    { echo -e "${BLUE}[INFO]${NC}  $*"; }
success() { echo -e "${GREEN}[OK]${NC}    $*"; }
warn()    { echo -e "${YELLOW}[WARN]${NC}  $*"; }
error()   { echo -e "${RED}[FAIL]${NC}  $*"; exit 1; }

# ── Installation initiale ──────────────────────────────────────────────────────
install_server() {
  info "=== Installation du serveur ==="

  info "Mise à jour des paquets"
  apt-get update -qq && apt-get upgrade -y -qq

  info "Installation PHP 8.3 + extensions"
  apt-get install -y -qq software-properties-common
  add-apt-repository -y ppa:ondrej/php
  apt-get update -qq
  apt-get install -y -qq \
    php8.3 php8.3-cli php8.3-fpm php8.3-mysql php8.3-mbstring \
    php8.3-xml php8.3-curl php8.3-zip php8.3-redis php8.3-gd \
    php8.3-intl php8.3-bcmath php8.3-tokenizer

  info "Installation Nginx + MySQL + Redis"
  apt-get install -y -qq nginx mysql-server redis-server

  info "Installation Composer"
  curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

  info "Installation Node.js 22"
  curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
  apt-get install -y -qq nodejs

  info "Configuration MySQL — innodb_large_prefix"
  cat >> /etc/mysql/mysql.conf.d/mysqld.cnf << 'EOF'

[mysqld]
innodb_large_prefix = 1
innodb_file_format   = Barracuda
innodb_file_per_table = 1
character-set-server = utf8mb4
collation-server     = utf8mb4_unicode_ci
EOF
  systemctl restart mysql

  success "Serveur configuré."
}

# ── Déploiement application ────────────────────────────────────────────────────
deploy() {
  info "=== Déploiement FryNov ERP ==="

  # ── 1. Pull du code ──────────────────────────────────────────────────────────
  info "Git pull $GIT_BRANCH"
  cd "$APP_DIR"
  git fetch --all --quiet
  git checkout "$GIT_BRANCH" --quiet
  git pull origin "$GIT_BRANCH" --quiet
  success "Code mis à jour."

  # ── 2. Backend (Laravel) ──────────────────────────────────────────────────────
  info "--- Backend ---"
  cd "$BACKEND_DIR"

  # Vérification .env
  if [ ! -f ".env" ]; then
    warn ".env absent — copie du template de production"
    cp .env.production.example .env
    error "STOP : configure .env et relance le script."
  fi

  info "Composer install (sans dev)"
  $COMPOSER install --no-dev --optimize-autoloader --no-interaction --quiet

  info "Cache config / routes / vues"
  $PHP artisan config:clear
  $PHP artisan config:cache
  $PHP artisan route:cache
  $PHP artisan view:cache

  info "Migrations"
  $PHP artisan migrate --force

  info "Seeders (idempotents)"
  $PHP artisan db:seed --class=RolesAndPermissionsSeeder --force
  $PHP artisan db:seed --class=PlansSeeder               --force
  $PHP artisan db:seed --class=ErpModulesSeeder           --force
  $PHP artisan db:seed --class=PlanModulesSeeder          --force

  info "Lien storage public"
  $PHP artisan storage:link --force 2>/dev/null || true

  info "Permissions répertoires"
  chown -R www-data:www-data "$BACKEND_DIR/storage" "$BACKEND_DIR/bootstrap/cache"
  chmod -R 775 "$BACKEND_DIR/storage" "$BACKEND_DIR/bootstrap/cache"

  success "Backend déployé."

  # ── 3. Frontend (Vue 3 + Vite) ────────────────────────────────────────────────
  info "--- Frontend ---"
  cd "$FRONTEND_DIR"

  # Vérification .env
  if [ ! -f ".env.production" ]; then
    warn ".env.production absent — copie de l'exemple"
    cp .env.production.example .env.production 2>/dev/null || \
      echo "VITE_API_URL=https://app.frynov.com" > .env.production
  fi

  info "npm ci + build"
  $NPM ci --silent
  $NPM run build

  info "Copie du dist dans /var/www/frynov/public"
  rm -rf "$APP_DIR/public/assets"
  cp -r "$FRONTEND_DIR/dist/." "$APP_DIR/public/"

  success "Frontend déployé."

  # ── 4. Queue worker (si non géré par Supervisor) ────────────────────────────
  if command -v supervisorctl &> /dev/null; then
    info "Redémarrage Supervisor (queue workers)"
    supervisorctl reread   2>/dev/null || true
    supervisorctl update   2>/dev/null || true
    supervisorctl restart frynov-worker:* 2>/dev/null || true
  else
    warn "Supervisor non installé — workers de queue non redémarrés."
    warn "Installe supervisor et crée /etc/supervisor/conf.d/frynov.conf"
  fi

  # ── 5. Cache OPcache ──────────────────────────────────────────────────────────
  if command -v php-fpm8.3 &>/dev/null; then
    info "Reload PHP-FPM"
    systemctl reload php8.3-fpm
  fi

  success "=== Déploiement terminé avec succès ==="
  echo ""
  echo -e "  URL : ${GREEN}$(grep APP_URL $BACKEND_DIR/.env | cut -d= -f2)${NC}"
  echo ""
}

# ── Entrypoint ────────────────────────────────────────────────────────────────
case "${1:-deploy}" in
  --install) install_server; deploy ;;
  *)         deploy ;;
esac
