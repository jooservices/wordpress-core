#!/usr/bin/env bash
# Runs a tool in the shared Workspaces CLI runtimes (project workspaces-cli-shared) with this checkout mounted at
# /workspace. Nothing stays running: the container is removed afterwards.
#   php|composer|wp   PHP 8.5 image (WP_PHP_CLI=8.3 selects the 8.3 image)
#   php-node          Node 24 in the PHP image (tools that need PHP and Node in one process)
#   node|npm|npx      the same PHP image (Node 24, npm, npx are in it)
#   playwright        Playwright with the browser on the Dev VM (see e2e-vm.sh), not in Docker
#   e2e               the php-8.5-e2e service (shares the wp-web network); used by e2e-vm.sh
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd -P)"
ROOT="$(cd "$SCRIPT_DIR/../.." && pwd -P)"
tool="${1:-}"
[[ -n "$tool" ]] || { echo "Usage: runtime.sh php|composer|wp|php-node|node|npm|npx|playwright <command> [args ...]" >&2; exit 2; }
shift

php_service="php-${WP_PHP_CLI:-8.5}-app"
case "$tool" in
  php | composer | wp)
    service="$php_service"
    command=("$tool" "$@")
    ;;
  php-node)
    service="$php_service"
    command=(node "$@")
    ;;
  node)
    service="$php_service"
    command=(node "$@")
    ;;
  npm | npx)
    service="$php_service"
    command=("$tool" "$@")
    ;;
  e2e)
    service=php-8.5-e2e
    command=("$@")
    ;;
  playwright)
    exec bash "$SCRIPT_DIR/e2e-vm.sh" "$@"
    ;;
  *)
    echo "Unsupported shared runtime: $tool" >&2
    exit 2
    ;;
esac

workspaces_root="$ROOT"
while [[ "$workspaces_root" != / && ( ! -f "$workspaces_root/dockers/compose.yaml" || ! -f "$workspaces_root/.env" ) ]]; do
  workspaces_root="$(dirname "$workspaces_root")"
done
[[ -f "$workspaces_root/dockers/compose.yaml" && -f "$workspaces_root/.env" ]] || {
  echo "ERROR: could not locate the Workspaces root shared Docker configuration." >&2
  exit 2
}

version="${service#php-}"; version="${version%-app}"; [[ "$service" != php-8.5-e2e ]] || version=8.5
if [[ "$service" == php-* ]] && ! docker image inspect "workspaces/php-app:$version" >/dev/null 2>&1; then
  "$workspaces_root/scripts/docker/env-run" --cli --profile cli build "$service" >&2
fi

compose=("$workspaces_root/scripts/docker/env-run" --cli --profile cli run --rm --no-deps --volume "$ROOT:/workspace" --workdir /workspace --user "$(id -u):$(id -g)" --env HOME=/tmp --env XDG_CACHE_HOME=/tmp/.cache --env TMPDIR=/tmp --env NPM_CONFIG_UPDATE_NOTIFIER=false)
for name in E2E_BASE_URL E2E_THEME E2E_ENGINES CI WP_VERSION WP_PHP_VERSION MATRIX_VARIANT MATRIX_PLUGIN MATRIX_PATHS MATRIX_CLS PLAYWRIGHT_JSON_OUTPUT_NAME PW_TEST_CONNECT_WS_ENDPOINT; do
  value="${!name:-}"
  if [[ -n "$value" ]]; then compose+=(--env "$name"); fi
done
export COMPOSE_PROGRESS=quiet
exec "${compose[@]}" "$service" "${command[@]}"
