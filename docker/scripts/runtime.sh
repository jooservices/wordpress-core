#!/usr/bin/env bash
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd -P)"
ROOT="$(cd "$SCRIPT_DIR/../.." && pwd -P)"
tool="${1:-}"
[[ -n "$tool" ]] || { echo "Usage: runtime.sh php|composer|node|npm|npx|playwright <command> [args ...]" >&2; exit 2; }
shift

case "$tool" in
  php|composer)
    service=php-app
    entrypoint=""
    command=("$tool" "$@")
    ;;
  node)
    service=node-runtime
    entrypoint=""
    command=("$@")
    ;;
  npm|npx)
    service=node-runtime
    entrypoint="$tool"
    command=("$@")
    ;;
  playwright)
    service=playwright-runner
    entrypoint=""
    command=("$@")
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

compose=(docker compose --project-directory "$workspaces_root" --env-file "$workspaces_root/.env" -f "$workspaces_root/dockers/compose.yaml" -p workspaces-shared --profile runtimes run --rm --no-deps --volume "$ROOT:/workspace" --workdir /workspace --user "$(id -u):$(id -g)" --env HOME=/tmp --env XDG_CACHE_HOME=/tmp/.cache)
for name in E2E_BASE_URL E2E_THEME E2E_ENGINES CI; do
  value="${!name:-}"
  if [[ -n "$value" ]]; then compose+=(--env "$name"); fi
done
if [[ -n "$entrypoint" ]]; then compose+=(--entrypoint "$entrypoint"); fi
exec "${compose[@]}" "$service" "${command[@]}"
