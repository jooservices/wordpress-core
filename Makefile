# JOOservices WP themes: single entry point. Compatible with GNU make 3.81 (macOS).
.DEFAULT_GOAL := help
SHELL := /bin/bash
THEME ?= jooservices

.PHONY: help up down reset shell wp logs link-core init-env lint analyse test-unit test-integration test-e2e demo health perf test-matrix package docs clean hooks

help: ## Show this help
	@grep -E '^[a-zA-Z0-9_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN{FS=":.*?## "}{printf "  %-20s %s\n",$$1,$$2}'

define stub
	@echo "[$(1)] not implemented yet"
endef

define require_theme
	@test -d themes/$(THEME) || { echo "theme not found: themes/$(THEME)"; exit 1; }
endef

init-env: ## Create .env with random secrets (never overwrites)
	$(call stub,init-env)
up: ## Start the Docker stack
	$(call stub,up)
down: ## Stop the Docker stack (keeps data)
	$(call stub,down)
reset: ## Remove this project's Docker resources (needs CONFIRM=yes)
	@test "$(CONFIRM)" = "yes" || { echo "reset removes this project's containers and volumes. Re-run with CONFIRM=yes"; exit 1; }
	$(call stub,reset)
shell: ## Shell in the WordPress container
	$(call stub,shell)
wp: ## Run WP-CLI: make wp ARGS="core version"
	$(call stub,wp)
logs: ## Follow container logs (SERVICE=wordpress)
	$(call stub,logs)
link-core: ## Link packages/theme-core into themes/$(THEME)/core
	$(call require_theme)
	$(call stub,link-core)
lint: ## PHPCS, ESLint, Stylelint, Prettier, header check
	$(call stub,lint)
analyse: ## PHPStan, tsc checkJs, deptrac
	$(call stub,analyse)
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
hooks: ## Install repository git hooks
	$(call stub,hooks)
clean: ## Remove build output and reports
	@rm -rf artifacts/reports artifacts/dist
	@echo "cleaned artifacts/reports and artifacts/dist"
