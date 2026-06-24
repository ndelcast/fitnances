# Fingen - Development Commands

SAIL := ./vendor/bin/sail

.PHONY: help init install up down stop restart status shell logs db dev build exec \
	queue sail-queue \
	fresh seed migrate rollback \
	sail-fresh sail-seed sail-migrate \
	pint pint-dirty test lint fix \
	sail-pint sail-test \
	clear tinker ide

# === Help ===

help:
	@echo "Commandes disponibles :"
	@echo ""
	@echo "Setup :"
	@echo "  make init        - Installation complete du projet"
	@echo "  make install     - composer install + npm install"
	@echo ""
	@echo "Sail :"
	@echo "  make up          - Demarrer Sail (detection auto des ports)"
	@echo "  make stop        - Arreter Sail"
	@echo "  make down        - Alias de stop"
	@echo "  make restart     - Redemarrer Sail"
	@echo "  make status      - Statut Sail, ports & URLs"
	@echo "  make shell       - Ouvrir un shell dans le container app"
	@echo "  make logs        - Suivre les logs"
	@echo "  make db          - Ouvrir le client MySQL"
	@echo "  make exec cmd=.. - Executer une commande dans le container"
	@echo ""
	@echo "Queue :"
	@echo "  make queue       - Lancer le worker de queue"
	@echo "  make sail-queue  - Lancer le worker de queue (Sail)"
	@echo ""
	@echo "Base de donnees :"
	@echo "  make fresh       - Reset DB + seeders"
	@echo "  make seed        - Lancer les seeders"
	@echo "  make migrate     - Lancer les migrations"
	@echo "  make rollback    - Rollback de la derniere migration"
	@echo ""
	@echo "Qualite de code :"
	@echo "  make pint        - Lancer Laravel Pint"
	@echo "  make pint-dirty  - Pint sur fichiers modifies uniquement"
	@echo "  make test        - Lancer les tests"
	@echo "  make lint        - Verifier code style (Sail)"
	@echo "  make fix         - Corriger code style (Sail)"
	@echo ""
	@echo "Assets :"
	@echo "  make dev         - Serveur Vite (dev)"
	@echo "  make build       - Build assets (production)"
	@echo ""
	@echo "Commandes Sail (Docker) :"
	@echo "  make sail-fresh  - Reset DB + seeders (Sail)"
	@echo "  make sail-seed   - Seeders (Sail)"
	@echo "  make sail-migrate- Migrations (Sail)"
	@echo "  make sail-pint   - Laravel Pint (Sail)"
	@echo "  make sail-test   - Tests (Sail)"
	@echo ""
	@echo "Divers :"
	@echo "  make clear       - Vider les caches"
	@echo "  make tinker      - Ouvrir Tinker"
	@echo "  make ide         - Generer les helpers IDE"

# === Setup ===

init:
	@echo "🔧 Installation de Fingen..."
	cp -n .env.example .env || true
	composer install
	npm install
	$(SAIL) up -d
	$(SAIL) artisan key:generate
	$(SAIL) artisan migrate
	npm run build
	@echo ""
	@echo "✅ Installation terminée."
	@echo "   Créer un admin : $(SAIL) artisan make:filament-user"
	@echo "   Lancer le dev  : make dev"
	@echo "   Lancer la queue: make sail-queue"

install:
	$(SAIL) composer install
	$(SAIL) npm install

# === Sail Lifecycle ===

