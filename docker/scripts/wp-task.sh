#!/usr/bin/env bash
# Select the current worktree on the single shared WordPress web runtime (wp-web) and
# hold a Workspaces-wide lock for the complete operation.
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd -P)"
ROOT="$(cd "$SCRIPT_DIR/../.." && pwd -P)"
action="${1:-}"
[[ -n "$action" ]] || { echo "Usage: wp-task.sh up|install|seed|seed-reset|integration|e2e|e2e-demo|matrix|marketplace|screenshots|health|demo|wp|shell|logs|down|reset|plugins-on|plugins-off [args ...]" >&2; exit 2; }
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

lock_dir="$workspaces_root/.tmp/jooservices-wp-shared-test.lock"
mkdir -p "$(dirname "$lock_dir")"
lock_owned=0
restore_theme_source=0
restore_marketplace_mount() {
  if ((restore_theme_source)); then
    restore_theme_source=0
    WEB_MARKETPLACE=0
    if ! web_start || ! webx -e "THEME=$theme" bash /scripts/install-wp.sh; then
      echo "ERROR: failed to restore the source mounts; inspect the shared WordPress container." >&2
    fi
  fi
}
cleanup_marketplace_preview() {
  if [[ "${CLEANUP_MARKETPLACE_PREVIEW:-0}" == 1 ]] && ((lock_owned)) && [[ -n "${ticket:-}" ]]; then
    web_down --reset || echo "ERROR: failed to remove the shared test web container." >&2
    CONFIRM="$ticket" "$ROOT/docker/scripts/shared-db.sh" drop "$ticket" || echo "ERROR: failed to drop the JOOS test database." >&2
    local uploads_volume="joowt-$(get_env DB_NAME)-uploads"
    if docker volume inspect "$uploads_volume" >/dev/null 2>&1; then
      docker volume rm "$uploads_volume" >/dev/null || echo "ERROR: failed to remove the JOOS test uploads volume." >&2
    fi
  fi
}
release_lock() {
  restore_marketplace_mount
  cleanup_marketplace_preview
  if ((lock_owned)) && [[ -f "$lock_dir/pid" ]] && [[ "$(<"$lock_dir/pid")" == "$$" ]]; then
    rm -f "$lock_dir/pid" "$lock_dir/worktree"
    rmdir "$lock_dir" 2>/dev/null || true
  fi
}
trap release_lock EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
for attempt in $(seq 1 900); do
  if mkdir "$lock_dir" 2>/dev/null; then
    lock_owned=1
    printf '%s\n' "$$" > "$lock_dir/pid"
    printf '%s\n' "$ROOT" > "$lock_dir/worktree"
    break
  fi
  if [[ -d "$lock_dir" && -f "$lock_dir/pid" ]]; then
    lock_pid="$(<"$lock_dir/pid")"
    if [[ "$lock_pid" =~ ^[0-9]+$ ]] && kill -0 "$lock_pid" 2>/dev/null; then
      if [[ "$attempt" == 1 ]]; then echo "Waiting for the shared WordPress test lock (PID $lock_pid)."; fi
      sleep 1
      continue
    fi
    rm -f "$lock_dir/pid" "$lock_dir/worktree"
    rmdir "$lock_dir" 2>/dev/null || true
    continue
  fi
  echo "ERROR: shared WordPress test lock exists without a readable owner; inspect $lock_dir." >&2
  exit 2
done
((lock_owned)) || { echo "ERROR: timed out waiting for the shared WordPress test lock." >&2; exit 2; }

get_env() {
  local key="$1"
  awk -F= -v key="$key" '$1 == key { sub(/^[^=]*=/, ""); print; exit }' "$ROOT/.env"
}

if [[ ! -f "$ROOT/.env" ]]; then
  JOOS_TICKET="${JOOS_TICKET:-}" "$ROOT/docker/scripts/init-env.sh"
fi
[[ -f "$ROOT/.env" ]] || { echo "ERROR: run make init-env from a JOOS ticket branch first." >&2; exit 2; }

ticket="${JOOS_TICKET:-}"
if [[ -z "$ticket" ]]; then
  branch="$(git -C "$ROOT" branch --show-current)"
  if [[ "$branch" =~ (JOOS-[0-9]+) ]]; then ticket="${BASH_REMATCH[1]}"; fi
fi
[[ "$ticket" =~ ^JOOS-[0-9]+$ ]] || {
  echo "ERROR: run this shared test target from a JOOS ticket worktree or set JOOS_TICKET." >&2
  exit 2
}

