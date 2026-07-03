# =============================================================================
# JUANET Enterprise SaaS Platform - Makefile Standard Commands
# Target: Multi-developer local workspace automation (Linux, macOS, WSL2)
# =============================================================================

.DEFAULT_GOAL := help

# --- Color Definitions for Colored Help output ---
GREEN  := $(shell tput -Txterm setaf 2 2>/dev/null || echo "")
YELLOW := $(shell tput -Txterm setaf 3 2>/dev/null || echo "")
BLUE   := $(shell tput -Txterm setaf 4 2>/dev/null || echo "")
RESET  := $(shell tput -Txterm sgr0 2>/dev/null || echo "")

# --- Containers names ---
DOCKER_COMPOSE := docker compose
APP_SERVICE    := app
NGINX_SERVICE  := web
REDIS_SERVICE  := redis

.PHONY: help
help: ## Display this help message
	@echo "JUANET Enterprise SaaS Automation System"
	@echo "----------------------------------------"
	@echo "Usage: make ${YELLOW}<target>${RESET}"
	@echo ""
	@echo "Available commands grouped by category:"
	@echo ""
	@echo "${BLUE}Docker Lifecycle:${RESET}"
	@grep -E '^[a-zA-Z_-]+:.*?## .*Lifecycle.*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "  ${YELLOW}%-20s${RESET} %s\n", $$1, $$2}'
	@echo ""
	@echo "${BLUE}Laravel Controls:${RESET}"
	@grep -E '^[a-zA-Z_-]+:.*?## .*Laravel.*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "  ${YELLOW}%-20s${RESET} %s\n", $$1, $$2}'
	@echo ""
	@echo "${BLUE}Composer & Node Dependency Engines:${RESET}"
	@grep -E '^[a-zA-Z_-]+:.*?## .*Dependency.*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "  ${YELLOW}%-20s${RESET} %s\n", $$1, $$2}'
	@echo ""
	@echo "${BLUE}Code Quality, Linting & Testing:${RESET}"
	@grep -E '^[a-zA-Z_-]+:.*?## .*Quality.*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "  ${YELLOW}%-20s${RESET} %s\n", $$1, $$2}'
	@echo ""
	@echo "${BLUE}System Maintenance, Cleanups & DB Access:${RESET}"
	@grep -E '^[a-zA-Z_-]+:.*?## .*Maintenance.*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "  ${YELLOW}%-20s${RESET} %s\n", $$1, $$2}'

# =============================================================================
# DOCKER COMMANDS
# =============================================================================

.PHONY: up
up: ## Start all Docker containers - Lifecycle
	$(DOCKER_COMPOSE) up -d

.PHONY: down
down: ## Stop all Docker containers - Lifecycle
	$(DOCKER_COMPOSE) down

.PHONY: restart
restart: ## Restart all services - Lifecycle
	$(DOCKER_COMPOSE) restart

.PHONY: build
build: ## Build Docker images - Lifecycle
	$(DOCKER_COMPOSE) build

.PHONY: rebuild
rebuild: ## Rebuild images without cache - Lifecycle
	$(DOCKER_COMPOSE) build --no-cache

.PHONY: ps
ps: ## Show running containers - Lifecycle
	$(DOCKER_COMPOSE) ps

.PHONY: logs
logs: ## Tail logs for all services - Lifecycle
	$(DOCKER_COMPOSE) logs -f

.PHONY: logs-app
logs-app: ## App container logs - Lifecycle
	$(DOCKER_COMPOSE) logs -f $(APP_SERVICE)

.PHONY: logs-nginx
logs-nginx: ## Nginx logs - Lifecycle
	$(DOCKER_COMPOSE) logs -f $(NGINX_SERVICE)

.PHONY: logs-redis
logs-redis: ## Redis logs - Lifecycle
	$(DOCKER_COMPOSE) logs -f $(REDIS_SERVICE)

# =============================================================================
# LARAVEL COMMANDS
# =============================================================================

.PHONY: artisan
artisan: ## Run any artisan command, usage: make artisan CMD="migrate" - Laravel
	$(DOCKER_COMPOSE) exec -u juanet $(APP_SERVICE) php artisan $(CMD)

.PHONY: migrate
migrate: ## Run pending database migrations - Laravel
	$(DOCKER_COMPOSE) exec -u juanet $(APP_SERVICE) php artisan migrate --force

.PHONY: migrate-fresh
migrate-fresh: ## Wipe database and run all migrations from scratch - Laravel
	$(DOCKER_COMPOSE) exec -u juanet $(APP_SERVICE) php artisan migrate:fresh --force

.PHONY: seed
seed: ## Populate database with seed data - Laravel
	$(DOCKER_COMPOSE) exec -u juanet $(APP_SERVICE) php artisan db:seed --force

.PHONY: fresh
fresh: migrate-fresh seed ## Fresh migration and database seed - Laravel

.PHONY: rollback
rollback: ## Rollback the last database migration - Laravel
	$(DOCKER_COMPOSE) exec -u juanet $(APP_SERVICE) php artisan migrate:rollback

.PHONY: optimize
optimize: ## Cache configs, routes, events, and views for speed - Laravel
	$(DOCKER_COMPOSE) exec -u juanet $(APP_SERVICE) php artisan optimize

.PHONY: optimize-clear
optimize-clear: ## Clear all cached configs, routes, events, and views - Laravel
	$(DOCKER_COMPOSE) exec -u juanet $(APP_SERVICE) php artisan optimize:clear

