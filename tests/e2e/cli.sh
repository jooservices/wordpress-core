#!/usr/bin/env bash
# Exercises `wp joo ...` against the running Docker stack (make up first). Usage: tests/e2e/cli.sh [make-args]
set -u
cd "$(dirname "$0")/../.."
fail=0
wp() { make -s wp ARGS="$*" 2>&1; }
check() { desc="$1"; want="$2"; shift 2; out="$("$@")"; code=$?; if [ "$want" = "ok" ] && [ $code -eq 0 ]; then echo "ok:   $desc"; elif [ "$want" = "fail" ] && [ $code -ne 0 ]; then echo "ok:   $desc (failed as expected)"; else echo "FAIL: $desc (exit $code): $out" | head -3; fail=1; fi; }
make -s wp ARGS="joo settings reset --all --yes" >/dev/null 2>&1
check "set eager_count" ok make -s wp ARGS="joo settings set lazy.eager_count 2"
out="$(make -s wp ARGS='joo settings get lazy.eager_count' 2>&1)"; [ "$out" = "2" ] && echo "ok:   get returns 2" || { echo "FAIL: get returned '$out'"; fail=1; }
check "invalid value is rejected" fail make -s wp ARGS="joo settings set lazy.eager_count abc"
check "unknown key is rejected" fail make -s wp ARGS="joo settings get nope.nothing"
out="$(make -s wp ARGS='joo settings list --source --format=json' 2>&1)"; echo "$out" | grep -q '"source":"user"' && echo "ok:   list shows the user source" || { echo "FAIL: list --source: $out" | head -2; fail=1; }
check "modules list" ok make -s wp ARGS="joo modules list"
./docker/scripts/compose.sh exec -T -u www-data wordpress wp joo doctor >/dev/null 2>&1; code=$?
if [ $code -le 1 ]; then echo "ok:   doctor exit code $code (0 healthy, 1 recommendations)"; else echo "FAIL: doctor exit code $code"; fail=1; fi
check "cache flush" ok make -s wp ARGS="joo cache flush"
check "reset one key" ok make -s wp ARGS="joo settings reset lazy.eager_count"
make -s wp ARGS="joo settings reset --all --yes" >/dev/null 2>&1
exit $fail
