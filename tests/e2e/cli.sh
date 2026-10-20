#!/usr/bin/env bash
# Exercises `wp joo ...` against the running Docker stack (make up first). Usage: tests/e2e/cli.sh [make-args]
set -u
cd "$(dirname "$0")/../.."
fail=0
wp() {
  local output status
  output="$(make -s wp ARGS="$*" 2>&1)"
  status=$?
  output="$(printf '%s\n' "$output" | sed -n '/^Admin user: /,$p' | tail -n +2)"
  printf '%s\n' "$output"
  return "$status"
}
check() { desc="$1"; want="$2"; shift 2; out="$("$@")"; code=$?; if [ "$want" = "ok" ] && [ $code -eq 0 ]; then echo "ok:   $desc"; elif [ "$want" = "fail" ] && [ $code -ne 0 ]; then echo "ok:   $desc (failed as expected)"; else echo "FAIL: $desc (exit $code): $out" | head -3; fail=1; fi; }
subscriber_login="joo-cli-subscriber-$$"
subscriber_email="$subscriber_login@example.test"
subscriber_id=""
cleanup() {
  if [[ -z "$subscriber_id" ]]; then
    subscriber_id="$(wp user get "$subscriber_login" --field=ID 2>/dev/null || true)"
  fi
  if [[ "$subscriber_id" =~ ^[0-9]+$ ]]; then
    wp user delete "$subscriber_id" --yes >/dev/null 2>&1 || true
  fi
}
trap cleanup EXIT
subscriber_output="$(wp user create "$subscriber_login" "$subscriber_email" --role=subscriber --porcelain 2>&1)"
subscriber_id="$(printf '%s\n' "$subscriber_output" | awk '/^[0-9]+$/ { id = $0 } END { print id }')"
if [[ ! "$subscriber_id" =~ ^[0-9]+$ ]]; then
  echo 'FAIL: WP-CLI did not return an ID for the temporary subscriber.'
  exit 1
fi

wp joo settings reset --all --yes >/dev/null 2>&1
before="$(wp joo settings get lazy.eager_count 2>&1)"
check "subscriber cannot change settings" fail wp "--user=$subscriber_id" joo settings set lazy.eager_count 9
after="$(wp joo settings get lazy.eager_count 2>&1)"
if [ "$after" = "$before" ]; then echo "ok:   subscriber write left settings unchanged"; else echo "FAIL: subscriber changed lazy.eager_count from '$before' to '$after'"; fail=1; fi

check "set eager_count" ok wp joo settings set lazy.eager_count 2
out="$(wp joo settings get lazy.eager_count 2>&1)"; [ "$out" = "2" ] && echo "ok:   get returns 2" || { echo "FAIL: get returned '$out'"; fail=1; }
check "invalid value is rejected" fail wp joo settings set lazy.eager_count abc
check "unknown key is rejected" fail wp joo settings get nope.nothing
out="$(wp joo settings list --source --format=json 2>&1)"; echo "$out" | grep -q '"source":"user"' && echo "ok:   list shows the user source" || { echo "FAIL: list --source: $out" | head -2; fail=1; }
check "modules list" ok wp joo modules list
wp joo doctor >/dev/null 2>&1; code=$?
if [ $code -le 1 ]; then echo "ok:   doctor exit code $code (0 healthy, 1 recommendations)"; else echo "FAIL: doctor exit code $code"; fail=1; fi
check "cache flush" ok wp joo cache flush
check "reset one key" ok wp joo settings reset lazy.eager_count
wp joo settings reset --all --yes >/dev/null 2>&1
exit $fail
