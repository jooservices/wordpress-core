# JOOservices WP themes: single entry point. Compatible with GNU make 3.81 (macOS).
.DEFAULT_GOAL := help
SHELL := /bin/bash
THEME ?= jooservices
export JOOWT_PROJECT ?= jooservices-wp-themes


.PHONY: seed seed-reset i18n coverage fix test-tooling install-wp help up down reset shell wp logs link-core init-env lint analyse test-unit test-integration test-e2e demo health perf test-matrix package docs clean hooks

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
fix: ## Auto-fix PHP (phpcbf), CSS (stylelint) and formatting (prettier)
	-@$(PHP) vendor/bin/phpcbf
	-@npx --no-install stylelint "**/*.css" --fix --allow-empty-input
	@npx --no-install prettier --ignore-path .prettierignore --write . >/dev/null
analyse: ## PHPStan (core level 8, others level 6), tsc checkJs, deptrac
	@$(PHP) vendor/bin/phpstan analyse -c phpstan-core.neon --no-progress --memory-limit=1G
	@$(PHP) vendor/bin/phpstan analyse -c phpstan-themes.neon --no-progress --memory-limit=1G
	@npx --no-install tsc -p tsconfig.tools.json
	@if ls packages/*/resources/js/*.js themes/*/assets/src/js/*.js >/dev/null 2>&1; then npx --no-install tsc -p tsconfig.json; fi
	@$(PHP) vendor/bin/deptrac analyse --no-progress --fail-on-uncovered
test-tooling: ## Prove that every quality tool fails on a violation
	@tests/make-help.sh
	@tests/tooling/run.sh
	@tests/tooling/hooks-test.sh
test-unit: ## PHPUnit unit suites and Vitest (COVERAGE=1: coverage with thresholds, PHP part runs in Docker with pcov)
	@if [ "$(COVERAGE)" = "1" ]; then $(MAKE) --no-print-directory coverage; else $(PHP) vendor/bin/phpunit && npx --no-install vitest run; fi
coverage: ## Merged unit + integration coverage (pcov in Docker) with the threshold gate
	@rm -rf artifacts/reports/cov artifacts/reports/unit && mkdir -p artifacts/reports/cov artifacts/reports/unit
	@$(COMPOSE) exec -T -u www-data wordpress php -d pcov.enabled=1 -d pcov.directory=/workspace -d error_reporting=24575 /workspace/vendor/bin/phpunit -c /workspace/phpunit.xml.dist --do-not-cache-result --coverage-php /workspace/artifacts/reports/cov/unit.cov
	@$(COMPOSE) exec -T -u www-data -e COVERAGE=1 -e JOO_TEST_THEME=$(TEST_THEME) wordpress bash /scripts/phpunit-integration.sh >/dev/null
	@$(COMPOSE) exec -T -u www-data wordpress php -d error_reporting=24575 /workspace/vendor/bin/phpcov merge --clover /workspace/artifacts/reports/unit/clover.xml --html /workspace/artifacts/reports/unit/html --text /workspace/artifacts/reports/unit/summary.txt /workspace/artifacts/reports/cov >/dev/null
	@./tools/coverage-gate.sh artifacts/reports/unit/clover.xml
	@npx --no-install vitest run --coverage
test-integration: ## wp-phpunit in Docker (WP_VERSION=6.6 for the minimum version on an isolated stack)
	@$(COMPOSE) exec -T -u www-data -e JOO_TEST_THEME=$(TEST_THEME) wordpress bash /scripts/phpunit-integration.sh $(ARGS)
test-e2e: ## Playwright e2e against the running demo (THEME=..., ARGS="specs/shared")
	$(call require_theme)
	@curl -fsS -o /dev/null $${E2E_BASE_URL:-http://127.0.0.1:8080}/ || { echo "demo not reachable: run make up first"; exit 1; }
	@E2E_THEME=$(THEME) E2E_BASE_URL=$${E2E_BASE_URL:-http://127.0.0.1:8080} npx --no-install playwright test -c tests/e2e/playwright.config.ts $(ARGS)
seed: ## Generate and import the deterministic demo content (idempotent; THEME=...)
	$(call require_theme)
	@node tools/wxr-generator/generate.mjs --out artifacts/demo/$(THEME) >/dev/null
	@$(COMPOSE) exec -T -u www-data wordpress bash /scripts/seed.sh $(THEME)
seed-reset: ## Remove only demo content and import it again (THEME=...)
	$(call require_theme)
	@node tools/wxr-generator/generate.mjs --out artifacts/demo/$(THEME) >/dev/null
	@$(COMPOSE) exec -T -u www-data wordpress bash /scripts/seed.sh $(THEME) --reset
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
i18n: ## Make POT files (WP-CLI in Docker), build the Vietnamese .po/.mo and fail on untranslated strings
	@mkdir -p artifacts/i18n
	@$(COMPOSE) exec -T -u www-data wordpress wp i18n make-pot /workspace/packages/theme-core /workspace/artifacts/i18n/joo-core.pot --domain=joo-core --exclude=tests,node_modules,resources/build,languages --skip-js --quiet
	@$(COMPOSE) exec -T -u www-data wordpress wp i18n make-pot /workspace/plugins/jooservices-content /workspace/artifacts/i18n/jooservices-content.pot --domain=jooservices-content --exclude=tests,node_modules,languages --skip-js --quiet
	@$(COMPOSE) exec -T -u www-data wordpress wp i18n make-pot /workspace/themes/starter /workspace/artifacts/i18n/starter.pot --domain=starter --exclude=tests,node_modules,core,languages --skip-js --quiet
	@node tools/i18n.mjs
docs: ## Generate reference docs (compatibility matrix; more generators land with their stories)
	@node tools/docs-compat.mjs
hooks: ## Install repository git hooks (repo-local config only)
	@git config --local core.hooksPath tools/githooks
	@echo "core.hooksPath = $$(git config --local core.hooksPath)"
clean: ## Remove build output and reports
	@rm -rf artifacts/reports artifacts/dist
	@echo "cleaned artifacts/reports and artifacts/dist"
