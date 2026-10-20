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

run_directory() {
  local theme="$1" directory="$2" label="$3"
  local coverage=()
  if [ "${COVERAGE:-0}" = "1" ]; then
    coverage+=(--coverage-php "$ROOT/artifacts/reports/cov/integration-${WP_VER}-${label}.cov")
  fi
  JOO_TEST_THEME="$theme" php "${PHP_FLAGS[@]}" "$ROOT/vendor/bin/phpunit" -c "$ROOT/phpunit-integration.xml.dist" \
    "$directory" \
    --log-junit "$ROOT/artifacts/reports/integration/junit-${WP_VER}-${label}.xml" \
    "${coverage[@]}"
}

# Core fixtures boot their own manifests. Loading the live theme into those
# processes leaves its hooks in WP_UnitTestCase's baseline and duplicates them.
for suite in kit-integration core-integration plugin-integration; do
  JOO_TEST_THEME= run_suite "$suite" "$@"
done

# Each fixture theme has its own isolated suite (theme-fixture-<name>) so package themes and a fixture core symlink are not scanned with them.
if [[ -n "${JOO_TEST_THEME:-}" && -d "$ROOT/tests/fixtures/themes/$JOO_TEST_THEME" ]]; then
  run_suite "theme-fixture-$JOO_TEST_THEME" "$@"
else
  # Only built-in theme integration tests need the selected live theme booted by the test bootstrap.
  JOO_TEST_THEME="${JOO_TEST_THEME:-}" run_suite theme-integration "$@"
fi

# A generated theme's tests are outside the static PHPUnit XML suite. Run them in their own process so fixture tests
# don't inherit registered blocks from the selected live theme.
if [[ -n "${JOO_TEST_THEME:-}" && ! -d "$ROOT/tests/fixtures/themes/$JOO_TEST_THEME" && "$JOO_TEST_THEME" != starter && "$JOO_TEST_THEME" != jooservices ]]; then
  theme_tests="$ROOT/themes/$JOO_TEST_THEME/tests/Integration"
  if [[ -d "$theme_tests" ]]; then
    run_directory "$JOO_TEST_THEME" "$theme_tests" "theme-$JOO_TEST_THEME"
  fi
fi

# The portfolio fixture boots its own manifest; a separate process keeps the selected theme's global hooks isolated.
if [[ "${JOO_TEST_THEME:-}" != portfolio ]]; then
  JOO_TEST_THEME= run_suite portfolio-fixture-integration "$@"
fi
