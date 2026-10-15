#!/usr/bin/env bash
# Fails with a hint when a host port is taken by something that is not this project's stack.
set -euo pipefail
port="${1:?port}"
name="${2:-service}"
if docker ps --filter "label=com.joowt.project=${JOOWT_PROJECT:-jooservices-wp-themes}" --format '{{.Ports}}' | grep -q "127.0.0.1:${port}->"; then exit 0; fi
if lsof -nP -iTCP:"${port}" -sTCP:LISTEN >/dev/null 2>&1; then
  echo "ERROR: port ${port} (${name}) is already in use. Re-run with a free port, e.g. WP_PORT=8081 (and set WP_SITE_URL accordingly)." >&2
  exit 1
fi
