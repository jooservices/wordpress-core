#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd -P)"
source "$ROOT/docker/scripts/wp-task-lock.sh"

if [[ "$WP_TASK_LOCK_WAIT_SECONDS" -ne 1200 || "$WP_TASK_OPERATION_CAP_SECONDS" -ne 1200 ]]; then
  echo "FAIL: the production shared WordPress test lock and operation caps are not 20 minutes." >&2
  exit 1
fi

if ! bash -c 'source "$1"; wp_task_lock_owner_is_ancestor "$2"' _ \
  "$ROOT/docker/scripts/wp-task-lock.sh" "$BASHPID"; then
  echo "FAIL: the process ancestry check did not accept a real ancestor." >&2
  exit 1
fi

tmp="$(mktemp -d)"
holder_pid=""
cleanup() {
  if [[ -n "$holder_pid" ]]; then
    kill "$holder_pid" 2>/dev/null || true
    wait "$holder_pid" 2>/dev/null || true
  fi
  wp_task_lock_release
  rm -rf -- "$tmp"
}
trap cleanup EXIT

dead_lock="$tmp/dead-owner.lock"
(exit 0) &
dead_pid="$!"
wait "$dead_pid" 2>/dev/null || true
if kill -0 "$dead_pid" 2>/dev/null; then
  echo "FAIL: dead-owner fixture process is still running." >&2
  exit 1
fi
printf '%s\n' "$dead_pid" > "$dead_lock"
if ! wp_task_lock_acquire "$dead_lock" 2> "$tmp/dead.stderr"; then
  echo "FAIL: dead-owner recovery did not acquire the lock." >&2
  cat "$tmp/dead.stderr" >&2
  exit 1
fi
dead_output="$(cat "$tmp/dead.stderr")"
if [[ "$dead_output" != *"Removed stale shared WordPress test lock for dead owner PID $dead_pid."* ]]; then
  echo "FAIL: dead-owner recovery did not log the stale lock." >&2
  exit 1
fi
if [[ "$(cat "$dead_lock")" != "$$" ]]; then
  echo "FAIL: dead-owner recovery did not record the current owner." >&2
  exit 1
fi
wp_task_lock_release
if [[ -e "$dead_lock" ]]; then
  echo "FAIL: lock release left the owner marker behind." >&2
  exit 1
fi
echo "ok:   dead owner is removed, logged, and replaced"

live_lock="$tmp/live-owner.lock"
sleep 30 &
holder_pid="$!"
live_owner_pid="$holder_pid"
if bash -c 'source "$1"; wp_task_lock_owner_is_ancestor "$2"' _ \
  "$ROOT/docker/scripts/wp-task-lock.sh" "$live_owner_pid"; then
  echo "FAIL: the process ancestry check accepted an unrelated live process." >&2
  exit 1
fi
printf '%s\n' "$live_owner_pid" > "$live_lock"
if [[ ! -s "$live_lock" ]] || ! kill -0 "$holder_pid" 2>/dev/null; then
  echo "FAIL: live-owner fixture did not acquire its lock." >&2
  exit 1
fi

if wp_task_lock_acquire "$live_lock" 1 > "$tmp/live.stdout" 2> "$tmp/live.stderr"; then
  echo "FAIL: a live owner did not hold the lock through the wait cap." >&2
  exit 1
fi
if ! grep -Eq 'ERROR: timed out after 1 second waiting for the shared WordPress test lock \(owner PID [0-9]+ is still running\)\.' "$tmp/live.stderr"; then
  echo "FAIL: live-owner timeout did not explain the cap and owner." >&2
  cat "$tmp/live.stderr" >&2
  exit 1
fi
if [[ "$(cat "$live_lock")" != "$live_owner_pid" ]]; then
  echo "FAIL: live-owner fixture lost its PID marker." >&2
  exit 1
fi
kill "$holder_pid" 2>/dev/null || true
wait "$holder_pid" 2>/dev/null || true
holder_pid=""
rm -f -- "$live_lock"
echo "ok:   live owner is preserved and the wait cap reports a clear error"

