#!/usr/bin/env bash
# Creates a ticket-scoped .env with random secrets. Never overwrites or prints secrets.
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT"
if [ -f .env ]; then echo ".env already exists: kept unchanged"; exit 0; fi

ticket="${JOOS_TICKET:-}"
if [ -z "$ticket" ]; then
  branch="$(git branch --show-current)"
  if [[ "$branch" =~ (JOOS|JOOWT|JOOWP)-([0-9]+) ]]; then ticket="${BASH_REMATCH[1]}-${BASH_REMATCH[2]}"; fi
fi
if [[ "$ticket" == staging ]]; then
  db_name="wp_staging"   # the project checkout's staging site (make staging-up)
else
  [[ "$ticket" =~ ^(JOOS|JOOWT|JOOWP)-([0-9]+)$ ]] || {
    echo "ERROR: set JOOS_TICKET=JOOS-<number> or JOOWT-<number> (or staging) or run from a ticket branch." >&2
    exit 2
  }
  db_name="wp_${BASH_REMATCH[1]}_${BASH_REMATCH[2]}"
fi
rand() { python3 -c 'import secrets,string;print("".join(secrets.choice(string.ascii_letters+string.digits) for _ in range(40)))'; }
cp .env.example .env
chmod 600 .env
set_value() {
  local key="$1" value="$2"
  KEY="$key" VALUE="$value" perl -pi -e 's/^$ENV{KEY}=.*/$ENV{KEY}=$ENV{VALUE}/' .env
}
set_value DB_NAME "$db_name"
set_value DB_USER "$db_name"
for key in DB_PASSWORD WP_ADMIN_PASSWORD; do
  value="$(rand)"
  set_value "$key" "$value"
done
echo ".env created for ${ticket} (mode 600); ticket database credentials generated"
