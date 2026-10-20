#!/usr/bin/env bash
# Operate this worktree's WordPress site on the shared wp-web. The site is a folder (artifacts/wp) with its own database
# and one vhost config (scripts/docker/web-site); no container, lock or mount is created per worktree, so any number of
# worktrees run in parallel. WP-CLI and PHP tests run in the shared PHP CLI containers (docker exec), web-facing tools in
# wp-web, the browser on the Dev VM.
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd -P)"
ROOT="$(cd "$SCRIPT_DIR/../.." && pwd -P)"
action="${1:-}"
[[ -n "$action" ]] || { echo "Usage: wp-task.sh up|install|seed|seed-reset|seed-live|integration|e2e|e2e-demo|matrix|marketplace|screenshots|critical|health|demo|wp|shell|logs|down|reset|plugins-on|plugins-off|demo-plugins|i18n [args ...]" >&2; exit 2; }
shift
theme="${1:-jooservices}"
if (($# > 0)); then shift; fi

workspaces_root="$ROOT"
while [[ "$workspaces_root" != / && ( ! -f "$workspaces_root/dockers/compose.yaml" || ! -f "$workspaces_root/.env" ) ]]; do
  workspaces_root="$(dirname "$workspaces_root")"
done
[[ -f "$workspaces_root/dockers/compose.yaml" && -f "$workspaces_root/.env" ]] || {
  echo "ERROR: could not locate the Workspaces root shared Docker configuration." >&2
  exit 2
}

get_env() {
  local key="$1"
  awk -F= -v key="$key" '$1 == key { sub(/^[^=]*=/, ""); print; exit }' "$ROOT/.env"
}

if [[ ! -f "$ROOT/.env" ]]; then
  JOOS_TICKET="${JOOS_TICKET:-}" "$ROOT/docker/scripts/init-env.sh"
fi
[[ -f "$ROOT/.env" ]] || { echo "ERROR: run make init-env from a JOOS or JOOWT ticket branch first." >&2; exit 2; }

ticket="${JOOS_TICKET:-}"
if [[ -z "$ticket" ]]; then
  branch="$(git -C "$ROOT" branch --show-current)"
  if [[ "$branch" =~ (JOOS|JOOWT)-([0-9]+) ]]; then ticket="${BASH_REMATCH[1]}-${BASH_REMATCH[2]}"; fi
fi
[[ "$ticket" =~ ^((JOOS|JOOWT)-[0-9]+|staging)$ ]] || {
  echo "ERROR: run this target from a JOOS or JOOWT ticket worktree or set JOOS_TICKET." >&2
  exit 2
}
# The staging site is the project checkout itself: it must be the main checkout (not a worktree) on develop.
if [[ "$ticket" == staging ]]; then
  [[ "$(git -C "$ROOT" rev-parse --git-dir)" == "$(git -C "$ROOT" rev-parse --git-common-dir)" ]] || { echo "ERROR: staging runs only in the project checkout, not in a worktree." >&2; exit 2; }
  [[ "$(git -C "$ROOT" branch --show-current)" == develop ]] || { echo "ERROR: staging runs only from the develop branch of the project checkout." >&2; exit 2; }
fi

source "$ROOT/docker/scripts/site-lib.sh"
source "$ROOT/docker/scripts/wp-task-lock.sh"
cd "$ROOT"

acquire_shared_wp_test_lock() {
  local lock_path="$workspaces_root/.tmp/jooservices-wp-shared-test.lock"
  local owner_pid

  if [[ -n "${WP_TASK_LOCK_OWNER_PID:-}" ]]; then
    owner_pid="$(cat "$lock_path" 2>/dev/null || true)"
    if [[ "$owner_pid" == "$WP_TASK_LOCK_OWNER_PID" ]] &&
      kill -0 "$owner_pid" 2>/dev/null &&
      wp_task_lock_owner_is_ancestor "$owner_pid"; then
      return 0
    fi
    echo "ERROR: inherited shared WordPress test lock is not owned by PID ${WP_TASK_LOCK_OWNER_PID}." >&2
    return 1
  fi

  wp_task_lock_acquire "$lock_path" "$WP_TASK_LOCK_WAIT_SECONDS"
}

restore_theme_source=0
restore_sources() {
  if ((restore_theme_source)); then
    restore_theme_source=0
    rm -rf "$JOO_SITE_DIR/wp-content/themes/$theme" "$JOO_SITE_DIR/wp-content/plugins/jooservices-resume"
    rt sh "bash '$ROOT/docker/scripts/site-bootstrap.sh'" >/dev/null || echo "ERROR: failed to restore the source symlinks of the site." >&2
  fi
}
cleanup_wp_task() {
  restore_sources
  wp_task_lock_release
}
trap cleanup_wp_task EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

ensure_site() {
	local theme_dir="themes/$theme" core_link="../../packages/theme-core"
	if [[ ! -d "$theme_dir" && -d "tests/fixtures/themes/$theme" ]]; then theme_dir="tests/fixtures/themes/$theme"; fi
	[[ -d "$theme_dir" ]] || { echo "ERROR: theme source not found: themes/$theme or tests/fixtures/themes/$theme" >&2; exit 2; }
	if [[ "$theme_dir" == tests/fixtures/themes/* ]]; then core_link="../../../../packages/theme-core"; fi
	ln -sfn "$core_link" "$theme_dir/core"
  rt node tools/build-assets.mjs --root packages/theme-core
	if [[ -d "$theme_dir/assets/src" ]]; then
		rt node tools/build-assets.mjs --root "$theme_dir" --src assets/src --out assets/build
  fi
  "$ROOT/docker/scripts/shared-db.sh" create "$ticket"
  site_up
}

# Seed snapshots: the demo content is generated and seeded ONCE per (dataset, WordPress version, code that shapes the data)
# and stored as a local artifact; every other worktree imports it (database dump + cloned uploads) instead of seeding.
snap_root="$workspaces_root/artifacts/jooservices-wp-themes/snapshots"
snapshot_dir() {
  local code
  code="$(cd "$ROOT" && find tools/wxr-generator docker/scripts/seed.php docker/scripts/seed.sh plugins/jooservices-resume/src packages/theme-core/src -type f -print0 | sort -z | xargs -0 shasum -a 256 | shasum -a 256 | cut -c1-12)"
  printf '%s/%s-wp%s-%s' "$snap_root" "${DEMO_SET:-$theme}" "$(wpx core version)" "$code"
}
snapshot_import() { # $1 = snapshot dir
  wpx db reset --yes >/dev/null
  wpx db import "$1/db.sql"
  wpx search-replace "http://seed.localhost" "$SITE_URL" --all-tables --precise --quiet
  rm -rf "$JOO_SITE_DIR/wp-content/uploads"
  if [[ -d "$1/uploads" ]]; then cp -cR "$1/uploads" "$JOO_SITE_DIR/wp-content/uploads"; else mkdir -p "$JOO_SITE_DIR/wp-content/uploads"; fi
  local admin
  admin="$(wpx user list --role=administrator --field=ID </dev/null | head -1)"
  wpx user update "$admin" --user_pass="$WP_ADMIN_PASSWORD" --skip-email >/dev/null
  wpx core update-db >/dev/null
  run_migrations >/dev/null
  wpx theme activate "$theme" >/dev/null
  wpx plugin activate jooservices-resume >/dev/null 2>&1 || true
  wpx rewrite flush >/dev/null 2>&1 || true
  echo "Imported the seed snapshot $(basename "$1") (checksum $(wpx option get joo_demo_checksum | cut -c1-8))."
}
snapshot_export() { # $1 = snapshot dir
  local tmp="$1.tmp.$$"
  mkdir -p "$tmp"
  wpx search-replace "$SITE_URL" "http://seed.localhost" --all-tables --precise --export="$tmp/db.sql" --quiet
  if [[ -d "$JOO_SITE_DIR/wp-content/uploads" ]]; then cp -cR "$JOO_SITE_DIR/wp-content/uploads" "$tmp/uploads"; fi
  printf '{"wordpress":"%s","checksum":"%s","created":"%s","theme":"%s"}\n' "$(wpx core version)" "$(wpx option get joo_demo_checksum)" "$(date -u +%FT%TZ)" "$theme" > "$tmp/manifest.json"
  rm -rf "$1"; mv "$tmp" "$1"
  echo "Stored the seed snapshot $(basename "$1") in $snap_root"
}

seed_demo() {
  local dir seeded=0
  if [[ "$action" == seed-live ]]; then
    rt node tools/live-import.mjs --source "${SOURCE:-artifacts/live/jooservices.com.json}" --out artifacts/demo/live
    export DEMO_SET=live
  else
    rt node tools/wxr-generator/generate.mjs --out "artifacts/demo/$theme" >/dev/null
  fi
  dir="$(snapshot_dir)"
  if [[ ! -f "$dir/manifest.json" ]]; then
    mkdir -p "$snap_root"
    if mkdir "$dir.lock" 2>/dev/null; then   # the first worktree to need it generates the snapshot, the others wait
      trap 'rmdir "$dir.lock" 2>/dev/null; restore_sources' EXIT
      if [[ "$action" == seed-live || "$action" == seed-reset ]]; then site_script seed.sh "$theme" --reset; else site_script seed.sh "$theme"; fi
      snapshot_export "$dir"
      rmdir "$dir.lock"; trap restore_sources EXIT
      seeded=1
    else
      echo "Waiting for another worktree to generate the seed snapshot ..."
      for _ in $(seq 1 600); do [[ -f "$dir/manifest.json" ]] && break; sleep 2; done
      [[ -f "$dir/manifest.json" ]] || { echo "ERROR: the seed snapshot was not produced in time (stale lock $dir.lock?)." >&2; exit 1; }
    fi
  fi
  ((seeded)) && return 0
  # Import unless this site already holds exactly this dataset (seed-reset always starts from the pristine snapshot).
  local current wanted
  current="$(wpx option get joo_demo_checksum 2>/dev/null </dev/null || true)"
  wanted="$(sed -n 's/.*"checksum":"\([^"]*\)".*/\1/p' "$dir/manifest.json")"
  if [[ "$action" != seed-reset && "$action" != seed-live && "$current" == "$wanted" ]]; then
    echo "Demo content is up to date (checksum ${current:0:8})."
  else
    snapshot_import "$dir"
  fi
}

# Staging keeps the content the owner reviews: a database dump is taken before anything that can change it (migrations,
# a reseed). The newest 5 dumps are kept in the local artifact folder.
staging_backup() {
  local dir="$workspaces_root/artifacts/jooservices-wp-themes/staging-backups" out
  mkdir -p "$dir"
  out="$dir/wp_staging-$(date -u +%Y%m%dT%H%M%SZ).sql.gz"
  wpx db export - </dev/null | gzip > "$out"
  ls -1t "$dir"/wp_staging-*.sql.gz | tail -n +6 | while read -r old; do rm -f "$old"; done
  echo "Backed up staging to $out"
}

# Applies the pending data migrations (migrations/, see docs/guides/migrations.md) to this site.
run_migrations() { wpx eval-file "$ROOT/docker/scripts/migrate.php" "$@" </dev/null; }

staging_require_clean() {
  [[ -z "$(git -C "$ROOT" status --porcelain)" ]] || { echo "ERROR: the staging checkout has uncommitted changes; staging must show committed develop only." >&2; exit 2; }
}

health_check() {
  local values
  values="$(wpx eval-file "$ROOT/docker/scripts/health-values.php" </dev/null)"
  webx node tools/health.mjs --base "$SITE_URL" --values "$values"
}

case "$action" in
  test-migrations|integration|e2e|e2e-demo|matrix|marketplace|screenshots|critical|health)
    if [[ -z "${WP_TASK_LOCK_OWNER_PID:-}" ]]; then
      acquire_shared_wp_test_lock || exit $?
      if wp_task_run_capped "$action" "$WP_TASK_OPERATION_CAP_SECONDS" \
        env WP_TASK_LOCK_OWNER_PID="$$" bash "$ROOT/docker/scripts/wp-task.sh" "$action" "$theme" "$@"; then
        exit 0
      else
        operation_status=$?
        exit "$operation_status"
      fi
    fi
    acquire_shared_wp_test_lock || exit $?
    ;;
esac

case "$action" in
  up|install)
    ensure_site
    echo "WordPress test site ready at $SITE_URL"
    ;;
  seed|seed-reset|seed-live)
    ensure_site
    seed_demo
    ;;
  migrate)
    ensure_site
    run_migrations "$@"
    ;;
  test-migrations)
    # Idempotency proof on a worktree site (never on staging): apply the fixtures twice, forgetting in between.
    [[ "$ticket" != staging ]] || { echo "ERROR: test-migrations drops the migration records; run it in a worktree, not on staging." >&2; exit 2; }
    acquire_shared_wp_test_lock || exit $?
    ensure_site
    prefix="$(wpx db prefix </dev/null)"
    run_migrations dir="$ROOT/docker/scripts/migration-fixtures"
    wpx db query "DROP TABLE ${prefix}joo_dev_migrations" </dev/null
    run_migrations dir="$ROOT/docker/scripts/migration-fixtures"
    [[ "$(wpx option get joo_migration_fixture_sql </dev/null)" == ok && "$(wpx option get joo_migration_fixture_php </dev/null)" == ok ]]
    wpx option delete joo_migration_fixture_sql joo_migration_fixture_php </dev/null >/dev/null
    wpx db query "DROP TABLE ${prefix}joo_dev_migrations" </dev/null
    run_migrations   # restore the records of the real migrations
    echo "OK: SQL and PHP migrations applied twice without error (idempotent)."
    ;;
  staging-up)
    # Staging is seeded once and never reseeded by itself (that would wipe the content under review).
    staging_require_clean
    ensure_site
    if [[ -z "$(wpx option get joo_staging_initialized 2>/dev/null </dev/null || true)" ]]; then
      seed_demo
      wpx option update joo_staging_initialized "$(date -u +%FT%TZ)" </dev/null >/dev/null
    else
      echo "Staging already holds content: not reseeded."
    fi
    echo "Staging ready at $SITE_URL"
    ;;
  staging-update)
    # After a merge into develop: rebuild assets, bring the data up to date, clear caches, check health.
    staging_require_clean
    ensure_site
    wpx core update-db </dev/null
    if [[ "$(run_migrations status | sed -n 's/^[0-9]* applied, \([0-9]*\) pending.*/\1/p')" != 0 ]]; then staging_backup; fi
    run_migrations
    wpx cache flush </dev/null
    wpx transient delete --all </dev/null
    wpx rewrite flush </dev/null 2>/dev/null || true
    # The seed-shaped health checks (private and protected posts, SEO output) can fail on content the owner edited: advisory here.
    health_check || echo "WARNING: some health checks failed on the staging content (see above); the update itself completed."
    echo "Staging updated: $SITE_URL ($(git -C "$ROOT" rev-parse --short HEAD))"
    ;;
  staging-reseed)
    [[ "${CONFIRM:-}" == yes ]] || { echo "staging-reseed replaces the staging content with the pristine seed snapshot. A backup is taken first. Re-run with CONFIRM=yes." >&2; exit 2; }
    staging_require_clean
    ensure_site
    staging_backup
    action=seed-reset
    seed_demo
    echo "Staging reseeded at $SITE_URL"
    ;;
  integration)
    acquire_shared_wp_test_lock || exit $?
    ensure_site
    WP_PHP_CLI="${WP_PHP_VERSION:-}" JOO_TEST_THEME="${TEST_THEME:-$theme}" COVERAGE="${COVERAGE:-0}" site_script phpunit-integration.sh "$@"
    ;;
  e2e)
    acquire_shared_wp_test_lock || exit $?
    ensure_site
    E2E_THEME="$theme" rt playwright npx --no-install playwright test -c tests/e2e/playwright.config.ts "$@"
    ;;
  e2e-demo)
    acquire_shared_wp_test_lock || exit $?
    ensure_site
    site_script e2e-demo-import.sh
    ;;
  matrix)
    acquire_shared_wp_test_lock || exit $?
    ensure_site
    seed_demo
    node tools/matrix.mjs --theme "$theme" "$@"
    ;;
  marketplace)
    acquire_shared_wp_test_lock || exit $?
    ensure_site
    rt node tools/theme-check.mjs --theme "$theme"
    rt php-node tools/build-theme.mjs --theme "$theme"
    rt php-node tools/build-plugin.mjs --plugin jooservices-resume
    # The checkers must see the packaged theme and plugin, not the source symlinks.
    restore_theme_source=1
    rm -f "$JOO_SITE_DIR/wp-content/themes/$theme" "$JOO_SITE_DIR/wp-content/plugins/jooservices-resume"
    cp -R "$ROOT/artifacts/package/$theme" "$JOO_SITE_DIR/wp-content/themes/$theme"
    cp -R "$ROOT/artifacts/plugins/jooservices-resume" "$JOO_SITE_DIR/wp-content/plugins/jooservices-resume"
    site_script install-wp.sh
    for checker in theme-check plugin-check; do
      if ! wpx plugin is-installed "$checker" </dev/null; then
        wpx plugin install "$checker" </dev/null
      fi
      wpx plugin activate "$checker" </dev/null
    done
    # Run both checkers so the evidence is complete, then fail if either reported a problem.
    check_status=0
    wpx theme-check run "$theme" --format=json </dev/null || check_status=1
    wpx plugin check jooservices-resume --format=json --require=./wp-content/plugins/plugin-check/cli.php </dev/null || check_status=1
    [[ "$check_status" -eq 0 ]] || exit 1
    ;;
  screenshots)
    acquire_shared_wp_test_lock || exit $?
    ensure_site
    rt playwright node tools/screenshots.mjs --theme "$theme" --base "$SITE_URL" "$@"
    ;;
  critical)
    acquire_shared_wp_test_lock || exit $?
    ensure_site
    rt playwright node tools/critical.mjs --theme "$theme" --base "$SITE_URL" "$@"
    ;;
  health)
    acquire_shared_wp_test_lock || exit $?
    ensure_site
    health_check
    ;;
  demo)
    ensure_site
    seed_demo
    if [[ "${CAPTURE_MARKETPLACE_PREVIEW:-0}" == 1 ]]; then
      wpx search-replace 'Viet VU' 'Jordan Sample' --all-tables --precise </dev/null
      wpx search-replace 'Viet Vu' 'Jordan Sample' --all-tables --precise </dev/null
      wpx search-replace 'SGCarmart' 'Northstar Demo Co.' --all-tables --precise </dev/null
      wpx search-replace 'www.sgcarmart.com' 'northstar.example' --all-tables --precise </dev/null
      wpx search-replace 'Acme Labs' 'Harborview Demo Co.' --all-tables --precise </dev/null
      rt playwright node tools/marketplace-preview.mjs "themes/$theme/screenshot.png"
    fi
    health_check
    echo "Demo ready at $SITE_URL"
    if [[ "${CLEANUP_MARKETPLACE_PREVIEW:-0}" == 1 ]]; then
      site_reset
      CONFIRM="$ticket" "$ROOT/docker/scripts/shared-db.sh" drop "$ticket"
    fi
    ;;
  wp)
    ensure_site
    wpx "$@"
    ;;
  shell)
    ensure_site
    docker exec -it -w "$JOO_SITE_DIR" "workspaces-cli-php-${WP_PHP_CLI:-8.3}" bash
    ;;
  logs)
    docker logs -f --tail=100 "$WEB_NAME"
    ;;
  down)
    site_down
    ;;
  reset)
    [[ "${CONFIRM:-}" == yes ]] || { echo "reset removes this worktree's site (its vhost, folder artifacts/wp, uploads) and drops this ticket database; wp-web and every other service keep running. Re-run with CONFIRM=yes." >&2; exit 2; }
    site_reset
    CONFIRM="$ticket" "$ROOT/docker/scripts/shared-db.sh" drop "$ticket"
    ;;
  plugins-off)
    ensure_site
    rt sh 'for p in wordpress-seo syntaxhighlighter akismet litespeed-cache health-check wordpress-chatgpt; do wp plugin is-active "$p" 2>/dev/null && wp plugin deactivate "$p"; done; true' </dev/null
    ;;
  plugins-on)
    ensure_site
    rt sh 'for p in wordpress-seo syntaxhighlighter akismet litespeed-cache health-check wordpress-chatgpt; do wp plugin is-installed "$p" 2>/dev/null && ! wp plugin is-active "$p" 2>/dev/null && wp plugin activate "$p"; done; true' </dev/null
    ;;
  demo-plugins)
    ensure_site
    site_script demo-plugins.sh
    ;;
  i18n)
    ensure_site
    mkdir -p artifacts/i18n
    wpx i18n make-pot "$ROOT/packages/theme-core" "$ROOT/artifacts/i18n/joo-core.pot" --domain=joo-core --exclude=tests,node_modules,resources/build,languages --skip-js --quiet
    wpx i18n make-pot "$ROOT/plugins/jooservices-resume" "$ROOT/artifacts/i18n/jooservices-resume.pot" --domain=jooservices-resume --exclude=tests,node_modules,languages --skip-js --quiet
    wpx i18n make-pot "$ROOT/themes/starter" "$ROOT/artifacts/i18n/starter.pot" --domain=starter --exclude=tests,node_modules,core,languages --skip-js --quiet
    wpx i18n make-pot "$ROOT/themes/jooservices" "$ROOT/artifacts/i18n/jooservices.pot" --domain=jooservices --exclude=tests,node_modules,core,languages,assets/src --skip-js --quiet
    rt node tools/i18n.mjs
    ;;
  *)
    echo "Unsupported WordPress task: $action" >&2
    exit 2
    ;;
esac
