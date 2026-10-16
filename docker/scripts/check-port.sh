#!/usr/bin/env bash
# Fails with a hint when a host port is taken by something other than the shared wp-web container.
set -euo pipefail
port="${1:?port}"
name="${2:-service}"
web_name="${WP_WEB_NAME:-workspaces-wp-web}"
if docker ps --filter "name=^${web_name}$" --format '{{.Ports}}' | grep -q "127.0.0.1:${port}->"; then exit 0; fi
if lsof -nP -iTCP:"${port}" -sTCP:LISTEN >/dev/null 2>&1; then
  echo "ERROR: port ${port} (${name}) is already in use. Re-run with a free port, e.g. WP_SITE_URL=http://127.0.0.1:8083." >&2
  exit 1
fi
