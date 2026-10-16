#!/usr/bin/env bash
# Select the current worktree on the single shared WordPress test container and
# hold a Workspaces-wide lock for the complete operation.
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd -P)"
ROOT="$(cd "$SCRIPT_DIR/../.." && pwd -P)"
action="${1:-}"
[[ -n "$action" ]] || { echo "Usage: wp-task.sh up|install|seed|seed-reset|integration|e2e|health|demo|wp|shell|logs|down|reset|plugins-on|plugins-off [args ...]" >&2; exit 2; }
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
release_lock() {
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

compose=("$ROOT/docker/scripts/compose.sh")
export JOOWT_PROJECT=joowt-wp-shared-test
ensure_stack() {
  ln -sfn ../../packages/theme-core "themes/$theme/core"
  bash "$ROOT/docker/scripts/runtime.sh" node tools/build-assets.mjs --root packages/theme-core
  if [[ -d "themes/$theme/assets/src" ]]; then
    bash "$ROOT/docker/scripts/runtime.sh" node tools/build-assets.mjs --root "themes/$theme" --src assets/src --out assets/build
  fi
  "$ROOT/docker/scripts/shared-db.sh" create "$ticket"
  local site_url port
  site_url="${WP_SITE_URL:-$(get_env WP_SITE_URL)}"
  port="$(bash "$ROOT/docker/scripts/site-url.sh" port "$site_url")"
  "$ROOT/docker/scripts/check-port.sh" "$port" wordpress
  "$ROOT/docker/scripts/check-port.sh" "$(get_env MAIL_PORT)" mailpit
  "${compose[@]}" up -d --build --wait mailpit
  "${compose[@]}" up -d --build --wait --force-recreate --renew-anon-volumes wordpress
  "${compose[@]}" exec -T -u www-data -e "THEME=$theme" wordpress bash /scripts/install-wp.sh
}

seed_demo() {
  if [[ "$action" == seed-live ]]; then
    bash "$ROOT/docker/scripts/runtime.sh" node tools/live-import.mjs --source "${SOURCE:-artifacts/live/jooservices.com.json}" --out artifacts/demo/live
    "${compose[@]}" exec -T -u www-data -e DEMO_SET=live wordpress bash /scripts/seed.sh "$theme" --reset
    return
  fi
  bash "$ROOT/docker/scripts/runtime.sh" node tools/wxr-generator/generate.mjs --out "artifacts/demo/$theme" >/dev/null
  if [[ "$action" == seed-reset ]]; then
    "${compose[@]}" exec -T -u www-data wordpress bash /scripts/seed.sh "$theme" --reset
  else
    "${compose[@]}" exec -T -u www-data wordpress bash /scripts/seed.sh "$theme"
  fi
}

health_check() {
  local values base
  values="$("${compose[@]}" exec -T -u www-data wordpress wp eval-file /scripts/health-values.php </dev/null)"
  base="${E2E_BASE_URL:-$(bash "$ROOT/docker/scripts/site-url.sh" internal "${WP_SITE_URL:-$(get_env WP_SITE_URL)}")}"
  E2E_BASE_URL="$base" bash "$ROOT/docker/scripts/runtime.sh" node tools/health.mjs --base "$base" --values "$values"
}

case "$action" in
  up|install)
    ensure_stack
    echo "Shared WordPress test site ready at $(bash "$ROOT/docker/scripts/site-url.sh" url "${WP_SITE_URL:-$(get_env WP_SITE_URL)}")."
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
    "${compose[@]}" exec -T -u www-data -e "JOO_TEST_THEME=${TEST_THEME:-$theme}" -e "COVERAGE=${COVERAGE:-0}" wordpress bash /scripts/phpunit-integration.sh "$@"
    ;;
  e2e)
    ensure_stack
    base="${E2E_BASE_URL:-$(bash "$ROOT/docker/scripts/site-url.sh" internal "${WP_SITE_URL:-$(get_env WP_SITE_URL)}")}"
    E2E_BASE_URL="$base" E2E_THEME="$theme" bash "$ROOT/docker/scripts/runtime.sh" playwright npx --no-install playwright test -c tests/e2e/playwright.config.ts "$@"
    ;;
  critical)
    ensure_stack
    base="${E2E_BASE_URL:-$(bash "$ROOT/docker/scripts/site-url.sh" internal "${WP_SITE_URL:-$(get_env WP_SITE_URL)}")}"
    bash "$ROOT/docker/scripts/runtime.sh" playwright node tools/critical.mjs --theme "$theme" --base "$base" "$@"
    ;;
  health)
    ensure_stack
    health_check
    ;;
  demo)
    ensure_stack
    seed_demo
    health_check
    echo "Demo ready at $(bash "$ROOT/docker/scripts/site-url.sh" url "${WP_SITE_URL:-$(get_env WP_SITE_URL)}")."
    ;;
  wp)
    ensure_stack
    "${compose[@]}" exec -T -u www-data wordpress wp "$@"
    ;;
  shell)
    ensure_stack
    "${compose[@]}" exec -u www-data wordpress bash
    ;;
  logs)
    "${compose[@]}" logs -f --tail=100 "${1:-wordpress}"
    ;;
  down)
    "${compose[@]}" stop wordpress mailpit
    ;;
  reset)
    [[ "${CONFIRM:-}" == yes ]] || { echo "reset stops the shared test WordPress and Mailpit containers, removes only this ticket's upload volume, and drops this ticket database. Re-run with CONFIRM=yes." >&2; exit 2; }
    "${compose[@]}" down --remove-orphans
    CONFIRM="$ticket" "$ROOT/docker/scripts/shared-db.sh" drop "$ticket"
    uploads_volume="joowt-$(get_env DB_NAME)-uploads"
    if docker volume inspect "$uploads_volume" >/dev/null 2>&1; then
      docker volume rm "$uploads_volume" >/dev/null
    fi
    ;;
  plugins-off)
    ensure_stack
    "${compose[@]}" exec -T -u www-data wordpress bash -c 'for p in wordpress-seo syntaxhighlighter akismet litespeed-cache health-check wordpress-chatgpt; do wp plugin is-active "$p" 2>/dev/null && wp plugin deactivate "$p"; done; true' </dev/null
    ;;
  plugins-on)
    ensure_stack
    "${compose[@]}" exec -T -u www-data wordpress bash -c 'for p in wordpress-seo syntaxhighlighter akismet litespeed-cache health-check wordpress-chatgpt; do wp plugin is-installed "$p" 2>/dev/null && ! wp plugin is-active "$p" 2>/dev/null && wp plugin activate "$p"; done; true' </dev/null
    ;;
  demo-plugins)
    ensure_stack
    "${compose[@]}" exec -T -u www-data wordpress bash /scripts/demo-plugins.sh
    ;;
  i18n)
    ensure_stack
    mkdir -p artifacts/i18n
    "${compose[@]}" exec -T -u www-data wordpress wp i18n make-pot /workspace/packages/theme-core /workspace/artifacts/i18n/joo-core.pot --domain=joo-core --exclude=tests,node_modules,resources/build,languages --skip-js --quiet
    "${compose[@]}" exec -T -u www-data wordpress wp i18n make-pot /workspace/plugins/jooservices-resume /workspace/artifacts/i18n/jooservices-resume.pot --domain=jooservices-resume --exclude=tests,node_modules,languages --skip-js --quiet
    "${compose[@]}" exec -T -u www-data wordpress wp i18n make-pot /workspace/themes/starter /workspace/artifacts/i18n/starter.pot --domain=starter --exclude=tests,node_modules,core,languages --skip-js --quiet
    "${compose[@]}" exec -T -u www-data wordpress wp i18n make-pot /workspace/themes/jooservices /workspace/artifacts/i18n/jooservices.pot --domain=jooservices --exclude=tests,node_modules,core,languages,assets/src --skip-js --quiet
    bash "$ROOT/docker/scripts/runtime.sh" node tools/i18n.mjs
    ;;
  *)
    echo "Unsupported shared WordPress task: $action" >&2
    exit 2
    ;;
esac
