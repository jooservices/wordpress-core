#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd -P)"
mode="${1:-url}"
url="${2:-${WP_SITE_URL:-}}"
if [ -z "$url" ] && [ -f "$ROOT/.env" ]; then
  url="$(awk -F= '$1 == "WP_SITE_URL" { sub(/^[^=]*=/, ""); print; exit }' "$ROOT/.env")"
fi
url="${url:-http://127.0.0.1:8082}"

python3 - "$mode" "$url" <<'PY'
import sys
from urllib.parse import urlsplit, urlunsplit

mode, raw = sys.argv[1:]
parts = urlsplit(raw)
if parts.scheme not in {"http", "https"} or not parts.hostname or parts.username or parts.password:
    raise SystemExit("ERROR: WP_SITE_URL must be an http(s) URL without credentials.")

if mode == "port":
    if parts.hostname not in {"127.0.0.1", "localhost"}:
        raise SystemExit("ERROR: WP_SITE_URL must publish only on loopback.")
    print(parts.port or (443 if parts.scheme == "https" else 80))
elif mode == "internal":
    if parts.hostname in {"127.0.0.1", "localhost"}:
        print(urlunsplit((parts.scheme, "workspaces-wp-web", parts.path.rstrip("/"), parts.query, parts.fragment)))
    else:
        print(raw.rstrip("/"))
elif mode == "url":
    print(raw.rstrip("/"))
else:
    raise SystemExit("Usage: site-url.sh [url|port|internal] [WP_SITE_URL]")
PY
