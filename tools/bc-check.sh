#!/usr/bin/env bash
# Backward-compatibility check of packages/theme-core public API between a Git ref and HEAD.
# Usage: tools/bc-check.sh <from-ref> [--to=<ref>]
# Roave checks the package it runs in, so the core subtree is exported into a throwaway repository
# for both revisions (git subtree split), then compared. Output: markdown on stdout and in artifacts/reports/bc/.
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
FROM="${1:?usage: tools/bc-check.sh <from-ref> [--to=<ref>]}"
TO="HEAD"; [ "${2:-}" ] && TO="${2#--to=}"
BIN="$ROOT/tools/bc/vendor/bin/roave-backward-compatibility-check"
[ -x "$BIN" ] || { echo "ERROR: run 'composer install --working-dir=tools/bc' first" >&2; exit 1; }
TMP="$(mktemp -d)"; trap 'rm -rf "$TMP"' EXIT
cd "$ROOT"
from_sha="$(git rev-parse --verify "$FROM^{commit}")"
to_sha="$(git rev-parse --verify "$TO^{commit}")"
split_from="$(git subtree split --prefix=packages/theme-core "$from_sha")"
split_to="$(git subtree split --prefix=packages/theme-core "$to_sha")"
git clone -q --no-hardlinks "$ROOT" "$TMP/repo"
cd "$TMP/repo"
git fetch -q "$ROOT" "$split_from" "$split_to" 2>/dev/null || true
git checkout -q -B bc-from "$split_from" 2>/dev/null || git checkout -q "$split_from"
git branch -f bc-from "$split_from"; git branch -f bc-to "$split_to"
git checkout -q bc-to
composer install --no-interaction --no-progress -q 2>/dev/null || composer dump-autoload -q
mkdir -p "$ROOT/artifacts/reports/bc"
report="$ROOT/artifacts/reports/bc/bc-$(date +%Y%m%d-%H%M%S).md"
set +e
php -d error_reporting=24575 "$BIN" --from=bc-from --to=bc-to --format=markdown | tee "$report"
code=${PIPESTATUS[0]}
echo "report: $report (exit $code)" >&2
exit $code
