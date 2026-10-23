#!/usr/bin/env bash
# Regression: JOOWP ticket worktrees must pass the ticket derivation used by make perf (boot-cost, init-env, wp-task).
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd -P)"
tmp="$(mktemp -d)"
trap 'rm -rf -- "$tmp"' EXIT

mkdir -p "$tmp/docker/scripts"
cp "$ROOT/Makefile" "$tmp/Makefile"
cp "$ROOT/docker/scripts/init-env.sh" "$tmp/docker/scripts/init-env.sh"
printf 'DB_NAME=x\nDB_USER=x\nDB_PASSWORD=x\nWP_ADMIN_PASSWORD=x\n' > "$tmp/.env.example"
git -C "$tmp" init -q -b JOOWP-304-core-make-perf-theme-jooservices-cannot

printf 'print-ticket:\n\t@echo $(JOOS_TICKET)\n' > "$tmp/print.mk"
ticket="$(cd "$tmp" && env -u JOOS_TICKET make --no-print-directory -s -f Makefile -f print.mk print-ticket 2>/dev/null)"
if [[ "$ticket" != JOOWP-304 ]]; then
  echo "FAIL: Makefile derived '$ticket' instead of JOOWP-304 from a JOOWP branch." >&2
  exit 1
fi

(cd "$tmp" && env -u JOOS_TICKET bash docker/scripts/init-env.sh >/dev/null)
if ! grep -q '^DB_NAME=wp_JOOWP_304$' "$tmp/.env"; then
  echo "FAIL: init-env.sh did not create the JOOWP-304 ticket database name." >&2
  exit 1
fi

# wp-task.sh needs the shared Workspaces runtime, so its ticket gate is checked on the script itself.
if ! grep -q '(JOOS|JOOWT|JOOWP)-\[0-9\]+|staging' "$ROOT/docker/scripts/wp-task.sh" ||
  ! grep -q '(JOOS|JOOWT|JOOWP)-(\[0-9\]+)\$' "$ROOT/docker/scripts/shared-db.sh"; then
  echo "FAIL: wp-task.sh or shared-db.sh does not accept JOOWP tickets." >&2
  exit 1
fi
if ! grep -Fq '((JOOS|JOOWT|JOOWP)-[0-9]+)' "$ROOT/docker/scripts/perf-vm.sh" ||
  ! grep -Fq 'export LIGHTHOUSE_CDP_HOST=host.docker.internal' "$ROOT/docker/scripts/perf-vm.sh" ||
  ! grep -Fq 'export LIGHTHOUSE_CDP_PORT="$local_port"' "$ROOT/docker/scripts/perf-vm.sh"; then
  echo "FAIL: perf-vm.sh does not route JOOWP perf runs to the Dev VM browser." >&2
  exit 1
fi
echo "ok:   JOOWP tickets pass the make perf route (Makefile, init-env, wp-task, shared-db, perf-vm)"
