#!/usr/bin/env bash
# Creates a throwaway repository, installs the hooks and checks accept/reject cases.
set -u
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
T="$(mktemp -d)"; trap 'rm -rf "$T"' EXIT
fail=0
cd "$T" && git init -q -b main . && git config user.name t && git config user.email t@example.test
mkdir -p tools && cp -R "$ROOT/tools/githooks" tools/githooks
git config core.hooksPath tools/githooks
ln -s "$ROOT/vendor" vendor; ln -s "$ROOT/node_modules" node_modules
cp "$ROOT/phpcs.xml.dist" "$ROOT/.prettierignore" "$ROOT/eslint.config.js" "$ROOT/package.json" .
check() { desc="$1"; want="$2"; shift 2; "$@" >/dev/null 2>&1; got=$?; if { [ "$want" = ok ] && [ $got -eq 0 ]; } || { [ "$want" = reject ] && [ $got -ne 0 ]; }; then echo "ok:   $desc"; else echo "FAIL: $desc (exit $got)"; fail=1; fi; }
echo 'a' > a.txt && git add a.txt
check "bad commit message rejected" reject git commit -q -m "fix stuff"
check "good commit message accepted" ok git commit -q -m "JOOWT-1: add a.txt"
# the secret is assembled at runtime so this file itself passes the secrets scan
printf '%s%s = "%s"\n' pass word "$(printf 'c3VwZXJzZWNyZXQ5OTk4' | base64 -d)" > s.txt && git add s.txt
check "staged secret rejected" reject git commit -q -m "JOOWT-2: add secret"
git reset -q s.txt && rm s.txt
echo 'X=1' > .env && git add -f .env
check ".env rejected" reject git commit -q -m "JOOWT-3: add env"
git reset -q .env && rm .env
printf '<?php\necho $_GET["x"];\n' > bad.php && git add bad.php
check "phpcs violation rejected" reject git commit -q -m "JOOWT-4: add bad php"
git reset -q bad.php && rm bad.php
check "merge message accepted" ok git commit -q --allow-empty -m "Merge branch x"
exit $fail
