# JOOservices WP themes: single entry point. Compatible with GNU make 3.81 (macOS).
.DEFAULT_GOAL := help
SHELL := /bin/bash
THEME ?= jooservices
JOOS_TICKET ?= $(shell git branch --show-current | sed -nE 's/.*((JOOS|JOOWT)-[0-9]+).*/\1/p')
export JOOS_TICKET WP_VERSION WP_PHP_VERSION MATRIX_VARIANT

.PHONY: migrate test-migrations staging-up staging-update staging-reseed test-marketplace critical screenshots plugins-off plugins-on plugins-package demo-plugins seed-live new-theme seed seed-reset i18n coverage fix test-tooling install-wp help up down reset shell wp logs link-core init-env shared-db-create shared-db-drop deps lint analyse test-unit test-integration test-e2e test-e2e-demo demo-package demo health perf test-matrix package docs clean hooks release

help: ## Show this help
	@grep -E '^[a-zA-Z0-9_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN{FS=":.*?## "}{printf "  %-20s %s\n",$$1,$$2}'

define stub
	@echo "[$(1)] not implemented yet"
endef

define require_theme
	@test -d themes/$(THEME) || { echo "theme not found: themes/$(THEME)"; exit 1; }
endef

PHP := bash ./docker/scripts/runtime.sh php -d error_reporting=24575
COMPOSER := bash ./docker/scripts/runtime.sh composer
NODE := bash ./docker/scripts/runtime.sh node
NPM := bash ./docker/scripts/runtime.sh npm
NPX := bash ./docker/scripts/runtime.sh npx
PHP_NODE := bash ./docker/scripts/runtime.sh php-node

deps: ## Install locked PHP and JavaScript dependencies in the shared runtimes
	@$(COMPOSER) install --no-interaction --prefer-dist
	@$(NPM) ci --no-audit --no-fund

init-env: ## Create .env with random secrets (never overwrites)
	@./docker/scripts/init-env.sh
shared-db-create: ## Create this ticket's shared MariaDB database and scoped user
	@test -n "$(JOOS_TICKET)" || { echo "run from a JOOS/JOOWT ticket branch or pass JOOS_TICKET=<ticket-key>"; exit 2; }
	@test -f .env || JOOS_TICKET=$(JOOS_TICKET) ./docker/scripts/init-env.sh
	@bash ./docker/scripts/shared-db.sh create $(JOOS_TICKET)
shared-db-drop: ## Drop only this ticket's shared MariaDB database (CONFIRM=JOOS-<n>)
	@test -n "$(JOOS_TICKET)" || { echo "run from a JOOS ticket branch or pass JOOS_TICKET=JOOS-<n>"; exit 2; }
	@CONFIRM="$(CONFIRM)" bash ./docker/scripts/shared-db.sh drop $(JOOS_TICKET)
migrate: ## Apply pending data migrations (migrations/) to this site; MIGRATE_ARGS=status lists them
	@bash ./docker/scripts/wp-task.sh migrate $(THEME) $(MIGRATE_ARGS)
test-migrations: ## Prove the migrations are idempotent (apply, forget, apply again)
	@bash ./docker/scripts/wp-task.sh test-migrations $(THEME)
staging-up: ## Create the project's staging site (http://jooservices-wp-themes.localhost:8082) from develop; seeds once
	@JOOS_TICKET=staging bash ./docker/scripts/wp-task.sh staging-up $(THEME)
staging-update: ## After a merge into develop: rebuild, migrate data, clear caches, health check
	@JOOS_TICKET=staging bash ./docker/scripts/wp-task.sh staging-update $(THEME)
staging-reseed: ## Replace the staging content with the pristine seed snapshot (backup first; needs CONFIRM=yes)
	@CONFIRM="$(CONFIRM)" JOOS_TICKET=staging bash ./docker/scripts/wp-task.sh staging-reseed $(THEME)
up: ## Create this worktree's WordPress site on the shared wp-web, install WordPress + THEME
	$(call require_theme)
	@bash ./docker/scripts/wp-task.sh up $(THEME)
install-wp: ## Install WordPress and activate THEME (idempotent)
	$(call require_theme)
	@bash ./docker/scripts/wp-task.sh install $(THEME)
down: ## Take this worktree's site offline (removes its vhost; keeps the folder and database)
	@bash ./docker/scripts/wp-task.sh down $(THEME)
reset: ## Remove this worktree's site (vhost, folder, uploads) and drop its database (needs CONFIRM=yes)
	@CONFIRM="$(CONFIRM)" bash ./docker/scripts/wp-task.sh reset $(THEME)
