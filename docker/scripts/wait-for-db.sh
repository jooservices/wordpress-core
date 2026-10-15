#!/usr/bin/env bash
# Waits until the database accepts connections (runs inside the wordpress container).
set -euo pipefail
timeout="${1:-120}"
for i in $(seq 1 "$timeout"); do
  if php -r '$m=@new mysqli(getenv("WORDPRESS_DB_HOST"),getenv("WORDPRESS_DB_USER"),getenv("WORDPRESS_DB_PASSWORD"),getenv("WORDPRESS_DB_NAME")); exit($m->connect_errno?1:0);'; then
    echo "database ready"; exit 0
  fi
  sleep 1
done
echo "ERROR: database did not become ready within ${timeout}s" >&2
exit 1
