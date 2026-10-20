#!/usr/bin/env bash
# Run Lighthouse from the shared Node runtime and Chrome on the Workspaces Dev VM.
set -Eeuo pipefail

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)"
ROOT="$(cd -- "$SCRIPT_DIR/../.." && pwd -P)"
workspaces_root="$ROOT"
while [[ "$workspaces_root" != / ]] && [[ ! -f "$workspaces_root/dockers/compose.yaml" || ! -f "$workspaces_root/.env" ]]; do
  workspaces_root="$(dirname -- "$workspaces_root")"
done
[[ -f "$workspaces_root/dockers/compose.yaml" && -f "$workspaces_root/.env" ]] || {
  echo 'ERROR: Workspaces root was not found.' >&2
  exit 2
}

theme="${1:-jooservices}"
[[ $# -le 1 ]] || { echo 'Usage: perf-vm.sh [theme]' >&2; exit 2; }

ticket="${JOOS_TICKET:-$(git -C "$ROOT" branch --show-current | sed -nE 's/.*((JOOS|JOOWT)-[0-9]+).*/\1/p')}"
if [[ -n "${PERF_BASE_URL:-${E2E_BASE_URL:-}}" ]]; then
  site_url="${PERF_BASE_URL:-$E2E_BASE_URL}"
else
  [[ "$ticket" =~ ^(JOOS|JOOWT)-[0-9]+$ ]] || {
    echo 'ERROR: set PERF_BASE_URL or run from a JOOS/JOOWT worktree.' >&2
    exit 2
  }
  site_host="${JOO_SITE_HOST:-$(printf '%s' "$ticket" | tr 'A-Z' 'a-z').jooservices-wp-themes}"
  site_port="${JOO_SITE_PORT:-8082}"
  site_url="http://${site_host}.localhost:${site_port}"
fi
site_url="${site_url%/}"
site_authority="${site_url#*://}"
site_authority="${site_authority%%/*}"
site_port="${site_authority##*:}"
if [[ "$site_port" == "$site_authority" ]]; then
  case "$site_url" in https://*) site_port=443 ;; *) site_port=80 ;; esac
fi
[[ "$site_port" =~ ^[0-9]+$ ]] || { echo 'ERROR: invalid site URL port.' >&2; exit 2; }

ssh_opts=(-o BatchMode=yes -o ConnectTimeout=10 -o ServerAliveInterval=15 -o ServerAliveCountMax=3)
control="/tmp/pf-${BASHPID}"
run_id="jperf-${BASHPID}"
remote_user_data="/tmp/${run_id}"
remote_log="/tmp/${run_id}.log"
remote_pid=""
remote_port=""
local_port=""
reverse_added=0
local_forward_added=0
master_started=0
vm() { ssh -S "$control" "${ssh_opts[@]}" dev "$@"; }

cleanup() {
  local status=$?
  trap - EXIT
  if (( master_started )); then
    if [[ -n "$remote_pid" ]]; then
      vm "kill -TERM $remote_pid 2>/dev/null || true; for i in \$(seq 1 10); do kill -0 $remote_pid 2>/dev/null || break; sleep 1; done; rm -rf '$remote_user_data'; rm -f '$remote_log'" >/dev/null 2>&1 || true
    fi
    if (( local_forward_added )); then
      ssh -S "$control" -O cancel -L "$local_port:127.0.0.1:$remote_port" dev >/dev/null 2>&1 || true
    fi
    if (( reverse_added )); then
      ssh -S "$control" -O cancel -R "$site_port:127.0.0.1:$site_port" dev >/dev/null 2>&1 || true
    fi
    ssh -S "$control" -O exit dev >/dev/null 2>&1 || true
  fi
  exit "$status"
}
trap cleanup EXIT

ssh -M -S "$control" -f -N "${ssh_opts[@]}" dev
master_started=1
vm true || { echo 'ERROR: the Dev VM is not reachable.' >&2; exit 2; }

chrome_path="$(vm "cd ~/pw && node -p 'require(\"playwright\").chromium.executablePath()'")"
[[ "$chrome_path" =~ ^/[A-Za-z0-9._/-]+$ ]] || { echo 'ERROR: could not resolve the Dev VM Chromium executable.' >&2; exit 2; }

remote_ports="$(vm "ss -H -ltn | awk '{print \$4}'")"
local_ports="$(ss -H -ltn | awk '{print $4}')"
start_port=$((9320 + RANDOM % 400))
for offset in $(seq 0 399); do
  candidate=$((9320 + (start_port - 9320 + offset) % 400))
  if ! grep -Eq ":${candidate}$" <<<"$remote_ports" && ! grep -Eq ":${candidate}$" <<<"$local_ports"; then
    remote_port="$candidate"
    local_port="$candidate"
    break
  fi
done
[[ -n "$remote_port" ]] || { echo 'ERROR: no free Dev VM debug port is available.' >&2; exit 2; }

remote_status="$(vm "curl -s -o /dev/null --max-time 4 -w '%{http_code}' '$site_url/' || true")"
if [[ "$remote_status" == 000 || -z "$remote_status" ]]; then
  ssh -S "$control" -O forward -R "$site_port:127.0.0.1:$site_port" dev
  reverse_added=1
  remote_status="$(vm "curl -s -o /dev/null --max-time 4 -w '%{http_code}' '$site_url/' || true")"
fi
[[ "$remote_status" != 000 && -n "$remote_status" ]] || {
  echo "ERROR: Dev VM cannot reach the local demo at $site_url (HTTP $remote_status)." >&2
  exit 2
}

remote_pid="$(vm "setsid nohup '$chrome_path' --headless=new --no-sandbox --disable-dev-shm-usage --remote-debugging-address=127.0.0.1 --remote-debugging-port=$remote_port --user-data-dir='$remote_user_data' about:blank > '$remote_log' 2>&1 < /dev/null & echo \$!")"
[[ "$remote_pid" =~ ^[0-9]+$ ]] || { echo 'ERROR: could not start Dev VM Chromium.' >&2; exit 2; }
browser_ready=0
for _ in $(seq 1 20); do
  if vm "curl -fsS --max-time 2 http://127.0.0.1:$remote_port/json/version >/dev/null 2>&1"; then browser_ready=1; break; fi
  sleep 1
done
(( browser_ready )) || { echo 'ERROR: Dev VM Chromium did not expose its debugging endpoint.' >&2; exit 2; }

ssh -S "$control" -O forward -L "$local_port:127.0.0.1:$remote_port" dev
local_forward_added=1
curl -fsS --max-time 3 "http://127.0.0.1:$local_port/json/version" >/dev/null || {
  echo 'ERROR: local Chromium debug tunnel did not answer.' >&2
  exit 2
}

export LIGHTHOUSE_CDP_HOST=host.docker.internal
export LIGHTHOUSE_CDP_PORT="$local_port"
export E2E_BASE_URL="$site_url"
export WP_SITE_URL="$site_url"
export RUNTIME_PASS_ENV="${RUNTIME_PASS_ENV:-} LIGHTHOUSE_CDP_HOST LIGHTHOUSE_CDP_PORT E2E_BASE_URL WP_SITE_URL"
"$workspaces_root/scripts/docker/runtime" node node tools/perf.mjs --theme "$theme"
