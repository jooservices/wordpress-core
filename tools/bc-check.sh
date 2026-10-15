#!/usr/bin/env bash
# Backward-compatibility check of packages/theme-core public API between a Git ref and another ref.
# Usage: tools/bc-check.sh <from-ref> [--to=<ref>]
# Roave checks the package in the repository root, so the core subtree is split out of this monorepo
# for both revisions (git subtree split) into a throwaway repository, then compared.
# Output: markdown on stdout and in artifacts/reports/bc/. Exit code of the checker is returned.
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
FROM="${1:?usage: tools/bc-check.sh <from-ref> [--to=<ref>]}"
TO="HEAD"; [ "${2:-}" ] && TO="${2#--to=}"
BIN="$ROOT/tools/bc/vendor/bin/roave-backward-compatibility-check"
[ -x "$BIN" ] || { echo "ERROR: run 'composer install --working-dir=tools/bc' first" >&2; exit 1; }
TMP="$(mktemp -d)"
cd "$ROOT"
cleanup() { git update-ref -d refs/bc/from 2>/dev/null || true; git update-ref -d refs/bc/to 2>/dev/null || true; rm -rf "$TMP"; }
trap cleanup EXIT
git update-ref refs/bc/from "$(git subtree split --prefix=packages/theme-core "$(git rev-parse --verify "$FROM^{commit}")")"
git update-ref refs/bc/to "$(git subtree split --prefix=packages/theme-core "$(git rev-parse --verify "$TO^{commit}")")"
git init -q "$TMP/repo"
(cd "$TMP/repo" && git fetch -q "$ROOT" refs/bc/from:refs/heads/bc-from refs/bc/to:refs/heads/bc-to && git checkout -q bc-to)
mkdir -p "$ROOT/artifacts/reports/bc"
report="$ROOT/artifacts/reports/bc/bc-$(date +%Y%m%d-%H%M%S).md"
cd "$TMP/repo"
set +e
php -d error_reporting=24575 "$BIN" --from=bc-from --to=bc-to --format=markdown 2>&1 | tee "$report"
code=${PIPESTATUS[0]}
echo "report: $report (exit $code)" >&2
exit "$code"
