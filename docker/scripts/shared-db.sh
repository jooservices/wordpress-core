#!/usr/bin/env bash
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd -P)"
ROOT="$(cd "$SCRIPT_DIR/../.." && pwd -P)"

usage() {
  echo "Usage: shared-db.sh create JOOS-<number>|staging | drop JOOS-<number>|staging (drop requires CONFIRM=<the same name>)" >&2
  exit 2
}

[[ $# -eq 2 ]] || usage
action="$1"
ticket="$2"
[[ "$action" == create || "$action" == drop ]] || usage
if [[ "$ticket" == staging ]]; then
  database="wp_staging"
else
  [[ "$ticket" =~ ^JOOS-([0-9]+)$ ]] || usage
  database="wp_JOOS_${BASH_REMATCH[1]}"
fi
username="$database"

if [[ "$action" == drop && "${CONFIRM:-}" != "$ticket" ]]; then
  echo "ERROR: set CONFIRM=$ticket to drop only $database and its matching database user." >&2
  exit 2
fi

workspaces_root="$ROOT"
while [[ "$workspaces_root" != / && ( ! -f "$workspaces_root/dockers/compose.yaml" || ! -f "$workspaces_root/.env" ) ]]; do
  workspaces_root="$(dirname "$workspaces_root")"
done
[[ -f "$workspaces_root/dockers/compose.yaml" && -f "$workspaces_root/.env" ]] || {
  echo "ERROR: could not locate the Workspaces root shared Docker configuration." >&2
  exit 2
}

env_value() {
  python3 - "$1" "$2" <<'PY'
import os
import stat
import sys
from pathlib import Path

path = Path(sys.argv[1])
key = sys.argv[2]
if path.is_symlink() or not path.is_file():
    raise SystemExit(f"ERROR: expected a regular ignored env file at {path}.")
if stat.S_IMODE(path.stat().st_mode) != 0o600:
    raise SystemExit(f"ERROR: env file {path} must have mode 0600.")
for raw in path.read_text(encoding="utf-8").splitlines():
    line = raw.strip()
    if not line or line.startswith("#") or "=" not in line:
        continue
    name, value = line.split("=", 1)
    if name.strip() == key:
        value = value.strip()
        if len(value) >= 2 and value[0] == value[-1] and value[0] in "'\"":
            value = value[1:-1]
        if "\n" in value or "\r" in value:
            raise SystemExit(f"ERROR: invalid value format for {key} in {path}.")
        print(value)
        break
else:
    raise SystemExit(f"ERROR: missing {key} in {path}.")
PY
}

project_env="$ROOT/.env"
database_config="$(env_value "$project_env" DB_NAME)"
database_user="$(env_value "$project_env" DB_USER)"
database_password="$(env_value "$project_env" DB_PASSWORD)"
[[ "$database_config" == "$database" && "$database_user" == "$username" ]] || {
  echo "ERROR: this worktree's DB_NAME and DB_USER must both be $database for $ticket." >&2
  exit 2
}
[[ "$database_password" =~ ^[A-Za-z0-9]{40,}$ ]] || {
  echo "ERROR: DB_PASSWORD in the ignored project .env must be a generated alphanumeric secret of at least 40 characters." >&2
  exit 2
}

root_password="$(env_value "$workspaces_root/.env" WORKSPACES_MARIADB_ROOT_PASSWORD)"
compose=(docker compose --project-directory "$workspaces_root" --env-file "$workspaces_root/.env" -f "$workspaces_root/dockers/compose.yaml" -p workspaces-shared)

if [[ "$action" == create ]]; then
  MYSQL_PWD="$root_password" "${compose[@]}" exec -T --env MYSQL_PWD mariadb mariadb --protocol=socket --user=root <<SQL
CREATE DATABASE IF NOT EXISTS \`$database\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$username'@'%' IDENTIFIED BY '$database_password';
ALTER USER '$username'@'%' IDENTIFIED BY '$database_password';
GRANT ALL PRIVILEGES ON \`$database\`.* TO '$username'@'%';
FLUSH PRIVILEGES;
SQL
  echo "Database ready: $database (user scoped to this database)."
else
  MYSQL_PWD="$root_password" "${compose[@]}" exec -T --env MYSQL_PWD mariadb mariadb --protocol=socket --user=root <<SQL
DROP DATABASE IF EXISTS \`$database\`;
DROP USER IF EXISTS '$username'@'%';
SQL
  echo "Dropped only $database and its matching database user."
fi
