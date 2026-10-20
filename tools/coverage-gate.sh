#!/usr/bin/env bash
# Applies the per-package line coverage thresholds from docs/definition-of-done.md to a Clover report.
set -u
clover="${1:-artifacts/reports/unit/clover.xml}"
cd "$(dirname "$0")/.."
fail=0
gate() {
  prefix="$1"; min="$2"
  out="$(bash ./docker/scripts/runtime.sh php tools/coverage-check.php "$clover" "$prefix" "$min" 2>&1)"; code=$?
  case $code in
    0) echo "ok:   $out" ;;
    1) echo "FAIL: $out"; fail=1 ;;
    2)
      if [[ "$out" == *"no statements found under"* ]]; then
        echo "skip: $prefix has no statements yet"
      else
        echo "ERROR: $out"; fail=1
      fi
      ;;
    *) echo "ERROR: coverage check exited $code: $out"; fail=1 ;;
  esac
}
gate packages/theme-core/src 85
gate plugins/jooservices-resume/src 80
gate themes/jooservices/inc 70
gate themes/starter/inc 70
exit $fail
