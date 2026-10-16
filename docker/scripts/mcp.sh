#!/usr/bin/env bash
# MCP demo: `mcp.sh env` adds the two secrets to .env, `mcp.sh up` builds and starts everything, `mcp.sh down` stops the server.
# Runs on the host (repository root). Secrets are never printed.
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT"
COMPOSE="./docker/scripts/compose.sh"
rand() { python3 -c 'import secrets,string;print("".join(secrets.choice(string.ascii_letters+string.digits) for _ in range(40)))'; }
env_value() { grep -E "^$1=" .env | head -1 | cut -d= -f2-; }

ensure_env() {
  [ -f .env ] || ./docker/scripts/init-env.sh
  for key in MCP_WORDPRESS_TOKEN MCP_AUTH_SECRET; do
    if ! grep -qE "^${key}=.+" .env; then
      sed -i.bak "/^${key}=/d" .env && rm -f .env.bak
      printf '%s=%s\n' "$key" "$(rand)" >>.env
      echo "added ${key} to .env"
    fi
  done
  if ! grep -qE '^MCP_PORT=' .env; then echo 'MCP_PORT=3000' >>.env; fi
}

case "${1:-}" in
  env) ensure_env ;;
  up)
    ensure_env
    command -v node >/dev/null || { echo "node is required on the host" >&2; exit 1; }
    # The WordPress container must exist to run composer, and again to pick up the plugin mount.
    $COMPOSE up -d --wait wordpress >/dev/null
    node tools/build-plugin.mjs --plugin wordpress-chatgpt --no-zip
    node tools/build-plugin.mjs --plugin jooservices-resume --no-zip
    # Compose adds the plugin mount on (re)creation; recreate once when the container does not see the plugin yet.
    $COMPOSE up -d --wait wordpress >/dev/null
    if ! $COMPOSE exec -T wordpress test -f /var/www/html/wp-content/plugins/wordpress-chatgpt/wordpress-chatgpt.php </dev/null; then
      $COMPOSE up -d --force-recreate --wait wordpress >/dev/null
    fi
    $COMPOSE exec -T -u www-data wordpress wp plugin activate wordpress-chatgpt jooservices-resume </dev/null
    $COMPOSE exec -T -u www-data -e MCP_WORDPRESS_TOKEN="$(env_value MCP_WORDPRESS_TOKEN)" wordpress wp eval-file /scripts/mcp-seed.php --use-include </dev/null
    MCP_WORDPRESS_TOKEN="$(env_value MCP_WORDPRESS_TOKEN)" MCP_AUTH_SECRET="$(env_value MCP_AUTH_SECRET)" MCP_PORT="$(env_value MCP_PORT)" \
      $COMPOSE --profile mcp up -d --build --wait mcp
    node tools/mcp-smoke.mjs
    ;;
  down) $COMPOSE --profile mcp stop mcp && $COMPOSE --profile mcp rm -f mcp ;;
  *) echo "usage: mcp.sh env | up | down" >&2; exit 2 ;;
esac
