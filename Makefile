# ─────────────────────────────────────────────
#  ERP Africa — Makefile
#  Usage : make <commande>
# ─────────────────────────────────────────────

.PHONY: help up down build install migrate seed test test-unit \
        test-integration test-modular test-all coverage lint \
        module branch-feature branch-phase shell-app shell-db logs

# Couleurs
GREEN  := \033[0;32m
YELLOW := \033[0;33m
NC     := \033[0m

help: ## Afficher cette aide
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) \
	| awk 'BEGIN {FS = ":.*?## "}; {printf "$(GREEN)%-25s$(NC) %s\n", $$1, $$2}'

# ── Docker ────────────────────────────────────────────────────
up: ## Démarrer tous les services
	docker compose up -d
	@echo "$(GREEN)Services démarrés$(NC)"
	@echo "  App      : http://localhost:8080"
	@echo "  Frontend : http://localhost:5173"
	@echo "  Mailpit  : http://localhost:8025"
	@echo "  MinIO    : http://localhost:9001"

down: ## Arrêter tous les services
	docker compose down

build: ## Rebuilder les images Docker
	docker compose build --no-cache

restart: ## Redémarrer les services
	docker compose restart

logs: ## Afficher les logs en temps réel
	docker compose logs -f app queue

logs-all: ## Afficher tous les logs
	docker compose logs -f

# ── Installation ──────────────────────────────────────────────
install: ## Installer toutes les dépendances (backend + frontend)
	@echo "$(YELLOW)Installation des dépendances backend...$(NC)"
	docker compose exec app composer install
	cp -n backend/.env.example backend/.env || true
	docker compose exec app php artisan key:generate
	@echo "$(YELLOW)Installation des dépendances frontend...$(NC)"
	docker compose run --rm frontend npm ci
	@echo "$(GREEN)Installation terminée$(NC)"

# ── Base de données ───────────────────────────────────────────
migrate: ## Lancer les migrations
	docker compose exec app php artisan migrate

migrate-fresh: ## Réinitialiser la DB et relancer les migrations
	docker compose exec app php artisan migrate:fresh --seed

seed: ## Injecter les données de test
	docker compose exec app php artisan db:seed

# ── Tests ─────────────────────────────────────────────────────
test: test-unit ## Lancer les tests unitaires (défaut)

test-unit: ## Tests unitaires uniquement (rapides)
	docker compose exec app php artisan test --testsuite=Unit --parallel

test-integration: ## Tests d'intégration (nécessite la DB)
	docker compose exec app php artisan test --testsuite=Integration --parallel

test-modular: ## Tests modulaires (flux complets)
	docker compose exec app php artisan test --testsuite=Modular

test-all: ## Tous les tests
	docker compose exec app php artisan test --parallel

test-frontend: ## Tests unitaires frontend
	docker compose run --rm frontend npm run test:unit

coverage: ## Générer le rapport de couverture
	docker compose exec app php artisan test --coverage
	@echo "$(GREEN)Rapport disponible dans backend/coverage/$(NC)"

# ── Qualité ───────────────────────────────────────────────────
lint: ## Vérifier le code style (Pint + ESLint)
	docker compose exec app ./vendor/bin/pint --test
	docker compose run --rm frontend npm run lint

lint-fix: ## Corriger automatiquement le code style
	docker compose exec app ./vendor/bin/pint
	docker compose run --rm frontend npm run lint -- --fix

analyse: ## Analyse statique PHPStan
	docker compose exec app ./vendor/bin/phpstan analyse --level=6

# ── Génération de code ────────────────────────────────────────
module: ## Créer un nouveau module : make module NAME=Orders PHASE=1
	@if [ -z "$(NAME)" ]; then echo "Usage: make module NAME=NomModule PHASE=1"; exit 1; fi
	docker compose exec app php artisan make:module $(NAME) --phase=$(or $(PHASE),1)

# ── Git — Raccourcis de branches ─────────────────────────────
branch-feature: ## Créer une branche feature : make branch-feature PHASE=1 NAME=catalog-module
	@if [ -z "$(NAME)" ] || [ -z "$(PHASE)" ]; then \
		echo "Usage: make branch-feature PHASE=1 NAME=catalog-module"; exit 1; \
	fi
	git checkout develop/phase$(PHASE)
	git pull origin develop/phase$(PHASE)
	git checkout -b phase$(PHASE)/feature/$(NAME)
	@echo "$(GREEN)Branche phase$(PHASE)/feature/$(NAME) créée$(NC)"

branch-fix: ## Créer une branche fix : make branch-fix PHASE=1 NAME=stock-lock
	@if [ -z "$(NAME)" ] || [ -z "$(PHASE)" ]; then \
		echo "Usage: make branch-fix PHASE=1 NAME=stock-lock"; exit 1; \
	fi
	git checkout develop/phase$(PHASE)
	git pull origin develop/phase$(PHASE)
	git checkout -b phase$(PHASE)/fix/$(NAME)
	@echo "$(GREEN)Branche phase$(PHASE)/fix/$(NAME) créée$(NC)"

branch-hotfix: ## Créer une branche hotfix : make branch-hotfix NAME=payment-null-pointer
	@if [ -z "$(NAME)" ]; then echo "Usage: make branch-hotfix NAME=description"; exit 1; fi
	git checkout main
	git pull origin main
	git checkout -b hotfix/$(NAME)
	@echo "$(GREEN)Branche hotfix/$(NAME) créée depuis main$(NC)"

# ── Shells ────────────────────────────────────────────────────
shell-app: ## Ouvrir un shell dans le conteneur app
	docker compose exec app sh

shell-db: ## Ouvrir psql dans le conteneur postgres
	docker compose exec postgres psql -U erp -d erp_development

tinker: ## Laravel Tinker
	docker compose exec app php artisan tinker

horizon: ## Ouvrir Laravel Horizon dans le navigateur
	@echo "Horizon disponible sur http://localhost:8080/horizon"

# ── Artisan raccourcis ────────────────────────────────────────
routes: ## Afficher toutes les routes API
	docker compose exec app php artisan route:list --path=api

cache-clear: ## Vider tous les caches Laravel
	docker compose exec app php artisan optimize:clear
