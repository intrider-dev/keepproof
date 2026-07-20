COMPOSE := docker compose
BACKEND := $(COMPOSE) run --rm backend
FRONTEND := $(COMPOSE) run --rm frontend

.PHONY: setup up down test quality e2e seed demo logs reset build production-build env

env:
	@test -f .env || cp .env.example .env
	@test -f apps/backend/.env || cp apps/backend/.env.example apps/backend/.env
	@test -f apps/frontend/.env || cp apps/frontend/.env.example apps/frontend/.env

setup: env
	$(COMPOSE) build backend frontend
	$(BACKEND) composer install --no-interaction --prefer-dist
	$(FRONTEND) npm ci
	$(BACKEND) php artisan key:generate --force
	$(BACKEND) php artisan migrate --force
	$(BACKEND) php artisan keepproof:storage:prepare --create

up: env
	$(COMPOSE) up --detach --wait

down:
	$(COMPOSE) down --remove-orphans

test:
	$(BACKEND) composer test
	$(COMPOSE) run --rm --workdir /workspace/packages/deadline-engine backend composer test
	$(FRONTEND) npm run test:unit

quality:
	$(BACKEND) composer quality
	$(COMPOSE) run --rm --workdir /workspace/packages/deadline-engine backend composer check
	$(FRONTEND) npm run quality

e2e: up
	$(COMPOSE) --profile tools run --rm e2e

seed:
	$(BACKEND) php artisan migrate --force
	$(BACKEND) php artisan keepproof:storage:prepare --create
	$(BACKEND) php artisan db:seed --force

demo: seed up
	@echo "KeepProof is available at http://localhost:$${APP_PORT:-8080}"
	@echo "Mailpit is available at http://localhost:8025"

logs:
	$(COMPOSE) logs --follow --tail=200

build:
	$(COMPOSE) build

production-build:
	$(COMPOSE) -f compose.yaml -f compose.production.yaml build

reset:
	$(COMPOSE) down --volumes --remove-orphans
	@echo "Removed KeepProof containers and named development volumes."
