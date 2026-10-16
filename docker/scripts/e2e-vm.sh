#!/usr/bin/env bash
# Browser E2E with the browser on the Dev VM and the tests here: nothing is copied to the VM.
#   - the VM runs `playwright run-server` (Playwright from ~/pw on the VM, same version as this project);
#   - one ssh session forwards VM port -> that server (-L) and the site port -> this Mac (-R), so the browser on the VM
#     opens the local site at http://127.0.0.1:<site port>;
#   - the test process runs in the shared php-8.5-e2e container, which shares wp-web's network, and connects to the
#     VM browser (PW_TEST_CONNECT_WS_ENDPOINT) through a small loopback forwarder (tcp-forward.mjs). Reports and traces are written here.
# The WordPress lock must be held and wp-web running (wp-task.sh does both). Usage: e2e-vm.sh <command> [args ...]
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd -P)"
ROOT="$(cd "$SCRIPT_DIR/../.." && pwd -P)"
(($# > 0)) || { echo "Usage: e2e-vm.sh <command> [args ...]" >&2; exit 2; }

get_env() { awk -F= -v key="$1" '$1 == key { sub(/^[^=]*=/, ""); print; exit }' "$ROOT/.env"; }
site_url="${WP_SITE_URL:-$(get_env WP_SITE_URL)}"
site_port="$(bash "$SCRIPT_DIR/site-url.sh" port "$site_url")"
ws_port="${E2E_VM_WS_PORT:-9300}"
ssh_opts=(-o BatchMode=yes -o ConnectTimeout=10)
control="/tmp/e2evm-$$.ctl"   # unix socket paths are limited to about 100 characters
server_started=0

cleanup() {
  ((! server_started)) || ssh "${ssh_opts[@]}" dev "pkill -f 'playwright run-server --port $ws_port'; rm -f /tmp/pw-run-server-$$.log" >/dev/null 2>&1 || true
  ssh -S "$control" -O exit dev >/dev/null 2>&1 || true
  rm -f "$control"
}
trap cleanup EXIT

ssh "${ssh_opts[@]}" dev true || { echo "ERROR: the Dev VM is not reachable (ssh dev)." >&2; exit 2; }
ssh "${ssh_opts[@]}" dev "! ss -ltn | grep -q ':$ws_port '" || { echo "ERROR: port $ws_port is already in use on the Dev VM (set E2E_VM_WS_PORT)." >&2; exit 2; }
# setsid detaches the server from the ssh session, so ssh returns at once and the server survives until cleanup.
ssh "${ssh_opts[@]}" dev "setsid nohup bash -c 'cd ~/pw && exec npx playwright run-server --port $ws_port --host 127.0.0.1' > /tmp/pw-run-server-$$.log 2>&1 < /dev/null &"
server_started=1
for _ in $(seq 1 30); do
  ssh "${ssh_opts[@]}" dev "grep -q 'Listening on' /tmp/pw-run-server-$$.log" && break
  sleep 1
done
ssh "${ssh_opts[@]}" dev "grep -q 'Listening on' /tmp/pw-run-server-$$.log" || { echo "ERROR: the Playwright server did not start on the Dev VM." >&2; exit 2; }
ssh "${ssh_opts[@]}" -M -S "$control" -f -N -o ExitOnForwardFailure=yes \
  -L "$ws_port:127.0.0.1:$ws_port" -R "$site_port:127.0.0.1:$site_port" dev

PW_TEST_CONNECT_WS_ENDPOINT="ws://127.0.0.1:$ws_port/" E2E_BASE_URL="http://127.0.0.1:$site_port" \
  bash "$SCRIPT_DIR/runtime.sh" e2e bash -c 'node docker/scripts/tcp-forward.mjs "$0" host.docker.internal "$0" & exec "${@:1}"' "$ws_port" "$@"
