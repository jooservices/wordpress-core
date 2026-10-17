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
[[ -f "$ROOT/.env" ]] || { echo "ERROR: run make init-env from a JOOS ticket branch first." >&2; exit 2; }

ticket="${JOOS_TICKET:-}"
if [[ -z "$ticket" ]]; then
  branch="$(git -C "$ROOT" branch --show-current)"
  if [[ "$branch" =~ (JOOS-[0-9]+) ]]; then ticket="${BASH_REMATCH[1]}"; fi
fi
[[ "$ticket" =~ ^JOOS-[0-9]+$ ]] || {
  echo "ERROR: run this target from a JOOS ticket worktree or set JOOS_TICKET." >&2
  exit 2
}

source "$ROOT/docker/scripts/site-lib.sh"
cd "$ROOT"

restore_theme_source=0
restore_sources() {
  if ((restore_theme_source)); then
    restore_theme_source=0
    rm -rf "$JOO_SITE_DIR/wp-content/themes/$theme" "$JOO_SITE_DIR/wp-content/plugins/jooservices-resume"
    rt sh "bash '$ROOT/docker/scripts/site-bootstrap.sh'" >/dev/null || echo "ERROR: failed to restore the source symlinks of the site." >&2
  fi
}
trap restore_sources EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

ensure_site() {
  ln -sfn ../../packages/theme-core "themes/$theme/core"
  rt node tools/build-assets.mjs --root packages/theme-core
  if [[ -d "themes/$theme/assets/src" ]]; then
    rt node tools/build-assets.mjs --root "themes/$theme" --src assets/src --out assets/build
  fi
  "$ROOT/docker/scripts/shared-db.sh" create "$ticket"
  site_up
}

seed_demo() {
  if [[ "$action" == seed-live ]]; then
    rt node tools/live-import.mjs --source "${SOURCE:-artifacts/live/jooservices.com.json}" --out artifacts/demo/live
    DEMO_SET=live site_script seed.sh "$theme" --reset
    return
  fi
  rt node tools/wxr-generator/generate.mjs --out "artifacts/demo/$theme" >/dev/null
  if [[ "$action" == seed-reset ]]; then
    site_script seed.sh "$theme" --reset
  else
    site_script seed.sh "$theme"
  fi
}

health_check() {
  local values
  values="$(wpx eval-file "$ROOT/docker/scripts/health-values.php" </dev/null)"
  webx node tools/health.mjs --base "$SITE_URL" --values "$values"
}

case "$action" in
  up|install)
    ensure_site
    echo "WordPress test site ready at $SITE_URL"
    ;;
  seed|seed-reset|seed-live)
    ensure_site
    seed_demo
    ;;
  integration)
    ensure_site
    WP_PHP_CLI="${WP_PHP_VERSION:-}" JOO_TEST_THEME="${TEST_THEME:-$theme}" COVERAGE="${COVERAGE:-0}" site_script phpunit-integration.sh "$@"
    ;;
  e2e)
    ensure_site
    E2E_THEME="$theme" rt playwright npx --no-install playwright test -c tests/e2e/playwright.config.ts "$@"
    ;;
  e2e-demo)
    ensure_site
    site_script e2e-demo-import.sh
    ;;
  matrix)
    ensure_site
    seed_demo
    node tools/matrix.mjs --theme "$theme" "$@"
    ;;
  marketplace)
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
    ensure_site
    rt playwright node tools/screenshots.mjs --theme "$theme" --base "$SITE_URL" "$@"
    ;;
  critical)
    ensure_site
    rt playwright node tools/critical.mjs --theme "$theme" --base "$SITE_URL" "$@"
    ;;
  health)
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
