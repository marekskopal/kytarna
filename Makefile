.PHONY: up down logs build migrate test test-backend test-backend-coverage test-frontend test-e2e test-e2e-ui test-env-up test-env-down lint lint-backend lint-fix install

## Start stack
up:
	docker compose up -d --build

## Stop stack
down:
	docker compose down

## Tail logs
logs:
	docker compose logs -f

## Rebuild without cache
build:
	docker compose build --no-cache

## Install backend + frontend dependencies (host)
install:
	cd backend && composer install
	cd frontend && pnpm install

## Run database migrations
migrate:
	docker compose exec backend php bin/console migration:run

## Run all tests
test: test-backend test-frontend test-e2e

## Backend unit tests (PHPUnit). Runs inside the backend container so the test
## suite can reach MariaDB. The harness auto-creates the `kytarna_test` database
## using the backend's MYSQL_USER credentials (must have CREATE privilege).
test-backend:
	docker compose exec -T -e TEST_MYSQL_DATABASE=kytarna_test backend vendor/bin/phpunit

## Backend unit tests with coverage report at backend/.phpunit.cache/coverage-html
## Uses pcov (compiled into the backend image). Coverage focuses on src/Controller,
## src/Mcp, and src/Service per the UPB-49 acceptance criteria.
test-backend-coverage:
	docker compose exec -T -e TEST_MYSQL_DATABASE=kytarna_test backend vendor/bin/phpunit --coverage-text --coverage-html .phpunit.cache/coverage-html

## Frontend unit tests (Vitest)
test-frontend:
	cd frontend && pnpm run test

## End-to-end tests (Playwright). Brings up the dev stack via docker compose if it
## is not already running (Playwright's webServer config uses reuseExistingServer).
## Override the base URL with E2E_BASE_URL=... and skip the auto-up with
## E2E_SKIP_WEBSERVER=1 when the stack is managed externally.
test-e2e: test-env-up
	cd frontend && E2E_SKIP_WEBSERVER=1 pnpm run e2e; \
	status=$$?; \
	$(MAKE) -C $(CURDIR) test-env-down; \
	exit $$status

## Bring up the e2e docker stack (db, redis, memcached, backend, frontend, proxy).
## Generates a self-signed TLS cert at ./test-ssl/ for the proxy on first run,
## bootstraps a .env from .env.example with a real AUTHORIZATION_TOKEN_KEY if
## the placeholder is still in place, applies migrations, and bind-mounts host
## backend code via docker-compose.test.yml so phpunit/phpstan/phpcs are visible
## (host backend/vendor must be populated — `cd backend && composer install`).
## The web/marketing service is skipped.
test-env-up:
	@if [ ! -f .env ]; then cp .env.example .env; fi
	@if grep -q "^AUTHORIZATION_TOKEN_KEY=replace-with-32-char-random-hex-key-here" .env; then \
		sed -i.bak "s|AUTHORIZATION_TOKEN_KEY=replace-with-32-char-random-hex-key-here|AUTHORIZATION_TOKEN_KEY=$$(openssl rand -hex 32)|" .env && rm -f .env.bak; \
	fi
	@mkdir -p test-ssl
	@if [ ! -f test-ssl/server.crt ]; then \
		openssl req -x509 -newkey rsa:2048 -keyout test-ssl/server.key -out test-ssl/server.crt \
			-days 365 -nodes -subj '/CN=localhost' 2>/dev/null; \
	fi
	@sed -i.bak "s|^PROXY_SSL_CERT=.*|PROXY_SSL_CERT=$(CURDIR)/test-ssl/server.crt|" .env && rm -f .env.bak
	@sed -i.bak "s|^PROXY_SSL_KEY=.*|PROXY_SSL_KEY=$(CURDIR)/test-ssl/server.key|" .env && rm -f .env.bak
	@if [ ! -d backend/vendor ]; then \
		echo "backend/vendor missing — run 'cd backend && composer install' first"; \
		exit 1; \
	fi
	ADMINER_USER=test ADMINER_PASSWORD=test \
		docker compose $(COMPOSE_FILES) --profile dev up -d --build --wait db redis memcached backend frontend proxy adminer
	docker compose exec -T backend php bin/console migration:run

## Stop the e2e docker stack.
test-env-down:
	docker compose $(COMPOSE_FILES) --profile dev down

# docker-compose.ssl.yml only exists on the open-source branch; on main the
# proxy serves SSL directly from docker-compose.yml. Include the override only
# when the file is present so the same target works on both branches.
COMPOSE_FILES := -f docker-compose.yml $(if $(wildcard docker-compose.ssl.yml),-f docker-compose.ssl.yml,) -f docker-compose.test.yml

## Run Playwright in interactive UI mode against the running dev stack.
test-e2e-ui:
	cd frontend && pnpm run e2e:ui

## Backend static analysis + code style
lint: lint-backend

lint-backend:
	cd backend && vendor/bin/phpstan analyse --no-progress
	cd backend && vendor/bin/phpcs

## Auto-fix backend code style
lint-fix:
	cd backend && vendor/bin/phpcbf
