# Déploiement sur VPS (ex. A2 Hosting) — Frynov ERP v1.0.0

> Cible : un **VPS Linux** (Ubuntu 22.04 LTS conseillé) type A2 Hosting « Unmanaged/Managed VPS ».
> Architecture retenue : **frontend statique découplé** (SPA Vue buildée) + **API Laravel** —
> idéale pour la v1 **et** prête pour la v2 multi-clusters par pays (voir `docs/plan.md` §Architecture v2).

---

## 0. Dimensionnement & topologie

| Élément | Reco mini (lancement) |
|---|---|
| vCPU / RAM | 2 vCPU / 4 Go (8 Go confortable avec Horizon) |
| Disque | 40–80 Go SSD |
| OS | Ubuntu 22.04 LTS |
| Domaines | `app.<domaine>` (SPA) · `api.<domaine>` (Laravel) |

**Topologie** : la SPA est un artefact **statique** servi par Nginx ; elle pointe vers l'API via
`VITE_API_BASE_URL` (fixé au build). L'auth est par **token Bearer Sanctum** (pas de cookie de
session cross-site) → CORS simple, et la SPA peut viser **n'importe quel cluster pays** sans changer de code.

---

## 1. Paquets système

```bash
sudo apt update && sudo apt -y upgrade
# PHP 8.2 + extensions Laravel
sudo apt -y install php8.2-fpm php8.2-cli php8.2-mysql php8.2-redis php8.2-mbstring \
  php8.2-xml php8.2-bcmath php8.2-curl php8.2-zip php8.2-gd php8.2-intl unzip git
# Composer
curl -sS https://getcomposer.org/installer | php && sudo mv composer.phar /usr/local/bin/composer
# MySQL 8 + Redis + Nginx
sudo apt -y install mysql-server redis-server nginx
# Node 20 (build du frontend) + Certbot (SSL)
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash - && sudo apt -y install nodejs
sudo apt -y install certbot python3-certbot-nginx
```

## 2. Base de données

```bash
sudo mysql -e "CREATE DATABASE frynov CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "CREATE USER 'frynov'@'localhost' IDENTIFIED BY 'MOT_DE_PASSE_FORT';"
sudo mysql -e "GRANT ALL PRIVILEGES ON frynov.* TO 'frynov'@'localhost'; FLUSH PRIVILEGES;"
```
> ⚠️ Les contraintes `CHECK` du stock sont **MySQL-only** (cf. Go/No-Go) : elles s'activent ici, pas en SQLite.

## 3. Code & dossiers

```bash
sudo mkdir -p /var/www/frynov && sudo chown -R $USER:www-data /var/www/frynov
cd /var/www/frynov
git clone <REPO_URL> repo && cd repo
git checkout v1.0.0      # tag de release (après GO ferme)
```

## 4. Backend (Laravel)

```bash
cd /var/www/frynov/repo/backend
composer install --no-dev --optimize-autoloader
cp .env.example .env && php artisan key:generate
# Éditer .env : voir §6
php artisan migrate --force            # 1re mise en prod
php artisan db:seed --force            # données de référence (rôles, plans, pays, modules)
#   (ne PAS lancer DemoSeeder en prod — c'est de la démo)
php artisan storage:link
# Caches de prod
php artisan config:cache && php artisan route:cache && php artisan event:cache
sudo chown -R www-data:www-data storage bootstrap/cache
```

## 5. Frontend (SPA Vue)

```bash
cd /var/www/frynov/repo/frontend
npm ci
VITE_API_BASE_URL=https://api.<domaine> npm run build   # produit dist/
```
> Le build exige `vue-tsc` (devDependency) → `npm ci` (pas `--omit=dev`). `dist/` est l'artefact statique servi par Nginx.

## 6. `.env` backend (extraits prod)

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.<domaine>
APP_KEY=base64:...               # généré par key:generate

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=frynov
DB_USERNAME=frynov
DB_PASSWORD=MOT_DE_PASSE_FORT

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
REDIS_HOST=127.0.0.1

FEATURE_SYNC=false               # module Sync (Phase 3) masqué en prod

