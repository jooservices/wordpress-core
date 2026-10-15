#!/usr/bin/env bash
# Asserts that `make help` lists every documented target and the guard rails work.
set -u
cd "$(dirname "$0")/.."
out="$(make help)" || { echo "FAIL: make help exited non-zero"; exit 1; }
for t in up down reset shell wp logs link-core init-env lint analyse test-unit test-integration test-e2e demo health perf test-matrix package docs hooks clean; do
  grep -qE "^  $t " <<<"$out" || { echo "FAIL: target '$t' missing from make help"; exit 1; }
done
make reset >/dev/null 2>&1 && { echo "FAIL: reset ran without CONFIRM=yes"; exit 1; }
make demo THEME=nope >/dev/null 2>&1 && { echo "FAIL: demo accepted a missing theme"; exit 1; }
echo "make help ok"
