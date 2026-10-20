#!/usr/bin/env bash
# Proves each quality tool fails on a deliberate violation and passes on a clean file.
set -u
cd "$(dirname "$0")/../.."
TMP_ROOT=".tooling-tmp"
TMP_ROOT_CREATED=0
if [ ! -d "$TMP_ROOT" ]; then mkdir "$TMP_ROOT"; TMP_ROOT_CREATED=1; fi
TMP="$(mktemp -d "$TMP_ROOT/run.XXXXXX")" || exit 1
cleanup() {
  rm -rf "$TMP" packages/theme-core/src/ToolingFixture themes/jooservices/inc/ToolingFixture.php
  if [ "$TMP_ROOT_CREATED" -eq 1 ]; then rmdir "$TMP_ROOT" 2>/dev/null || true; fi
}
trap cleanup EXIT
fail=0
expect_fail() { name="$1"; shift; if "$@" >/dev/null 2>&1; then echo "FAIL: $name accepted a violation"; fail=1; else echo "ok:   $name rejects the violation"; fi; }
expect_pass() { name="$1"; shift; if "$@" >/dev/null 2>&1; then echo "ok:   $name accepts clean input"; else echo "FAIL: $name rejected clean input"; fail=1; fi; }

# PHPCS
cp tests/tooling/fixtures/bad.php "$TMP/bad.php"
expect_fail "phpcs" php -d error_reporting=24575 vendor/bin/phpcs --standard=phpcs.xml.dist "$TMP/bad.php"
printf "<?php\n/**\n * Clean file.\n *\n * @package JOOservices\n */\n\ndeclare(strict_types=1);\n\ndefined( 'ABSPATH' ) || exit;\n" > "$TMP/good.php"
expect_pass "phpcs" php -d error_reporting=24575 vendor/bin/phpcs --standard=phpcs.xml.dist "$TMP/good.php"

# Core PHP and block template translations.
expect_pass "core i18n" php tools/check-core-i18n.php
expect_fail "core i18n" php tools/check-core-i18n.php tests/tooling/fixtures/i18n-hardcoded.php
expect_fail "core i18n template" php tools/check-core-i18n.php tests/tooling/fixtures/i18n-hardcoded-template.php
expect_fail "core i18n block comment" php tools/check-core-i18n.php tests/tooling/fixtures/i18n-hardcoded-block-comment.php
expect_fail "core i18n single word" php tools/check-core-i18n.php tests/tooling/fixtures/i18n-hardcoded-open.php
expect_fail "core i18n printf" php tools/check-core-i18n.php tests/tooling/fixtures/i18n-hardcoded-printf.php
expect_fail "core i18n printf argument" php tools/check-core-i18n.php tests/tooling/fixtures/i18n-hardcoded-printf-argument.php
expect_fail "core i18n vprintf argument" php tools/check-core-i18n.php tests/tooling/fixtures/i18n-hardcoded-vprintf-argument.php

# PHPStan: core must stay compatible with PHP 8.1.
cp tests/tooling/fixtures/php-82-readonly.php "$TMP/php-input.php"
cat > "$TMP/phpstan-php81.neon" <<'NEON'
includes:
  - ../../phpstan-core.neon
parameters:
  paths:
    - php-input.php
NEON
expect_fail "PHPStan PHP 8.1" php -d error_reporting=24575 vendor/bin/phpstan analyse -c "$TMP/phpstan-php81.neon" --no-progress --memory-limit=1G
printf '<?php\nfinal class Php81Compatible {}\n' > "$TMP/php-input.php"
expect_pass "PHPStan PHP 8.1" php -d error_reporting=24575 vendor/bin/phpstan analyse -c "$TMP/phpstan-php81.neon" --no-progress --memory-limit=1G

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
# core version: single source of truth
php tools/check-version.php >/dev/null 2>&1 && echo "ok:   check-version passes for the real core" || { echo "FAIL: check-version"; fail=1; }
mkdir -p "$TMP/ver" && cp packages/theme-core/composer.json packages/theme-core/core.php packages/theme-core/CHANGELOG.md "$TMP/ver/"
sed -i.bak 's/^## 0\.1\.0/## 0.2.0/' "$TMP/ver/CHANGELOG.md"
expect_fail "check-version" php tools/check-version.php "$TMP/ver"
# runtime bundle: an unexpected file in a core copy is rejected, the allowed set passes
mkdir -p "$TMP/bundle" && node tools/runtime-bundle.mjs list 2>/dev/null | while read -r f; do mkdir -p "$TMP/bundle/$(dirname "$f")"; cp "packages/theme-core/$f" "$TMP/bundle/$f"; done
node tools/runtime-bundle.mjs check "$TMP/bundle" >/dev/null 2>&1 && echo "ok:   runtime bundle accepts the allowed files" || { echo "FAIL: runtime bundle (clean)"; fail=1; }
mkdir -p "$TMP/bundle/tests" && echo x > "$TMP/bundle/tests/leak.php"
expect_fail "runtime bundle" node tools/runtime-bundle.mjs check "$TMP/bundle"
# i18n check: passes with a complete dictionary, fails when a string has no translation
node tools/i18n.mjs --check >/dev/null 2>&1 && echo "ok:   i18n check passes with a complete dictionary" || { echo "FAIL: i18n check"; fail=1; }
cp packages/theme-core/languages/vi.json "$TMP/vi.json.bak"
node -e 'const fs = require("node:fs"); const path = "packages/theme-core/languages/vi.json"; const data = JSON.parse(fs.readFileSync(path, "utf8")); delete data.Home; fs.writeFileSync(path, JSON.stringify(data));'
expect_fail "i18n check" node tools/i18n.mjs --check
cp "$TMP/vi.json.bak" packages/theme-core/languages/vi.json
exit $fail