up:
	@echo "🔍 Détection des ports disponibles..."
	@USED_PORTS=$$(( lsof -iTCP -sTCP:LISTEN -nP 2>/dev/null | awk '{print $$9}' | grep -oE '[0-9]+$$'; docker ps --format '{{.Ports}}' 2>/dev/null | grep -oE '0\.0\.0\.0:[0-9]+' | cut -d: -f2 ) | sort -u); \
	is_free() { echo "$$USED_PORTS" | grep -qx "$$1" && return 1 || return 0; }; \
	find_port() { p=$$1; while ! is_free $$p; do p=$$((p + 1)); done; echo $$p; }; \
	APP_PORT=$$(find_port 80); \
	DB_PORT=$$(find_port 3306); \
	VITE=$$(find_port 5173); \
	echo "  HTTP:  $$APP_PORT"; \
	echo "  MySQL: $$DB_PORT"; \
	echo "  Vite:  $$VITE"; \
	echo ""; \
	sed -i '' '/^APP_PORT=/d;/^FORWARD_DB_PORT=/d;/^VITE_PORT=/d' .env; \
	echo "APP_PORT=$$APP_PORT" >> .env; \
	echo "FORWARD_DB_PORT=$$DB_PORT" >> .env; \
	echo "VITE_PORT=$$VITE" >> .env; \
	sed -i '' "s|^APP_URL=.*|APP_URL=http://localhost:$$APP_PORT|" .env; \
	echo "🚀 Démarrage de Sail..."; \
	$(SAIL) up -d; \
	echo ""; \
	echo "✅ Fingen est accessible sur : http://localhost:$$APP_PORT"

down:
	@$(SAIL) down
	@echo "✅ Sail arrêté."

stop: down

restart:
	$(SAIL) restart

status:
	@if $(SAIL) ps --status running 2>/dev/null | grep -q "laravel.test"; then \
		APP_PORT=$$(grep '^APP_PORT=' .env 2>/dev/null | cut -d= -f2); \
		DB_PORT=$$(grep '^FORWARD_DB_PORT=' .env 2>/dev/null | cut -d= -f2); \
		VITE_PORT=$$(grep '^VITE_PORT=' .env 2>/dev/null | cut -d= -f2); \
		echo "🟢 Sail est en cours d'exécution"; \
		echo ""; \
		echo "  🌐 App:   http://localhost:$${APP_PORT:-80}"; \
		echo "  🛢  MySQL: localhost:$${DB_PORT:-3306}"; \
		echo "  ⚡ Vite:  http://localhost:$${VITE_PORT:-5173}"; \
		echo ""; \
		$(SAIL) ps --format "table {{.Name}}\t{{.Status}}\t{{.Ports}}"; \
	else \
		echo "🔴 Sail n'est pas lancé."; \
		echo "   Lancer avec : make up"; \
	fi

shell:
	$(SAIL) shell

logs:
	$(SAIL) logs -f

db:
	$(SAIL) mysql

exec:
	@if [ -z "$(cmd)" ]; then \
		echo "Usage: make exec cmd=\"<commande>\""; \
		exit 1; \
	fi
	$(SAIL) exec laravel.test $(cmd)

# === Queue ===

queue:
	php artisan queue:work

sail-queue:
	$(SAIL) artisan queue:work

# === Database ===

fresh:
	php artisan migrate:fresh --seed

seed:
	php artisan db:seed

migrate:
	php artisan migrate

rollback:
	$(SAIL) artisan migrate:rollback

# === Code Quality ===

pint:
	./vendor/bin/pint

pint-dirty:
	./vendor/bin/pint --dirty

test:
	php artisan test --compact

lint:
	$(SAIL) bin pint --test

fix:
	$(SAIL) bin pint

# === Sail Commands (Docker) ===

sail-fresh:
	$(SAIL) artisan migrate:fresh --seed

sail-seed:
	$(SAIL) artisan db:seed

sail-migrate:
	$(SAIL) artisan migrate

sail-pint:
	$(SAIL) bin pint

sail-test:
	$(SAIL) artisan test --compact

# === Assets ===

dev:
	npm run dev

build:
	npm run build

# === Divers ===

clear:
	$(SAIL) artisan config:clear
	$(SAIL) artisan route:clear
	$(SAIL) artisan view:clear
	$(SAIL) artisan cache:clear

tinker:
	$(SAIL) artisan tinker

ide:
	$(SAIL) artisan ide-helper:generate
	$(SAIL) artisan ide-helper:models -N
	$(SAIL) artisan ide-helper:meta
