#!/usr/bin/env bash
# This worktree's WordPress site on the shared wp-web. Sourced by wp-task.sh. Needs ROOT, workspaces_root, ticket, get_env.
# Nothing is created per worktree except files: a site folder (artifacts/wp), a database on the shared MariaDB and one
# vhost config (scripts/docker/web-site). WP-CLI and tests run in the shared CLI containers, web-facing tools in wp-web.

WEB_NAME="${WP_WEB_NAME:-workspaces-wp-web}"
JOO_SITE_PORT="${JOO_SITE_PORT:-8082}"
if [[ "$ticket" == staging ]]; then JOO_SITE_HOST="jooservices-wp-themes"; else JOO_SITE_HOST="$(printf '%s' "$ticket" | tr 'A-Z' 'a-z').jooservices-wp-themes"; fi
SITE_URL="http://$JOO_SITE_HOST.localhost:$JOO_SITE_PORT"
JOO_SITE_DIR="$ROOT/artifacts/wp"
JOO_ROOT="$ROOT"
JOO_WORKSPACES="$workspaces_root"
JOO_WEB_ROOT="/var/www/html/${ROOT#"$workspaces_root"/}"   # this checkout as wp-web sees it
export JOO_SITE_HOST JOO_SITE_PORT JOO_SITE_DIR JOO_ROOT JOO_WORKSPACES JOO_WEB_ROOT
export WP_SITE_URL="$SITE_URL" WP_VERSION="${WP_VERSION:-$(get_env WP_VERSION)}" THEME="${theme:-jooservices}"
export WORDPRESS_DB_HOST=mariadb:3306 WORDPRESS_DB_NAME="$(get_env DB_NAME)" WORDPRESS_DB_USER="$(get_env DB_USER)" WORDPRESS_DB_PASSWORD="$(get_env DB_PASSWORD)"
export WP_ADMIN_USER="$(get_env WP_ADMIN_USER)" WP_ADMIN_PASSWORD="$(get_env WP_ADMIN_PASSWORD)" WP_ADMIN_EMAIL="$(get_env WP_ADMIN_EMAIL)"
export WP_CLI_CACHE_DIR="$workspaces_root/artifacts/wpcli-cache"   # core downloads shared by every worktree
export PATH="$ROOT/docker/scripts:$PATH"

rt() { bash "$ROOT/docker/scripts/runtime.sh" "$@"; }
wpx() { rt wp "$@"; }                       # WP-CLI in the shared PHP 8.3 container, in this site's folder
site_script() { local s="$1"; shift; rt sh "bash '$ROOT/docker/scripts/$s' \"\$@\"" _ "$@" 2>&1; }

# Runs a command in wp-web (Node, same network as the site, *.localhost host entries) in this checkout's folder.
webx() {
  local opts=()
  while [[ "${1:-}" == -e ]]; do opts+=(-e "$2"); shift 2; done
  docker exec -i ${opts[@]+"${opts[@]}"} -u www-data -w "$JOO_WEB_ROOT" -e HOME=/tmp -e NPM_CONFIG_UPDATE_NOTIFIER=false "$WEB_NAME" "$@"
}

site_up() {
  "$workspaces_root/scripts/docker/shared" web-up >/dev/null
  "$workspaces_root/scripts/docker/shared" runtimes-up mailpit >/dev/null
  mkdir -p "$JOO_SITE_DIR"
  rt sh "bash '$ROOT/docker/scripts/site-bootstrap.sh'"
  "$workspaces_root/scripts/docker/web-site" add "$JOO_SITE_HOST" "$JOO_SITE_DIR" >/dev/null
  rt sh "bash '$ROOT/docker/scripts/install-wp.sh'"
}
site_down() { "$workspaces_root/scripts/docker/web-site" remove "$JOO_SITE_HOST" >/dev/null; }
site_reset() { site_down; rm -rf "$JOO_SITE_DIR" "$ROOT/artifacts/wp-tests-lib"; }