gtimeout_bin="$tmp/gtimeout-bin"
mkdir -p "$gtimeout_bin"
gtimeout_stub="$gtimeout_bin/gtimeout"
: > "$gtimeout_stub"
chmod +x "$gtimeout_stub"
if [[ "$(PATH="$gtimeout_bin" wp_task_timeout_command)" != "$gtimeout_stub" ]]; then
  echo "FAIL: the timeout resolver did not select gtimeout when timeout is unavailable." >&2
  exit 1
fi
echo "ok:   gtimeout is selected when timeout is unavailable"

run_forwarded_signal_test() {
  local signal="$1"
  local expected_status="$2"
  local ready="$tmp/signal-$signal-ready"
  local marker="$tmp/signal-$signal-forwarded"
  local previous_trap_marker="$tmp/signal-$signal-original-trap"
  local test_pid="$$"
  local signal_sender_pid signal_status

  trap 'touch "$previous_trap_marker"' "$signal"
  (
    for _ in {1..50}; do
      [[ -e "$ready" ]] && break
      sleep 0.1
    done
    kill -"$signal" "$test_pid" 2>/dev/null || true
  ) &
  signal_sender_pid="$!"
  if wp_task_run_capped "signal $signal fixture" 30 bash -c 'trap '\''touch "$2"; exit 0'\'' TERM; : > "$1"; while :; do :; done' _ "$ready" "$marker" > "$tmp/signal-$signal.stdout" 2> "$tmp/signal-$signal.stderr"; then
    echo "FAIL: $signal did not stop the capped operation." >&2
    exit 1
  else
    signal_status=$?
  fi
  wait "$signal_sender_pid" 2>/dev/null || true
  kill -"$signal" "$$"
  if [[ ! -e "$previous_trap_marker" ]]; then
    echo "FAIL: the original $signal trap was not restored after the capped operation." >&2
    exit 1
  fi
  trap - "$signal"
  if [[ "$signal_status" -ne "$expected_status" || ! -e "$marker" ]]; then
    echo "FAIL: $signal was not forwarded to the capped process group (status $signal_status)." >&2
    cat "$tmp/signal-$signal.stderr" >&2
    exit 1
  fi
  echo "ok:   $signal is forwarded to the capped operation and returns $expected_status"
}

run_forwarded_signal_test INT 130
run_forwarded_signal_test TERM 143
run_forwarded_signal_test HUP 129

operation_lock="$tmp/capped-operation.lock"
termination_marker="$tmp/capped-operation-terminated"
if ! wp_task_lock_acquire "$operation_lock"; then
  echo "FAIL: could not acquire the operation-cap fixture lock." >&2
  exit 1
fi
if [[ "$(cat "$operation_lock")" != "$$" ]]; then
  echo "FAIL: operation-cap fixture lock does not record the owner." >&2
  exit 1
fi

if wp_task_run_capped "test fixture" 1 bash -c 'trap '\''touch "$1"; exit 0'\'' TERM; while :; do sleep 30; done' _ "$termination_marker" > "$tmp/cap.stdout" 2> "$tmp/cap.stderr"; then
  echo "FAIL: a stuck operation completed instead of hitting its cap." >&2
  exit 1
else
  cap_status=$?
fi
if [[ "$cap_status" -ne 124 ]]; then
  echo "FAIL: the capped operation returned status $cap_status instead of 124." >&2
  cat "$tmp/cap.stderr" >&2
  exit 1
fi
if ! grep -Fq "ERROR: shared WordPress operation 'test fixture' exceeded its 1 second cap and was terminated." "$tmp/cap.stderr"; then
  echo "FAIL: operation-cap timeout did not explain that the operation was terminated." >&2
  cat "$tmp/cap.stderr" >&2
  exit 1
fi
if [[ ! -e "$termination_marker" ]]; then
  echo "FAIL: operation-cap timeout did not terminate the command." >&2
  exit 1
fi
if [[ "$(cat "$operation_lock")" != "$$" ]]; then
  echo "FAIL: operation-cap timeout changed the active lock owner." >&2
  exit 1
fi
wp_task_lock_release
if [[ -e "$operation_lock" ]]; then
  echo "FAIL: operation-cap fixture lock was not released." >&2
  exit 1
fi
echo "ok:   a stuck operation is terminated at its cap with a clear error"
