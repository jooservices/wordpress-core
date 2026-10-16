#!/usr/bin/env bash
# Creates a ticket-scoped .env with random secrets. Never overwrites or prints secrets.
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT"
if [ -f .env ]; then echo ".env already exists: kept unchanged"; exit 0; fi

ticket="${JOOS_TICKET:-}"
if [ -z "$ticket" ]; then
  branch="$(git branch --show-current)"
  if [[ "$branch" =~ JOOS-([0-9]+) ]]; then ticket="JOOS-${BASH_REMATCH[1]}"; fi
fi
[[ "$ticket" =~ ^JOOS-([0-9]+)$ ]] || {
  echo "ERROR: set JOOS_TICKET=JOOS-<number> or run from a JOOS ticket branch." >&2
  exit 2
}
db_name="wp_JOOS_${BASH_REMATCH[1]}"
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
