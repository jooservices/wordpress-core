#!/usr/bin/env bash
# Runs a Playwright command on the Dev VM (ssh dev) against the shared WordPress test site, through a reverse tunnel
# of the site port. The VM runs the browsers; Docker never does. Files travel as tar over ssh (the VM has no rsync). The shared WordPress lock must be held by the caller.
# Usage: e2e-vm.sh <command> [args ...]   Environment: E2E_VM_PULL = extra repo paths to copy back (space separated).
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd -P)"
ROOT="$(cd "$SCRIPT_DIR/../.." && pwd -P)"
(($# > 0)) || { echo "Usage: e2e-vm.sh <command> [args ...]" >&2; exit 2; }

get_env() { awk -F= -v key="$1" '$1 == key { sub(/^[^=]*=/, ""); print; exit }' "$ROOT/.env"; }
site_url="${WP_SITE_URL:-$(get_env WP_SITE_URL)}"
port="$(bash "$SCRIPT_DIR/site-url.sh" port "$site_url")"
remote='projects/jooservices-wp-themes'   # one folder on the VM; E2E runs are serialized by the WordPress lock
ssh_opts=(-o BatchMode=yes -o ConnectTimeout=10)
control="/tmp/e2evm-$$.ctl"   # unix socket paths are limited to about 100 characters
mkdir -p "$ROOT/.tmp"

ssh "${ssh_opts[@]}" dev true || { echo "ERROR: the Dev VM is not reachable (ssh dev)." >&2; exit 2; }
rm -f "$control"
ssh "${ssh_opts[@]}" -M -S "$control" -f -N -o ExitOnForwardFailure=yes -R "$port:127.0.0.1:$port" dev
close_tunnel() { ssh -S "$control" -O exit dev >/dev/null 2>&1 || true; rm -f "$control"; }
trap close_tunnel EXIT

remote_home="$(ssh "${ssh_opts[@]}" dev 'printf %s "$HOME"')"
ssh "${ssh_opts[@]}" dev "mkdir -p ~/$remote"
# The VM has no rsync: replace the sources with tar over ssh, keeping node_modules, artifacts and the lock hash.
ssh "${ssh_opts[@]}" dev "cd ~/$remote && find . -mindepth 1 -maxdepth 1 ! -name node_modules ! -name artifacts ! -name .lockhash -exec rm -rf {} +"
tar -C "$ROOT" -czf - --exclude=.git --exclude=node_modules --exclude=vendor --exclude=artifacts --exclude=.tmp --exclude=.env \
  --exclude='*.zip' . | ssh "${ssh_opts[@]}" dev "tar -xzf - -C ~/$remote"

# Credentials travel on stdin only; the VM gets a minimal .env (the specs read the admin login from it).
env_lines=()
for name in E2E_BASE_URL E2E_THEME E2E_ENGINES E2E_ALLOW_CONSOLE CI WP_SITE_URL MATRIX_VARIANT MATRIX_PLUGIN MATRIX_PATHS MATRIX_CLS PLAYWRIGHT_JSON_OUTPUT_NAME; do
  value="${!name:-}"
  [[ -n "$value" ]] || continue
  value="${value//\/workspace\//$remote_home/$remote/}"
  env_lines+=("export $name=$(printf '%q' "$value")")
done
quoted="$(printf '%q ' "$@")"
status=0
ssh "${ssh_opts[@]}" dev "bash -s" <<REMOTE || status=$?
set -euo pipefail
cd ~/$remote
umask 077
printf 'WP_ADMIN_USER=%s\nWP_ADMIN_PASSWORD=%s\nWP_SITE_URL=%s\n' '$(get_env WP_ADMIN_USER)' '$(get_env WP_ADMIN_PASSWORD)' '$site_url' > .env
lock_hash="\$(sha256sum package-lock.json | cut -d' ' -f1)"
if [ ! -d node_modules ] || [ "\$(cat .lockhash 2>/dev/null)" != "\$lock_hash" ]; then
  npm ci --no-audit --no-fund >/dev/null
  echo "\$lock_hash" > .lockhash
fi
$(printf '%s\n' "${env_lines[@]+"${env_lines[@]}"}")
set +e
$quoted
code=\$?
rm -f .env
exit \$code
REMOTE

mkdir -p "$ROOT/artifacts"
pull() { ssh "${ssh_opts[@]}" dev "cd ~/$remote && [ -e '$1' ] && tar -czf - '$1'" 2>/dev/null | tar -xzf - -C "$ROOT" 2>/dev/null || true; }
pull artifacts
for path in ${E2E_VM_PULL:-}; do pull "$path"; done
exit "$status"