shell: ## Shell in the shared PHP CLI container, in this site's folder
	@bash ./docker/scripts/wp-task.sh shell $(THEME)
wp: ## Run WP-CLI: make wp ARGS="core version"
	@bash ./docker/scripts/wp-task.sh wp $(THEME) $(ARGS)
logs: ## Follow the shared wp-web logs
	@bash ./docker/scripts/wp-task.sh logs $(THEME)
link-core: ## Link packages/theme-core into themes/$(THEME)/core
	$(call require_theme)
	@ln -sfn ../../packages/theme-core themes/$(THEME)/core
	@echo "themes/$(THEME)/core -> ../../packages/theme-core"
VITEST_ARGS ?= --configLoader native --no-cache --fsModuleCachePath /tmp/jooservices-wp-themes-vitest --maxWorkers=4
lint: ## PHPCS, ESLint, Stylelint, Prettier, header check
	@$(PHP) tools/check-headers.php
	@$(PHP) vendor/bin/phpcs
	@$(NPX) --no-install eslint .
	@$(NPX) --no-install stylelint "**/*.css" --allow-empty-input
	@$(NPX) --no-install prettier --ignore-path .prettierignore --check .
fix: ## Auto-fix PHP (phpcbf), CSS (stylelint) and formatting (prettier)
	-@$(PHP) vendor/bin/phpcbf
	-@$(NPX) --no-install stylelint "**/*.css" --fix --allow-empty-input
	@$(NPX) --no-install prettier --ignore-path .prettierignore --write . >/dev/null
