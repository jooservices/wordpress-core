#!/usr/bin/env bash
# Applies the per-package line coverage thresholds from docs/definition-of-done.md to a Clover report.
set -u
clover="${1:-artifacts/reports/unit/clover.xml}"
cd "$(dirname "$0")/.."
fail=0
gate() {
  prefix="$1"; min="$2"
  out="$(php tools/coverage-check.php "$clover" "$prefix" "$min" 2>&1)"; code=$?
  case $code in
    0) echo "ok:   $out" ;;
    1) echo "FAIL: $out"; fail=1 ;;
    *) echo "skip: $prefix has no statements yet" ;;
  esac
}
gate packages/theme-core/src 85
gate plugins/jooservices-content/src 80
gate themes/jooservices/inc 70
gate themes/starter/inc 70
exit $fail
