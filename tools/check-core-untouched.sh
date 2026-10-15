#!/usr/bin/env bash
# Reuse proof: a theme is built on core without changing core.
# Usage: tools/check-core-untouched.sh <base> [head] [theme]
# Fails when a commit in base..head changes files under themes/<theme>/ (any theme when omitted) and also changes
# packages/theme-core/. Core fixes then belong in their own commit, so every core change is reviewable on its own.
set -euo pipefail
cd "$(git rev-parse --show-toplevel)"
BASE="${1:?base ref}"
HEAD_REF="${2:-HEAD}"
THEME="${3:-}"
scope="themes/${THEME:+$THEME/}"
status=0
count=0
for commit in $(git rev-list --reverse "${BASE}..${HEAD_REF}"); do
  files="$(git diff-tree --no-commit-id --name-only -r "$commit")"
  if grep -q "^${scope}" <<<"$files"; then
    count=$((count + 1))
    if grep -q '^packages/theme-core/' <<<"$files"; then
      echo "MIXED: $(git log -1 --format='%h %s' "$commit") changes the theme and core together"
      status=1
    fi
  fi
done
[ "$status" -eq 0 ] && echo "core untouched by ${count} theme commit(s) in ${BASE}..${HEAD_REF}"
exit "$status"
