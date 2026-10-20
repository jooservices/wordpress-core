#!/usr/bin/env bash
# Serialize operations that exercise the shared WordPress web runtime.
# flock protects only short owner-marker updates, so child processes cannot
# keep the guard locked after the wp-task.sh owner exits.

WP_TASK_LOCK_WAIT_SECONDS=1200
WP_TASK_LOCK_PATH=""
WP_TASK_LOCK_HELD=0

wp_task_lock_duration() {
  local seconds="$1"
  local count suffix
  if ((seconds % 60 == 0)); then
    count="$((seconds / 60))"
    suffix="s"
    ((count == 1)) && suffix=""
    printf '%s minute%s' "$count" "$suffix"
  else
    count="$seconds"
    suffix="s"
    ((count == 1)) && suffix=""
    printf '%s second%s' "$count" "$suffix"
  fi
}

wp_task_lock_acquire() {
  local lock_path="${1:?lock path is required}"
  local wait_seconds="${2:-$WP_TASK_LOCK_WAIT_SECONDS}"
  local guard_path owner_pid duration waited=0

  if [[ ! "$wait_seconds" =~ ^[0-9]+$ ]] || ((wait_seconds < 1)); then
    echo "ERROR: shared WordPress test lock wait must be a positive number of seconds." >&2
    return 2
  fi
  if ! command -v flock >/dev/null 2>&1; then
    echo "ERROR: flock is required to coordinate the shared WordPress test lock." >&2
    return 2
  fi

  mkdir -p "$(dirname "$lock_path")" || return 1
  guard_path="${lock_path}.flock"
  exec 9> "$guard_path" || {
    echo "ERROR: could not open the shared WordPress test lock guard: $guard_path" >&2
    return 1
  }

  while :; do
    if ! flock -x 9; then
      echo "ERROR: could not acquire the shared WordPress test lock guard: $guard_path" >&2
      exec 9>&-
      return 1
    fi

    # Inspect the owner before sleeping. Protect marker cleanup and creation
    # with the short-lived guard so concurrent waiters cannot remove a new PID.
    owner_pid="$(cat "$lock_path" 2>/dev/null || true)"
    if [[ "$owner_pid" =~ ^[1-9][0-9]*$ ]] && ! kill -0 "$owner_pid" 2>/dev/null; then
      rm -f -- "$lock_path" || {
        echo "ERROR: could not remove stale shared WordPress test lock: $lock_path" >&2
        flock -u 9 || true
        exec 9>&-
        return 1
      }
      echo "Removed stale shared WordPress test lock for dead owner PID $owner_pid." >&2
      owner_pid=""
    fi

    if [[ ! -e "$lock_path" ]]; then
      if ! printf '%s\n' "$$" > "$lock_path"; then
        echo "ERROR: could not record the shared WordPress test lock owner: $lock_path" >&2
        flock -u 9 || true
        exec 9>&-
        return 1
      fi
      WP_TASK_LOCK_PATH="$lock_path"
      WP_TASK_LOCK_HELD=1
      flock -u 9 || true
      exec 9>&-
      return 0
    fi

    flock -u 9 || true
    if ((waited >= wait_seconds)); then
      duration="$(wp_task_lock_duration "$wait_seconds")"
      if [[ "$owner_pid" =~ ^[1-9][0-9]*$ ]] && kill -0 "$owner_pid" 2>/dev/null; then
        echo "ERROR: timed out after $duration waiting for the shared WordPress test lock (owner PID $owner_pid is still running)." >&2
      else
        echo "ERROR: timed out after $duration waiting for the shared WordPress test lock." >&2
      fi
      exec 9>&-
      return 1
    fi
    sleep 1
    waited=$((waited + 1))
  done
}

wp_task_lock_release() {
  ((WP_TASK_LOCK_HELD)) || return 0

  local guard_path="${WP_TASK_LOCK_PATH}.flock"
  local owner_pid=""
  exec 9> "$guard_path" || {
    echo "ERROR: could not open the shared WordPress test lock guard during release: $guard_path" >&2
    WP_TASK_LOCK_PATH=""
    WP_TASK_LOCK_HELD=0
    return 0
  }
  if flock -x 9; then
    owner_pid="$(cat "$WP_TASK_LOCK_PATH" 2>/dev/null || true)"
    if [[ "$owner_pid" == "$$" ]]; then
      rm -f -- "$WP_TASK_LOCK_PATH" || echo "ERROR: could not remove shared WordPress test lock: $WP_TASK_LOCK_PATH" >&2
    fi
    flock -u 9 || true
  else
    echo "ERROR: could not acquire the shared WordPress test lock guard during release: $guard_path" >&2
  fi
  exec 9>&-
  WP_TASK_LOCK_PATH=""
  WP_TASK_LOCK_HELD=0
}