.PHONY: cache-clear
cache-clear: ## Reset Laravel system caches - Laravel
	$(DOCKER_COMPOSE) exec -u juanet $(APP_SERVICE) php artisan cache:clear

.PHONY: route-clear
route-clear: ## Reset Laravel route cache - Laravel
	$(DOCKER_COMPOSE) exec -u juanet $(APP_SERVICE) php artisan route:clear

.PHONY: config-clear
config-clear: ## Reset Laravel config cache - Laravel
	$(DOCKER_COMPOSE) exec -u juanet $(APP_SERVICE) php artisan config:clear

.PHONY: view-clear
view-clear: ## Reset Laravel compiled view cache - Laravel
	$(DOCKER_COMPOSE) exec -u juanet $(APP_SERVICE) php artisan view:clear

.PHONY: queue-work
queue-work: ## Run background queue worker - Laravel
	$(DOCKER_COMPOSE) exec -u juanet $(APP_SERVICE) php artisan queue:work

.PHONY: queue-restart
queue-restart: ## Force queue workers to restart after updates - Laravel
	$(DOCKER_COMPOSE) exec -u juanet $(APP_SERVICE) php artisan queue:restart

.PHONY: schedule-work
schedule-work: ## Run background scheduler worker - Laravel
	$(DOCKER_COMPOSE) exec -u juanet $(APP_SERVICE) php artisan schedule:work

.PHONY: tinker
tinker: ## Open an interactive Laravel Shell session - Laravel
	$(DOCKER_COMPOSE) exec -u juanet $(APP_SERVICE) php artisan tinker

# =============================================================================
# DEPENDENCIES COMMANDS
# =============================================================================

.PHONY: composer-install
composer-install: ## Install composer dependencies - Dependency
	$(DOCKER_COMPOSE) exec -u juanet $(APP_SERVICE) composer install

.PHONY: composer-update
composer-update: ## Update composer dependencies - Dependency
	$(DOCKER_COMPOSE) exec -u juanet $(APP_SERVICE) composer update

.PHONY: composer-dump
composer-dump: ## Optimize and regenerate composer autoload map - Dependency
	$(DOCKER_COMPOSE) exec -u juanet $(APP_SERVICE) composer dump-autoload

.PHONY: npm-install
npm-install: ## Install npm package dependencies - Dependency
	$(DOCKER_COMPOSE) exec -u juanet $(APP_SERVICE) npm install

.PHONY: npm-dev
npm-dev: ## Run local assets development server - Dependency
	$(DOCKER_COMPOSE) exec -u juanet $(APP_SERVICE) npm run dev

.PHONY: npm-build
npm-build: ## Build asset bundle for production deployment - Dependency
	$(DOCKER_COMPOSE) exec -u juanet $(APP_SERVICE) npm run build

# =============================================================================
# QUALITY COMMANDS
# =============================================================================

.PHONY: test
test: ## Run standard phpunit suite inside app container - Quality
	$(DOCKER_COMPOSE) exec -u juanet $(APP_SERVICE) ./vendor/bin/phpunit

.PHONY: lint
lint: ## Code Quality checker check - Quality
	@echo "Checking syntax standard with PHP CS Fixer (dry-run)..."
	# $(DOCKER_COMPOSE) exec -u juanet $(APP_SERVICE) ./vendor/bin/php-cs-fixer fix --dry-run --diff

.PHONY: format
format: ## Format standard style - Quality
	@echo "Formatting codebase using PHP CS Fixer..."
	# $(DOCKER_COMPOSE) exec -u juanet $(APP_SERVICE) ./vendor/bin/php-cs-fixer fix

.PHONY: stan
stan: ## Run phpstan static analysis scanner - Quality
	@echo "Running PHPStan analysis..."
	# $(DOCKER_COMPOSE) exec -u juanet $(APP_SERVICE) ./vendor/bin/phpstan analyse

.PHONY: psalm
psalm: ## Run psalm static analysis scanner - Quality
	@echo "Running Psalm analysis..."
	# $(DOCKER_COMPOSE) exec -u juanet $(APP_SERVICE) ./vendor/bin/psalm

# =============================================================================
# MAINTENANCE AND UTILITY COMMANDS
# =============================================================================

.PHONY: permissions
permissions: ## Correct directory ownership and permission issues - Maintenance
	$(DOCKER_COMPOSE) exec -u root $(APP_SERVICE) chown -R juanet:www-data storage bootstrap/cache
	$(DOCKER_COMPOSE) exec -u root $(APP_SERVICE) chmod -R 775 storage bootstrap/cache

.PHONY: clean
clean: optimize-clear ## Clear caches and purge temporary session/view files - Maintenance
	rm -rf public/build public/hot bootstrap/cache/*.php

.PHONY: prune
prune: ## Prune completely unreferenced docker containers & networks - Maintenance
	docker system prune -f --volumes

.PHONY: shell
shell: ## Open bash shell inside App container as application user - Maintenance
	$(DOCKER_COMPOSE) exec -it -u juanet $(APP_SERVICE) bash

.PHONY: root-shell
root-shell: ## Open bash shell inside App container as superuser - Maintenance
	$(DOCKER_COMPOSE) exec -it -u root $(APP_SERVICE) bash

.PHONY: db
db: ## Connect directly to postgres client inside db container - Maintenance
	$(DOCKER_COMPOSE) exec -it db psql -U postgres -d juanet_platform

.PHONY: redis-cli
redis-cli: ## Connect to redis-cli in active Redis container - Maintenance
	$(DOCKER_COMPOSE) exec -it $(REDIS_SERVICE) redis-cli