source "$ROOT/docker/scripts/web-lib.sh"
site_base() { printf '%s' "${E2E_BASE_URL:-$(bash "$ROOT/docker/scripts/site-url.sh" url "$(web_site_url)")}"; }
internal_base() { printf '%s' "${E2E_BASE_URL:-$(bash "$ROOT/docker/scripts/site-url.sh" internal "$(web_site_url)")}"; }
ensure_stack() {
  ln -sfn ../../packages/theme-core "themes/$theme/core"
  bash "$ROOT/docker/scripts/runtime.sh" node tools/build-assets.mjs --root packages/theme-core
  if [[ -d "themes/$theme/assets/src" ]]; then
    bash "$ROOT/docker/scripts/runtime.sh" node tools/build-assets.mjs --root "themes/$theme" --src assets/src --out assets/build
  fi
  "$ROOT/docker/scripts/shared-db.sh" create "$ticket"
  local port
  port="$(bash "$ROOT/docker/scripts/site-url.sh" port "$(web_site_url)")"
  "$ROOT/docker/scripts/check-port.sh" "$port" wordpress
  web_start
  webx -e "THEME=$theme" bash /scripts/install-wp.sh
}

seed_demo() {
  if [[ "$action" == seed-live ]]; then
    bash "$ROOT/docker/scripts/runtime.sh" node tools/live-import.mjs --source "${SOURCE:-artifacts/live/jooservices.com.json}" --out artifacts/demo/live
    webx -e DEMO_SET=live bash /scripts/seed.sh "$theme" --reset
    return
  fi
  bash "$ROOT/docker/scripts/runtime.sh" node tools/wxr-generator/generate.mjs --out "artifacts/demo/$theme" >/dev/null
  if [[ "$action" == seed-reset ]]; then
    webx bash /scripts/seed.sh "$theme" --reset
  else
    webx bash /scripts/seed.sh "$theme"
  fi
}

health_check() {
  local values base
  values="$(webx wp eval-file /scripts/health-values.php </dev/null)"
  base="$(internal_base)"
  E2E_BASE_URL="$base" bash "$ROOT/docker/scripts/runtime.sh" node tools/health.mjs --base "$base" --values "$values"
}

