#!/usr/bin/env bash
# Runs a tool in the shared, long-lived Workspaces containers (docker exec; nothing is created per call).
#   php|composer     PHP image (WP_PHP_CLI=8.3 or 8.1..8.5 selects the version; default 8.5)
#   wp               WP-CLI in the PHP image (WP_PHP_CLI, then WP_PHP_VERSION, default 8.3) in the site folder (JOO_SITE_DIR)
#   sh               a shell command in the selected PHP image in the site folder
#   php-node         Node 24 in the PHP image (tools that need PHP and Node in one process)
#   node|npm|npx     Node 24 / npm / npx (they live in the PHP image)
#   playwright       Playwright with the browser on the Dev VM (the test process is a docker exec into wp-web)
# The working directory is the repository root unless stated otherwise.
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd -P)"
ROOT="$(cd "$SCRIPT_DIR/../.." && pwd -P)"
tool="${1:-}"
[[ -n "$tool" ]] || { echo "Usage: runtime.sh php|composer|wp|sh|php-node|node|npm|npx|playwright <command> [args ...]" >&2; exit 2; }
shift

workspaces_root="$ROOT"
while [[ "$workspaces_root" != / && ( ! -f "$workspaces_root/dockers/compose.yaml" || ! -f "$workspaces_root/.env" ) ]]; do
  workspaces_root="$(dirname "$workspaces_root")"
done

# Outside Workspaces (a GitHub runner, a contributor's machine) or with JOOS_HOST_TOOLS=1 the tools that need no
# WordPress site run from the host: php, composer, node, npm, npx. The site tools (wp, sh, playwright) need the shared runtimes.
if [[ "${JOOS_HOST_TOOLS:-}" == 1 || ! -f "$workspaces_root/dockers/compose.yaml" || ! -f "$workspaces_root/.env" ]]; then
  cd "$ROOT"
  case "$tool" in
    php | composer | npm | npx) exec "$tool" "$@" ;;
    php-node | node) exec node "$@" ;;
    *) echo "ERROR: '$tool' needs the Workspaces shared runtimes (not found; JOOS_HOST_TOOLS=${JOOS_HOST_TOOLS:-unset})." >&2; exit 2 ;;
  esac
fi

export RUNTIME_PASS_ENV="E2E_BASE_URL E2E_THEME E2E_ENGINES E2E_ALLOW_CONSOLE E2E_REPORT_DIR CI WP_VERSION WP_PHP_VERSION PHP_ERROR_REPORTING MATRIX_VARIANT MATRIX_PLUGIN MATRIX_PATHS MATRIX_CLS PLAYWRIGHT_JSON_OUTPUT_NAME PW_TEST_CONNECT_WS_ENDPOINT JOO_ROOT JOO_SITE_DIR JOO_WORKSPACES JOO_TEST_THEME COVERAGE THEME WP_SITE_URL WP_ADMIN_USER WP_ADMIN_PASSWORD WP_ADMIN_EMAIL WORDPRESS_DB_HOST WORDPRESS_DB_NAME WORDPRESS_DB_USER WORDPRESS_DB_PASSWORD DEMO_SET SEED_DIR SEED_RESET SNAPSHOT_KEY WP_CLI_CACHE_DIR"
site="${JOO_SITE_DIR:-$ROOT}"
runtime="$workspaces_root/scripts/docker/runtime"
php_lang="php${WP_PHP_CLI:+@$WP_PHP_CLI}"
wp_lang="php@${WP_PHP_CLI:-${WP_PHP_VERSION:-8.3}}"
case "$tool" in
  php | composer) cd "$ROOT"; exec "$runtime" "$php_lang" "$tool" "$@" ;;
  wp) cd "$site"; exec "$runtime" "$wp_lang" wp "$@" ;;
  sh) cd "$site"; exec "$runtime" "$wp_lang" sh -c "$@" ;;
  php-node) cd "$ROOT"; exec "$runtime" "$php_lang" node "$@" ;;
  node) cd "$ROOT"; exec "$runtime" node node "$@" ;;
  npm | npx) cd "$ROOT"; exec "$runtime" node "$tool" "$@" ;;
  playwright)
    cd "$ROOT"
    exec "$workspaces_root/scripts/docker/e2e-vm" ${JOO_SITE_HOST:+--site "$JOO_SITE_HOST"} --port "${JOO_SITE_PORT:-8082}" -- "$@"
    ;;
  *) echo "Unsupported shared runtime: $tool" >&2; exit 2 ;;
esac