analyse: ## PHPStan (core level 8 on PHP 8.1, others level 6), tsc checkJs, deptrac
	@$(PHP) vendor/bin/phpstan analyse -c phpstan-core.neon --no-progress --memory-limit=1G
	@$(PHP) vendor/bin/phpstan analyse -c phpstan-themes.neon --no-progress --memory-limit=1G
	@$(NPX) --no-install tsc -p tsconfig.tools.json
	@if ls packages/*/resources/js/*.js themes/*/assets/src/js/*.js >/dev/null 2>&1; then $(NPX) --no-install tsc -p tsconfig.json; fi
	@$(PHP) vendor/bin/deptrac analyse --no-progress --fail-on-uncovered
test-tooling: ## Prove that every quality tool fails on a violation
	@tests/make-help.sh
	@tests/tooling/run.sh
	@tests/tooling/hooks-test.sh
test-unit: ## PHPUnit unit suites and Vitest (COVERAGE=1: coverage with thresholds, PHP part runs in Docker with pcov)
	@if [ "$(COVERAGE)" = "1" ]; then $(MAKE) --no-print-directory coverage; else $(PHP) vendor/bin/phpunit && $(PHP_NODE) node_modules/vitest/vitest.mjs run $(VITEST_ARGS); fi
coverage: ## Merged unit + integration coverage (pcov in Docker) with the threshold gate
	@rm -rf artifacts/reports/cov artifacts/reports/unit && mkdir -p artifacts/reports/cov artifacts/reports/unit
	@$(PHP) -d pcov.enabled=1 -d pcov.directory=/workspace vendor/bin/phpunit -c phpunit.xml.dist --do-not-cache-result --coverage-php artifacts/reports/cov/unit.cov
	@COVERAGE=1 TEST_THEME="$(TEST_THEME)" bash ./docker/scripts/wp-task.sh integration $(THEME) >/dev/null
	@$(PHP) -d memory_limit=1G vendor/bin/phpcov merge --clover artifacts/reports/unit/clover.xml --html artifacts/reports/unit/html --text artifacts/reports/unit/summary.txt artifacts/reports/cov >/dev/null
	@./tools/coverage-gate.sh artifacts/reports/unit/clover.xml
	@$(PHP_NODE) node_modules/vitest/vitest.mjs run --coverage $(VITEST_ARGS)
test-integration: ## wp-phpunit against this worktree's site (WP_VERSION=6.6 for the minimum version)
	$(call require_theme)
	@bash ./docker/scripts/wp-task.sh integration $(THEME) $(ARGS)
test-e2e: ## Playwright E2E on the Dev VM against the shared test site (THEME=..., ARGS="specs/shared")
	$(call require_theme)
	@bash ./docker/scripts/wp-task.sh e2e $(THEME) $(ARGS)
test-e2e-demo: demo-package ## Import, diff, idempotence, and rollback on a clean shared WordPress site
	$(call require_theme)
	@bash ./docker/scripts/wp-task.sh e2e-demo $(THEME)
demo-package: ## Build the licence-clean Tier B buyer demo import ZIP
	@$(PHP_NODE) tools/demo-package.mjs
test-marketplace: ## WordPress Theme Check and Plugin Check on this worktree's site
	$(call require_theme)
	@bash ./docker/scripts/wp-task.sh marketplace $(THEME)
screenshots: ## Capture showcase screenshots of this worktree's site (browser on the Dev VM)
	$(call require_theme)
	@bash ./docker/scripts/wp-task.sh screenshots $(THEME) $(ARGS)
critical: ## Regenerate the critical CSS from the running demo (THEME=...)
	$(call require_theme)
	@bash ./docker/scripts/wp-task.sh critical $(THEME) $(ARGS)
new-theme: ## Scaffold a theme from the starter: make new-theme NAME=acme [TITLE="Acme"]
	@$(NODE) tools/new-theme.mjs --name=$(NAME) $(if $(TITLE),--title="$(TITLE)") $(if $(FROM),--from=$(FROM))
seed: ## Generate and import the deterministic demo content (idempotent; THEME=...)
	$(call require_theme)
	@bash ./docker/scripts/wp-task.sh seed $(THEME)
seed-reset: ## Remove only demo content and import it again (THEME=...)
	$(call require_theme)
	@bash ./docker/scripts/wp-task.sh seed-reset $(THEME)
seed-live: ## Replace the demo with the public content of a live site (needs artifacts/live/<site>.json; see docs/guides/live-content.md)
	$(call require_theme)
	@SOURCE=$(or $(SOURCE),artifacts/live/jooservices.com.json) bash ./docker/scripts/wp-task.sh seed-live $(THEME)
demo-plugins: ## Install the plugins jooservices.com uses (docker/demo-plugins.json)
	@bash ./docker/scripts/wp-task.sh demo-plugins $(THEME)
plugins-package: ## Build the zips of the plugins (artifacts/package)
	@$(PHP_NODE) tools/build-plugin.mjs --plugin all
plugins-off: ## Deactivate the mirror plugins (Yoast, SyntaxHighlighter...): the e2e specs and `make health` assume a plain site
	@bash ./docker/scripts/wp-task.sh plugins-off $(THEME)
plugins-on: ## Activate the mirror plugins again (demo-plugins)
	@bash ./docker/scripts/wp-task.sh plugins-on $(THEME)
demo: ## Build, start Docker, install WordPress + theme, seed, health check (THEME=...)
	$(call require_theme)
	@bash ./docker/scripts/wp-task.sh demo $(THEME)
capture-marketplace-preview: ## Capture the sanitized preview, then remove this ticket's test site/database (THEME=...)
	$(call require_theme)
	@CAPTURE_MARKETPLACE_PREVIEW=1 CLEANUP_MARKETPLACE_PREVIEW=1 bash ./docker/scripts/wp-task.sh demo $(THEME)
health: ## Verify the running demo (THEME=...)
	$(call require_theme)
	@bash ./docker/scripts/wp-task.sh health $(THEME)
perf: ## Lighthouse CI and size budgets (THEME=...)
	$(call require_theme)
	@$(NODE) tools/perf.mjs --theme $(THEME)
test-matrix: ## Plugin and runtime compatibility matrix (THEME=..., WP_VERSION=..., WP_PHP_VERSION=...)
	$(call require_theme)
	@bash ./docker/scripts/wp-task.sh matrix $(THEME) $(ARGS)
package: ## Build the installable theme zip (THEME=...)
	$(call require_theme)
	@$(NODE) tools/theme-check.mjs --theme $(THEME)
	@$(NODE) tools/build-theme.mjs --theme $(THEME) $(if $(filter jooservices,$(THEME)),--include-plugin jooservices-resume)
release: ## Build ThemeForest artifacts (THEME=..., WP=..., TAG=1 for local tags)
	$(call require_theme)
	@node tools/release-runner.mjs --theme $(THEME) $(if $(WP),--wp $(WP)) $(if $(TAG),--tag)
i18n: ## Make POT files (WP-CLI in Docker), build the Vietnamese .po/.mo and fail on untranslated strings
	@bash ./docker/scripts/wp-task.sh i18n $(THEME)
docs: ## Generate compatibility and API reference docs
	@$(NODE) tools/docs-compat.mjs
	@$(NODE) tools/docs-reference.mjs
docs-check: ## Check generated API references and buyer documentation links
	@$(NODE) tools/docs-reference.mjs --check
	@$(NODE) tools/docs-check.mjs
hooks: ## Install repository git hooks (repo-local config only)
	@git config --local core.hooksPath tools/githooks
	@echo "core.hooksPath = $$(git config --local core.hooksPath)"
clean: ## Remove build output and reports
	@rm -rf artifacts/reports artifacts/dist
	@echo "cleaned artifacts/reports and artifacts/dist"