case "$action" in
  up|install)
    ensure_stack
    echo "Shared WordPress test site ready at $(bash "$ROOT/docker/scripts/site-url.sh" url "$(web_site_url)")."
    ;;
  seed|seed-reset)
    ensure_stack
    seed_demo
    ;;
  seed-live)
    ensure_stack
    seed_demo
    ;;
  integration)
    ensure_stack
    webx -e "JOO_TEST_THEME=${TEST_THEME:-$theme}" -e "COVERAGE=${COVERAGE:-0}" bash /scripts/phpunit-integration.sh "$@"
    ;;
  e2e)
    ensure_stack
    base="$(site_base)"
    E2E_BASE_URL="$base" E2E_THEME="$theme" bash "$ROOT/docker/scripts/runtime.sh" playwright npx --no-install playwright test -c tests/e2e/playwright.config.ts "$@"
    ;;
  e2e-demo)
    ensure_stack
    webx bash /scripts/e2e-demo-import.sh
    ;;
  matrix)
    ensure_stack
    seed_demo
    node tools/matrix.mjs --theme "$theme" "$@"
    ;;
  marketplace)
    ensure_stack
    bash "$ROOT/docker/scripts/runtime.sh" node tools/theme-check.mjs --theme "$theme"
    bash "$ROOT/docker/scripts/runtime.sh" php-node tools/build-theme.mjs --theme "$theme"
    bash "$ROOT/docker/scripts/runtime.sh" php-node tools/build-plugin.mjs --plugin jooservices-resume
    restore_theme_source=1
    WEB_MARKETPLACE=1 web_start
    webx -e "THEME=$theme" bash /scripts/install-wp.sh
    for checker in theme-check plugin-check; do
      if ! webx wp plugin is-installed "$checker" </dev/null; then
        webx wp plugin install "$checker" </dev/null
      fi
      webx wp plugin activate "$checker" </dev/null
    done
    # Run both checkers so the evidence is complete, then fail if either reported a problem.
    check_status=0
    webx wp theme-check run "$theme" --format=json </dev/null || check_status=1
    webx wp plugin check jooservices-resume --format=json --require=./wp-content/plugins/plugin-check/cli.php </dev/null || check_status=1
    [[ "$check_status" -eq 0 ]] || exit 1
    ;;
  screenshots)
    ensure_stack
    base="$(site_base)"
    E2E_BASE_URL="$base" bash "$ROOT/docker/scripts/runtime.sh" playwright node tools/screenshots.mjs --theme "$theme" --base "$base" "$@"
    ;;
  critical)
    ensure_stack
    base="$(site_base)"
    E2E_VM_PULL="themes/$theme/assets/critical" bash "$ROOT/docker/scripts/runtime.sh" playwright node tools/critical.mjs --theme "$theme" --base "$base" "$@"
    ;;
  health)
    ensure_stack
    health_check
    ;;
  demo)
    ensure_stack
    seed_demo
    if [[ "${CAPTURE_MARKETPLACE_PREVIEW:-0}" == 1 ]]; then
      webx wp search-replace 'Viet VU' 'Jordan Sample' --all-tables --precise </dev/null
      webx wp search-replace 'Viet Vu' 'Jordan Sample' --all-tables --precise </dev/null
      webx wp search-replace 'SGCarmart' 'Northstar Demo Co.' --all-tables --precise </dev/null
      webx wp search-replace 'www.sgcarmart.com' 'northstar.example' --all-tables --precise </dev/null
      webx wp search-replace 'Acme Labs' 'Harborview Demo Co.' --all-tables --precise </dev/null
      E2E_BASE_URL="$(site_base)" E2E_VM_PULL="themes/$theme/screenshot.png" \
        bash "$ROOT/docker/scripts/runtime.sh" playwright node tools/marketplace-preview.mjs "themes/$theme/screenshot.png"
    fi
    health_check
    echo "Demo ready at $(bash "$ROOT/docker/scripts/site-url.sh" url "$(web_site_url)")."
    ;;
  wp)
    ensure_stack
    webx wp "$@"
    ;;
  shell)
    ensure_stack
    docker exec -it -u www-data -w /var/www/html "$WEB_NAME" bash
    ;;
  logs)
    docker logs -f --tail=100 "$WEB_NAME"
    ;;
  down)
    web_down
    ;;
  reset)
    [[ "${CONFIRM:-}" == yes ]] || { echo "reset removes the shared test web container and its web root, this ticket's upload volume, and this ticket database; it leaves Mailpit and every Workspaces core service running. Re-run with CONFIRM=yes." >&2; exit 2; }
    web_down --reset
    CONFIRM="$ticket" "$ROOT/docker/scripts/shared-db.sh" drop "$ticket"
    uploads_volume="joowt-$(get_env DB_NAME)-uploads"
    if docker volume inspect "$uploads_volume" >/dev/null 2>&1; then
      docker volume rm "$uploads_volume" >/dev/null
    fi
    ;;
  plugins-off)
    ensure_stack
    webx bash -c 'for p in wordpress-seo syntaxhighlighter akismet litespeed-cache health-check wordpress-chatgpt; do wp plugin is-active "$p" 2>/dev/null && wp plugin deactivate "$p"; done; true' </dev/null
    ;;
  plugins-on)
    ensure_stack
    webx bash -c 'for p in wordpress-seo syntaxhighlighter akismet litespeed-cache health-check wordpress-chatgpt; do wp plugin is-installed "$p" 2>/dev/null && ! wp plugin is-active "$p" 2>/dev/null && wp plugin activate "$p"; done; true' </dev/null
    ;;
  demo-plugins)
    ensure_stack
    webx bash /scripts/demo-plugins.sh
    ;;
  i18n)
    ensure_stack
    mkdir -p artifacts/i18n
    webx wp i18n make-pot /workspace/packages/theme-core /workspace/artifacts/i18n/joo-core.pot --domain=joo-core --exclude=tests,node_modules,resources/build,languages --skip-js --quiet
    webx wp i18n make-pot /workspace/plugins/jooservices-resume /workspace/artifacts/i18n/jooservices-resume.pot --domain=jooservices-resume --exclude=tests,node_modules,languages --skip-js --quiet
    webx wp i18n make-pot /workspace/themes/starter /workspace/artifacts/i18n/starter.pot --domain=starter --exclude=tests,node_modules,core,languages --skip-js --quiet
    webx wp i18n make-pot /workspace/themes/jooservices /workspace/artifacts/i18n/jooservices.pot --domain=jooservices --exclude=tests,node_modules,core,languages,assets/src --skip-js --quiet
    bash "$ROOT/docker/scripts/runtime.sh" node tools/i18n.mjs
    ;;
  *)
    echo "Unsupported shared WordPress task: $action" >&2
    exit 2
    ;;
esac
