#!/usr/bin/env bash
# Runs the wp-phpunit integration suites in the shared PHP CLI container (called by `make test-integration`).
# Environment: JOO_ROOT (checkout), JOO_SITE_DIR (this worktree's WordPress site), WORDPRESS_DB_*, JOO_TEST_THEME, COVERAGE.
# The tests library must match the WordPress series; when the repository's wp-phpunit does not, the matching
# release is installed under artifacts/wp-tests-lib (needs network).
set -euo pipefail
ROOT="${JOO_ROOT:?}"
cd "$ROOT"
WP_VER="$(cd "${JOO_SITE_DIR:?}" && wp core version)"
SERIES="${WP_VER%.*}"
LOCKED="$(php -r '$i=json_decode(file_get_contents(getenv("JOO_ROOT")."/vendor/composer/installed.json"),true);foreach(($i["packages"]??$i) as $p){if($p["name"]==="wp-phpunit/wp-phpunit"){echo $p["version"];}}')"
LOCKED_SERIES="$(printf '%s' "${LOCKED#v}" | cut -d. -f1,2)"
if [ "$LOCKED_SERIES" = "$SERIES" ]; then
  export WP_TESTS_DIR="$ROOT/vendor/wp-phpunit/wp-phpunit"
else
  LIB="$ROOT/artifacts/wp-tests-lib/${SERIES}"
  if [ ! -f "${LIB}/vendor/wp-phpunit/wp-phpunit/includes/functions.php" ]; then
    mkdir -p "$LIB" && cd "$LIB"
    printf '{"name":"joowt/wp-tests-lib","require":{"wp-phpunit/wp-phpunit":"%s.*"},"config":{"platform":{"php":"8.1.99"}}}' "$SERIES" > composer.json
    composer install --no-interaction --no-progress --quiet
    cd "$ROOT"
  fi
  export WP_TESTS_DIR="${LIB}/vendor/wp-phpunit/wp-phpunit"
fi
export WP_PHPUNIT__TESTS_CONFIG="$ROOT/tests/wp-tests-config.php"
mkdir -p "$ROOT/artifacts/reports/integration"
echo "WordPress ${WP_VER} (tests lib ${WP_TESTS_DIR})"
PHP_FLAGS=(-d "error_reporting=${PHP_ERROR_REPORTING:-24575}")
if [ "${COVERAGE:-0}" = "1" ]; then
  PHP_FLAGS+=(-d pcov.enabled=1 -d pcov.directory="$ROOT")
  mkdir -p "$ROOT/artifacts/reports/cov"
fi

run_suite() {
  local suite="$1"
  shift
  local coverage=()
  if [ "${COVERAGE:-0}" = "1" ]; then
    coverage+=(--coverage-php "$ROOT/artifacts/reports/cov/integration-${WP_VER}-${suite}.cov")
  fi
  php "${PHP_FLAGS[@]}" "$ROOT/vendor/bin/phpunit" -c "$ROOT/phpunit-integration.xml.dist" \
    --testsuite "$suite" \
    --log-junit "$ROOT/artifacts/reports/integration/junit-${WP_VER}-${suite}.xml" \
    "${coverage[@]}" "$@"
}

# Core fixtures boot their own manifests. Loading the live theme into those
# processes leaves its hooks in WP_UnitTestCase's baseline and duplicates them.
for suite in kit-integration core-integration plugin-integration; do
  JOO_TEST_THEME= run_suite "$suite" "$@"
done

# Fixture themes have their own isolated suite so package themes and a fixture core symlink are not scanned with them.
if [[ -n "${JOO_TEST_THEME:-}" && -d "$ROOT/tests/fixtures/themes/$JOO_TEST_THEME" ]]; then
  run_suite theme-fixtures "$@"
else
  JOO_TEST_THEME="${JOO_TEST_THEME:-}" run_suite theme-integration "$@"
fi