# CORS : autoriser l'origine de la SPA (auth Bearer, pas de cookies cross-site)
# config/cors.php → 'allowed_origins' => ['https://app.<domaine>']
```

## 7. Nginx — deux vhosts

**API (`api.<domaine>`)** — standard Laravel :
```nginx
server {
  listen 80;  server_name api.<domaine>;
  root /var/www/frynov/repo/backend/public;  index index.php;
  location / { try_files $uri $uri/ /index.php?$query_string; }
  location ~ \.php$ {
    fastcgi_pass unix:/run/php/php8.2-fpm.sock;
    fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    include fastcgi_params;
  }
  client_max_body_size 12M;     # imports XLSX (max 10M)
}
```

**SPA (`app.<domaine>`)** — statique + fallback :
```nginx
server {
  listen 80;  server_name app.<domaine>;
  root /var/www/frynov/repo/frontend/dist;  index index.html;
  location / { try_files $uri $uri/ /index.html; }     # routing client Vue
  location /assets/ { expires 1y; add_header Cache-Control "public, immutable"; }
}
```
```bash
sudo ln -s /etc/nginx/sites-available/{api,app}.<domaine> /etc/nginx/sites-enabled/ 2>/dev/null
sudo nginx -t && sudo systemctl reload nginx
```

## 8. Queues (Horizon) & planificateur

`anti-oversell` (Redis) et les jobs (CMUP async, exports) tournent via **Horizon** :
```ini
# /etc/supervisor/conf.d/frynov-horizon.conf
[program:frynov-horizon]
command=php /var/www/frynov/repo/backend/artisan horizon
user=www-data  autostart=true  autorestart=true  stopwaitsecs=3600
```
```bash
sudo apt -y install supervisor
sudo supervisorctl reread && sudo supervisorctl update && sudo supervisorctl start frynov-horizon
# Planificateur Laravel (1 ligne cron)
( crontab -l 2>/dev/null; echo "* * * * * cd /var/www/frynov/repo/backend && php artisan schedule:run >> /dev/null 2>&1" ) | crontab -
```

## 9. SSL (Let's Encrypt)

```bash
sudo certbot --nginx -d api.<domaine> -d app.<domaine>
```

## 10. Durcissement

- Firewall : `sudo ufw allow OpenSSH && sudo ufw allow 'Nginx Full' && sudo ufw enable`
- `fail2ban` (SSH), MySQL non exposé (bind 127.0.0.1), Redis `requirepass` + bind local.
- `APP_DEBUG=false`, secrets hors Git, Horizon protégé (gate `viewHorizon` → super-admin).
- Sauvegardes : `mysqldump` quotidien + `storage/` ; rétention 7–30 j (cron + offsite).

## 11. Mise à jour (déploiement)

```bash
cd /var/www/frynov/repo && git fetch && git checkout vX.Y.Z
cd backend  && composer install --no-dev -o && php artisan migrate --force \
  && php artisan config:cache && php artisan route:cache && php artisan event:cache
cd ../frontend && npm ci && VITE_API_BASE_URL=https://api.<domaine> npm run build
sudo supervisorctl restart frynov-horizon && sudo systemctl reload php8.2-fpm
```
> Zéro-downtime (optionnel) : déployer dans un dossier horodaté + symlink `current/`, puis basculer le symlink.

## 12. Note A2 Hosting

A2 propose VPS *Unmanaged* (root complet → ce guide s'applique tel quel) ou *Managed*
(cPanel/WHM). En *Managed*, privilégier un VPS root ou un conteneur dédié : Frynov a besoin de
**Redis + workers persistants (Horizon)** et d'un **accès cron/supervisor**, peu compatibles avec
un hébergement mutualisé classique. Choisir un plan VPS (pas Shared) avec accès SSH root.

> **v2 / souveraineté des données** : ce déploiement mono-cluster est la base. La cible multi-pays
> (un cluster + une base **par pays**) est décrite dans `docs/plan.md` (Architecture v2). La SPA étant
> découplée et configurée par `VITE_API_BASE_URL`, elle se redéploie à l'identique vers chaque cluster pays.
