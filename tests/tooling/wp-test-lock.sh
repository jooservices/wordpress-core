#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd -P)"
source "$ROOT/docker/scripts/wp-task-lock.sh"

if [[ "$WP_TASK_LOCK_WAIT_SECONDS" -ne 1200 ]]; then
  echo "FAIL: the production shared WordPress test lock cap is not 20 minutes." >&2
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
