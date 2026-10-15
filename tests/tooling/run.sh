#!/usr/bin/env bash
# Proves each quality tool fails on a deliberate violation and passes on a clean file.
set -u
cd "$(dirname "$0")/../.."
TMP=".tooling-tmp"
rm -rf "$TMP"; mkdir -p "$TMP"
trap 'rm -rf "$TMP" packages/theme-core/src/ToolingFixture themes/jooservices/inc/ToolingFixture.php' EXIT
fail=0
expect_fail() { name="$1"; shift; if "$@" >/dev/null 2>&1; then echo "FAIL: $name accepted a violation"; fail=1; else echo "ok:   $name rejects the violation"; fi; }
expect_pass() { name="$1"; shift; if "$@" >/dev/null 2>&1; then echo "ok:   $name accepts clean input"; else echo "FAIL: $name rejected clean input"; fail=1; fi; }

# PHPCS
cp tests/tooling/fixtures/bad.php "$TMP/bad.php"
expect_fail "phpcs" php -d error_reporting=24575 vendor/bin/phpcs --standard=phpcs.xml.dist "$TMP/bad.php"
printf "<?php\n/**\n * Clean file.\n *\n * @package JOOservices\n */\n\ndeclare(strict_types=1);\n\ndefined( 'ABSPATH' ) || exit;\n" > "$TMP/good.php"
expect_pass "phpcs" php -d error_reporting=24575 vendor/bin/phpcs --standard=phpcs.xml.dist "$TMP/good.php"

# ESLint
cp tests/tooling/fixtures/bad.js "$TMP/bad.js"
expect_fail "eslint" npx eslint "$TMP/bad.js"
printf 'export const answer = 42;\n' > "$TMP/good.js"
expect_pass "eslint" npx eslint "$TMP/good.js"

# Stylelint
cp tests/tooling/fixtures/bad.css "$TMP/bad.css"
expect_fail "stylelint" npx stylelint "$TMP/bad.css"
printf '.joo-box {\n  color: var(--joo-ink);\n  max-width: 100%%;\n}\n' > "$TMP/good.css"
expect_pass "stylelint" npx stylelint "$TMP/good.css"

# Prettier
printf 'const a   =   1\n' > "$TMP/ugly.js"
expect_fail "prettier" npx prettier --ignore-path .prettierignore --check "$TMP/ugly.js"

# deptrac: core importing a theme class must fail
mkdir -p packages/theme-core/src/ToolingFixture
cat > themes/jooservices/inc/ToolingFixture.php <<'PHP'
<?php
namespace JOOservices\Themes\Jooservices;
final class ToolingFixture {}
PHP
cat > packages/theme-core/src/ToolingFixture/Bad.php <<'PHP'
<?php
namespace JOOservices\ThemeCore\ToolingFixture;
use JOOservices\Themes\Jooservices\ToolingFixture;
final class Bad { public function make(): ToolingFixture { return new ToolingFixture(); } }
PHP
expect_fail "deptrac" php -d error_reporting=24575 vendor/bin/deptrac analyse --no-progress --fail-on-uncovered
rm -rf packages/theme-core/src/ToolingFixture themes/jooservices/inc/ToolingFixture.php
expect_pass "deptrac" php -d error_reporting=24575 vendor/bin/deptrac analyse --no-progress --fail-on-uncovered
bash tests/tooling/docs-compat.sh || fail=1
exit $fail
