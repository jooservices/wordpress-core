#!/usr/bin/env bash
# Serialize operations that exercise the shared WordPress web runtime.
# flock protects only short owner-marker updates, so child processes cannot
# keep the guard locked after the wp-task.sh owner exits.

WP_TASK_LOCK_WAIT_SECONDS=1200
WP_TASK_OPERATION_CAP_SECONDS=1200
WP_TASK_MATRIX_OPERATION_CAP_SECONDS=3600
WP_TASK_LOCK_PATH=""
WP_TASK_LOCK_HELD=0

wp_task_timeout_command() {
  if command -v timeout >/dev/null 2>&1; then
    command -v timeout
  elif command -v gtimeout >/dev/null 2>&1; then
    command -v gtimeout
  else
    return 1
  fi
}

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

wp_task_run_capped() {
  local operation="${1:?operation name is required}"
  local cap_seconds="${2:-$WP_TASK_OPERATION_CAP_SECONDS}"
  local duration status timeout_command timeout_pid="" interrupted_status=0
  local previous_int_trap previous_term_trap previous_hup_trap
  shift 2

  if [[ ! "$cap_seconds" =~ ^[0-9]+$ ]] || ((cap_seconds < 1)); then
    echo "ERROR: shared WordPress operation cap must be a positive number of seconds." >&2
    return 2
  fi
  if ! timeout_command="$(wp_task_timeout_command)"; then
    echo "ERROR: timeout (or gtimeout) is required to cap shared WordPress operations." >&2
    return 2
  fi

  previous_int_trap="$(trap -p INT)"
  previous_term_trap="$(trap -p TERM)"
  previous_hup_trap="$(trap -p HUP)"
  trap 'interrupted_status=130; wp_task_forward_timeout_signal "$timeout_pid"' INT
  trap 'interrupted_status=143; wp_task_forward_timeout_signal "$timeout_pid"' TERM
  trap 'interrupted_status=129; wp_task_forward_timeout_signal "$timeout_pid"' HUP

  "$timeout_command" --signal=TERM --kill-after=5s "${cap_seconds}s" "$@" &
  timeout_pid="$!"
  if ((interrupted_status > 0)); then
    wp_task_forward_timeout_signal "$timeout_pid"
  fi

  while :; do
    if wait "$timeout_pid"; then
      status=0
    else
      status=$?
    fi
    if ((interrupted_status == 0)) || ! kill -0 "$timeout_pid" 2>/dev/null; then
      break
    fi
  done

  if [[ -n "$previous_int_trap" ]]; then eval "$previous_int_trap"; else trap - INT; fi
  if [[ -n "$previous_term_trap" ]]; then eval "$previous_term_trap"; else trap - TERM; fi
  if [[ -n "$previous_hup_trap" ]]; then eval "$previous_hup_trap"; else trap - HUP; fi

  if ((interrupted_status > 0)); then
    status="$interrupted_status"
  fi
  ((status == 0)) && return 0

  if ((status == 124 || status == 137)); then
    duration="$(wp_task_lock_duration "$cap_seconds")"
    echo "ERROR: shared WordPress operation '$operation' exceeded its $duration cap and was terminated." >&2
    return 124
  fi
  return "$status"
}

wp_task_forward_timeout_signal() {
  local timeout_pid="${1:-}"
  [[ "$timeout_pid" =~ ^[1-9][0-9]*$ ]] || return 0

  kill -TERM -- "-$timeout_pid" 2>/dev/null || kill -TERM "$timeout_pid" 2>/dev/null || true
}

wp_task_lock_owner_is_ancestor() {
  local owner_pid="${1:-}"
  local current_pid="${PPID:-}"
  local parent_pid

  [[ "$owner_pid" =~ ^[1-9][0-9]*$ ]] || return 1
  while [[ "$current_pid" =~ ^[1-9][0-9]*$ ]]; do
    [[ "$current_pid" == "$owner_pid" ]] && return 0
    parent_pid="$(ps -o ppid= -p "$current_pid" 2>/dev/null | awk 'NF { print $1; exit }')"
    [[ "$parent_pid" =~ ^[1-9][0-9]*$ && "$parent_pid" != "$current_pid" ]] || return 1
    current_pid="$parent_pid"
  done

  return 1
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
    if [[ -e "$lock_path" ]]; then
      if [[ "$owner_pid" =~ ^[1-9][0-9]*$ ]]; then
        if ! kill -0 "$owner_pid" 2>/dev/null; then
          rm -f -- "$lock_path" || {
            echo "ERROR: could not remove stale shared WordPress test lock: $lock_path" >&2
            flock -u 9 || true
            exec 9>&-
            return 1
          }
          echo "Removed stale shared WordPress test lock for dead owner PID $owner_pid." >&2
          owner_pid=""
        fi
      else
        rm -f -- "$lock_path" || {
          echo "ERROR: could not remove stale shared WordPress test lock: $lock_path" >&2
          flock -u 9 || true
          exec 9>&-
          return 1
        }
        echo "Removed stale shared WordPress test lock with an empty or invalid owner marker." >&2
        owner_pid=""
      fi
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
