#!/usr/bin/env bash
# Regression coverage for ticket keys accepted by the integration tooling.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd -P)"
tmp="$(mktemp -d)"
trap 'rm -rf -- "$tmp"' EXIT

fail() {
  echo "FAIL: $*" >&2
  exit 1
}

prepare_workspace() {
  local dir="$1" branch="$2"
  mkdir -p "$dir/docker/scripts" "$dir/dockers" "$dir/themes/jooservices"
  cp "$ROOT/Makefile" "$dir/Makefile"
  cp "$ROOT/docker/scripts/init-env.sh" "$dir/docker/scripts/init-env.sh"
  cp "$ROOT/docker/scripts/shared-db.sh" "$dir/docker/scripts/shared-db.sh"
  cp "$ROOT/docker/scripts/wp-task.sh" "$dir/docker/scripts/wp-task.sh"
  printf 'DB_NAME=x\nDB_USER=x\nDB_PASSWORD=x\nWP_ADMIN_PASSWORD=x\n' > "$dir/.env.example"
  printf 'services: {}\n' > "$dir/dockers/compose.yaml"
  # The wp-task probe exits before these stubs are used; the lock release runs in its exit trap.
  printf 'wp_task_lock_release() { :; }\n' > "$dir/docker/scripts/wp-task-lock.sh"
  : > "$dir/docker/scripts/site-lib.sh"
  git -C "$dir" init -q -b "$branch"
}

assert_ticket_case() {
  local ticket="$1" branch="$2"
  local database="wp_${ticket/-/_}" dir="$tmp/${ticket,,}"
  prepare_workspace "$dir" "$branch"
  printf 'print-ticket:\n\t@echo $(JOOS_TICKET)\n' > "$dir/print.mk"

  local derived
  derived="$(cd "$dir" && env -u JOOS_TICKET make --no-print-directory -s -f Makefile -f print.mk print-ticket 2>/dev/null)"
  [[ "$derived" == "$ticket" ]] || fail "Makefile derived '$derived' instead of $ticket from $branch."

  (cd "$dir" && env -u JOOS_TICKET bash docker/scripts/init-env.sh >/dev/null)
  grep -Fxq "DB_NAME=$database" "$dir/.env" || fail "init-env.sh did not create $database for $ticket."

  local task_output task_status
  set +e
  task_output="$(cd "$dir" && env -u JOOS_TICKET bash docker/scripts/wp-task.sh ticket-probe 2>&1)"
  task_status=$?
  set -e
  [[ "$task_status" -eq 2 ]] || fail "wp-task.sh ticket probe for $ticket exited $task_status, expected 2."
  grep -Fq 'Unsupported WordPress task: ticket-probe' <<<"$task_output" ||
    fail "wp-task.sh rejected $ticket before reaching its task dispatch."

  cat > "$dir/docker/scripts/wp-task.sh" <<'SH'
#!/usr/bin/env bash
printf '%s|%s|%s\n' "$JOOS_TICKET" "$1" "$2" > "$TASK_CAPTURE"
SH
  chmod +x "$dir/docker/scripts/wp-task.sh"
  (cd "$dir" && env -u JOOS_TICKET TASK_CAPTURE="$dir/task.out" make --no-print-directory -s test-integration)
  grep -Fxq "$ticket|integration|jooservices" "$dir/task.out" ||
    fail "make test-integration did not route $ticket through wp-task.sh integration."

  printf 'DB_NAME=%s\nDB_USER=%s\nDB_PASSWORD=%040d\nWORKSPACES_MARIADB_ROOT_PASSWORD=fake-root\n' \
    "$database" "$database" 0 > "$dir/.env"
  chmod 600 "$dir/.env"
  mkdir -p "$dir/bin"
  cat > "$dir/bin/docker" <<'SH'
#!/usr/bin/env bash
cat > "$SHARED_DB_SQL"
SH
  chmod +x "$dir/bin/docker"
  (cd "$dir" && PATH="$dir/bin:$PATH" SHARED_DB_SQL="$dir/shared-db.sql" \
    bash docker/scripts/shared-db.sh create "$ticket" >/dev/null)
  grep -Fq "CREATE DATABASE IF NOT EXISTS \`$database\`" "$dir/shared-db.sql" ||
    fail "shared-db.sh did not prepare the expected database for $ticket."
  echo "ok:   $ticket branch detection, database name, wp-task gate, and integration route"
}

assert_ticket_case JOOWT-1 JOOWT-1-feature
assert_ticket_case JOOS-17 JOOS-17-feature

# Explicit ticket selection must work independently of branch detection.
explicit="$tmp/explicit"
prepare_workspace "$explicit" feature-branch
(cd "$explicit" && JOOS_TICKET=JOOWT-1 bash docker/scripts/init-env.sh >/dev/null)
grep -Fxq 'DB_NAME=wp_JOOWT_1' "$explicit/.env" || fail 'explicit JOOWT-1 did not create wp_JOOWT_1.'
echo "ok:   explicit JOOWT-1 creates wp_JOOWT_1"

# Staging remains a supported explicit route and keeps its shared database name.
staging="$tmp/staging"
prepare_workspace "$staging" develop
(cd "$staging" && JOOS_TICKET=staging bash docker/scripts/init-env.sh >/dev/null)
grep -Fxq 'DB_NAME=wp_staging' "$staging/.env" || fail 'staging did not create wp_staging.'
cat > "$staging/docker/scripts/wp-task.sh" <<'SH'
#!/usr/bin/env bash
printf '%s|%s|%s\n' "$JOOS_TICKET" "$1" "$2" > "$TASK_CAPTURE"
SH
chmod +x "$staging/docker/scripts/wp-task.sh"
(cd "$staging" && TASK_CAPTURE="$staging/task.out" make --no-print-directory -s staging-up)
grep -Fxq 'staging|staging-up|jooservices' "$staging/task.out" || fail 'Makefile did not route staging-up through the staging ticket.'
printf 'DB_NAME=wp_staging\nDB_USER=wp_staging\nDB_PASSWORD=%040d\nWORKSPACES_MARIADB_ROOT_PASSWORD=fake-root\n' 0 > "$staging/.env"
chmod 600 "$staging/.env"
mkdir -p "$staging/bin"
cat > "$staging/bin/docker" <<'SH'
#!/usr/bin/env bash
cat > "$SHARED_DB_SQL"
SH
chmod +x "$staging/bin/docker"
(cd "$staging" && PATH="$staging/bin:$PATH" SHARED_DB_SQL="$staging/shared-db.sql" \
  bash docker/scripts/shared-db.sh create staging >/dev/null)
grep -Fq 'CREATE DATABASE IF NOT EXISTS `wp_staging`' "$staging/shared-db.sql" ||
  fail 'shared-db.sh did not preserve the wp_staging database.'
echo 'ok:   staging route and wp_staging database remain supported'

# Invalid ticket input continues to fail with the documented status and does not create .env.
invalid="$tmp/invalid"
prepare_workspace "$invalid" feature-branch
set +e
(cd "$invalid" && JOOS_TICKET=bad bash docker/scripts/init-env.sh >/dev/null 2>&1)
status=$?
set -e
[[ "$status" -eq 2 ]] || fail "JOOS_TICKET=bad exited $status, expected 2."
[[ ! -e "$invalid/.env" ]] || fail 'invalid ticket input created .env.'
echo 'ok:   invalid ticket still exits 2'
