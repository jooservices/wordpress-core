#!/usr/bin/env bash
# Runs the wp-phpunit integration suites inside the wordpress container (called by `make test-integration`).
# The tests library must match the WordPress series; when the repository's wp-phpunit does not, the matching
# release is installed into /tmp (needs network).
set -euo pipefail
cd /workspace
WP_VER="$(cd /var/www/html && wp core version)"
SERIES="${WP_VER%.*}"
LOCKED="$(php -r '$i=json_decode(file_get_contents("/workspace/vendor/composer/installed.json"),true);foreach(($i["packages"]??$i) as $p){if($p["name"]==="wp-phpunit/wp-phpunit"){echo $p["version"];}}')"
LOCKED_SERIES="$(printf '%s' "${LOCKED#v}" | cut -d. -f1,2)"
if [ "$LOCKED_SERIES" = "$SERIES" ]; then
  export WP_TESTS_DIR="/workspace/vendor/wp-phpunit/wp-phpunit"
else
  LIB="/tmp/wp-phpunit-${SERIES}"
  if [ ! -f "${LIB}/vendor/wp-phpunit/wp-phpunit/includes/functions.php" ]; then
    mkdir -p "$LIB" && cd "$LIB"
    printf '{"name":"joowt/wp-tests-lib","require":{"wp-phpunit/wp-phpunit":"%s.*"},"config":{"platform":{"php":"8.1.99"}}}' "$SERIES" > composer.json
    composer install --no-interaction --no-progress --quiet
    cd /workspace
  fi
  export WP_TESTS_DIR="${LIB}/vendor/wp-phpunit/wp-phpunit"
fi
export WP_PHPUNIT__TESTS_CONFIG=/workspace/tests/wp-tests-config.php
mkdir -p /workspace/artifacts/reports/integration
echo "WordPress ${WP_VER} (tests lib ${WP_TESTS_DIR})"
PHP_FLAGS=(-d error_reporting=24575)
if [ "${COVERAGE:-0}" = "1" ]; then
  PHP_FLAGS+=(-d pcov.enabled=1 -d pcov.directory=/workspace)
  mkdir -p /workspace/artifacts/reports/cov
fi

run_suite() {
  local suite="$1"
  shift
  local coverage=()
  if [ "${COVERAGE:-0}" = "1" ]; then
    coverage+=(--coverage-php "/workspace/artifacts/reports/cov/integration-${WP_VER}-${suite}.cov")
  fi
  php "${PHP_FLAGS[@]}" /workspace/vendor/bin/phpunit -c /workspace/phpunit-integration.xml.dist \
    --testsuite "$suite" \
    --log-junit "/workspace/artifacts/reports/integration/junit-${WP_VER}-${suite}.xml" \
    "${coverage[@]}" "$@"
}

# Core fixtures boot their own manifests. Loading the live theme into those
# processes leaves its hooks in WP_UnitTestCase's baseline and duplicates them.
for suite in kit-integration core-integration plugin-integration; do
  JOO_TEST_THEME= run_suite "$suite" "$@"
done

# Only theme integration tests need the selected live theme booted by the test bootstrap.
JOO_TEST_THEME="${JOO_TEST_THEME:-}" run_suite theme-integration "$@"
