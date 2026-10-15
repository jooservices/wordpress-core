#!/usr/bin/env bash
# Creates .env from .env.example with random secrets. Never overwrites, never prints secrets.
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT"
if [ -f .env ]; then echo ".env already exists: kept unchanged"; exit 0; fi
rand() { python3 -c 'import secrets,string;print("".join(secrets.choice(string.ascii_letters+string.digits) for _ in range(24)))'; }
cp .env.example .env
chmod 600 .env
for key in DB_PASSWORD DB_ROOT_PASSWORD WP_ADMIN_PASSWORD; do
  value="$(rand)"
  KEY="$key" VALUE="$value" perl -pi -e 's/^$ENV{KEY}=.*/$ENV{KEY}=$ENV{VALUE}/' .env
done
echo ".env created (mode 600) with generated secrets"
