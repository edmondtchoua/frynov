# Guide d'installation et de setup

## Prérequis

| Outil | Version minimale | Notes |
|-------|-----------------|-------|
| PHP | 8.3 | Extensions : `pdo_mysql`, `pdo_sqlite`, `mbstring`, `xml`, `gd` |
| Composer | 2.x | |
| MySQL | 8.0 | Pour l'environnement local |
| Redis | 7.x | Pour cache et mutex (Inventory) |
| Node.js | 20.x | Pour le frontend Vue |
| Docker | 24.x | Optionnel mais recommandé |

---

## Installation locale (sans Docker)

### 1. Cloner le dépôt

```bash
git clone https://github.com/etech-africa/erp.git
cd erp
```

### 2. Backend Laravel

```bash
cd backend

# Installer les dépendances PHP
composer install

# Copier et configurer l'environnement
cp .env.example .env

# Éditer .env :
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_DATABASE=etech_erp
# DB_USERNAME=root
# DB_PASSWORD=

# Générer la clé applicative
php artisan key:generate

# Créer la base de données
mysql -u root -e "CREATE DATABASE etech_erp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Lancer les migrations
php artisan migrate

# (Optionnel) Données de test
php artisan db:seed
```

### 3. Lancer le serveur de développement

```bash
# Dans backend/
php artisan serve
# → http://127.0.0.1:8000
```

---

## Installation avec Docker

```bash
# À la racine du monorepo
cp backend/.env.example backend/.env
# Adapter les variables DB_ pour pointer vers le conteneur MySQL

docker compose up -d

# Première exécution : migrations
docker compose exec backend php artisan migrate
```

Services disponibles après `docker compose up` :
- Backend API : `http://localhost:8000`
- MySQL : `localhost:3306`
- Redis : `localhost:6379`
- MailHog (emails dev) : `http://localhost:8025`

---

## Configuration PHP pour les tests (Windows)

Les tests utilisent SQLite en mémoire. Activer l'extension dans `php.ini` :

```ini
; Fichier : C:\PHP\8.3\php.ini
extension=pdo_sqlite
```

Vérification :
```bash
php -m | grep pdo_sqlite
# → pdo_sqlite
```

---

## Variables d'environnement importantes

| Variable | Exemple | Description |
|----------|---------|-------------|
| `APP_ENV` | `local` | Environnement (`local`, `staging`, `production`) |
| `APP_KEY` | `base64:...` | Clé de chiffrement (généré par `artisan key:generate`) |
| `DB_CONNECTION` | `mysql` | `mysql` en prod, `sqlite` (`:memory:`) en tests |
| `SANCTUM_STATEFUL_DOMAINS` | `localhost` | Domaines autorisés pour cookies SPA |
| `QUEUE_CONNECTION` | `redis` | `sync` en local, `redis` en prod |
| `REDIS_HOST` | `127.0.0.1` | Hôte Redis |

---

## Commandes Artisan utiles

```bash
# Créer un nouveau module
php artisan make:module NomDuModule

# Lancer toutes les migrations
php artisan migrate

# Rollback + re-migration (dev uniquement !)
php artisan migrate:fresh --seed

# Lister toutes les routes
php artisan route:list --path=api

# Vider les caches
php artisan optimize:clear
```
