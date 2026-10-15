# JOOservices WP themes: single entry point. Compatible with GNU make 3.81 (macOS).
.DEFAULT_GOAL := help
SHELL := /bin/bash
THEME ?= jooservices
export JOOWT_PROJECT ?= jooservices-wp-themes


.PHONY: test-tooling install-wp help up down reset shell wp logs link-core init-env lint analyse test-unit test-integration test-e2e demo health perf test-matrix package docs clean hooks

help: ## Show this help
	@grep -E '^[a-zA-Z0-9_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN{FS=":.*?## "}{printf "  %-20s %s\n",$$1,$$2}'

define stub
	@echo "[$(1)] not implemented yet"
endef

define require_theme
	@test -d themes/$(THEME) || { echo "theme not found: themes/$(THEME)"; exit 1; }
endef

COMPOSE := ./docker/scripts/compose.sh

init-env: ## Create .env with random secrets (never overwrites)
	@./docker/scripts/init-env.sh
up: ## Start the Docker stack and install WordPress + THEME
	@test -f .env || ./docker/scripts/init-env.sh
	@p=$${WP_PORT:-$$(grep -E '^WP_PORT=' .env | cut -d= -f2)}; ./docker/scripts/check-port.sh "$${p:-8080}" wordpress
	@p=$${MAIL_PORT:-$$(grep -E '^MAIL_PORT=' .env | cut -d= -f2)}; ./docker/scripts/check-port.sh "$${p:-8025}" mailpit
	@$(COMPOSE) up -d --build --wait
	@$(MAKE) --no-print-directory install-wp THEME=$(THEME)
install-wp: ## Install WordPress and activate THEME (idempotent)
	$(call require_theme)
	@$(MAKE) --no-print-directory link-core THEME=$(THEME)
	@$(COMPOSE) exec -T -u www-data -e THEME=$(THEME) wordpress bash /scripts/install-wp.sh
down: ## Stop the Docker stack (keeps data)
	@$(COMPOSE) down
reset: ## Remove this project's Docker resources (needs CONFIRM=yes)
	@test "$(CONFIRM)" = "yes" || { echo "reset removes this project's containers and volumes. Re-run with CONFIRM=yes"; exit 1; }
	@test -f .env || { echo "nothing to reset (.env missing)"; exit 0; }
	@echo "Removing resources labelled com.joowt.project=$(JOOWT_PROJECT):"
	@docker ps -a --filter label=com.joowt.project=$(JOOWT_PROJECT) --format '  container {{.Names}}'
	@docker volume ls --filter label=com.joowt.project=$(JOOWT_PROJECT) --format '  volume {{.Name}}'
	@$(COMPOSE) down -v --remove-orphans
shell: ## Shell in the WordPress container
	@$(COMPOSE) exec -u www-data wordpress bash
wp: ## Run WP-CLI: make wp ARGS="core version"
	@$(COMPOSE) exec -T -u www-data wordpress wp $(ARGS)
logs: ## Follow container logs (SERVICE=wordpress)
	@$(COMPOSE) logs -f --tail=100 $(SERVICE)
link-core: ## Link packages/theme-core into themes/$(THEME)/core
	$(call require_theme)
	@ln -sfn ../../packages/theme-core themes/$(THEME)/core
	@echo "themes/$(THEME)/core -> ../../packages/theme-core"
PHP := php -d error_reporting=24575
lint: ## PHPCS, ESLint, Stylelint, Prettier, header check
	@php tools/check-headers.php
	@$(PHP) vendor/bin/phpcs
	@npx --no-install eslint .
	@npx --no-install stylelint "**/*.css" --allow-empty-input
	@npx --no-install prettier --ignore-path .prettierignore --check .
analyse: ## PHPStan (core level 8, others level 6), tsc checkJs, deptrac
	@$(PHP) vendor/bin/phpstan analyse -c phpstan-core.neon --no-progress
	@$(PHP) vendor/bin/phpstan analyse -c phpstan-themes.neon --no-progress
	@npx --no-install tsc -p tsconfig.json
	@$(PHP) vendor/bin/deptrac analyse --no-progress --fail-on-uncovered
test-tooling: ## Prove that every quality tool fails on a violation
	@tests/make-help.sh
	@tests/tooling/run.sh
	@tests/tooling/hooks-test.sh
test-unit: ## PHPUnit unit suites and Vitest
	$(call stub,test-unit)
test-integration: ## wp-phpunit in Docker
	$(call stub,test-integration)
test-e2e: ## Playwright e2e against the demo (THEME=...)
	$(call require_theme)
	$(call stub,test-e2e)
demo: ## Build, start Docker, install WordPress + theme, seed, health check (THEME=...)
	$(call require_theme)
	$(call stub,demo)
health: ## Verify the running demo (THEME=...)
	$(call require_theme)
	$(call stub,health)
perf: ## Lighthouse CI and size budgets (THEME=...)
	$(call require_theme)
	$(call stub,perf)
test-matrix: ## Plugin compatibility matrix (THEME=...)
	$(call require_theme)
	$(call stub,test-matrix)
package: ## Build the installable theme zip (THEME=...)
	$(call require_theme)
	$(call stub,package)
docs: ## Generate reference docs
	$(call stub,docs)
hooks: ## Install repository git hooks (repo-local config only)
	@git config --local core.hooksPath tools/githooks
	@echo "core.hooksPath = $$(git config --local core.hooksPath)"
clean: ## Remove build output and reports
	@rm -rf artifacts/reports artifacts/dist
	@echo "cleaned artifacts/reports and artifacts/dist"
