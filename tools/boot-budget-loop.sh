#!/usr/bin/env bash
# Runs the real-Kernel boot probe (tools/boot-units-probe.php, the same BootBudget judgement as KernelTest) N times (default 20) while background loops of the same boots load the CPU.
# The loop of boots is the artificial load; no stress tool is used. Usage: tools/boot-budget-loop.sh [runs] [load-workers]
set -uo pipefail
cd "$(dirname "$0")/.."
runs="${1:-20}"
workers="${2:-4}"
probe=tools/boot-units-probe.php
run() { php "$probe"; }
# BOOT_BUDGET_CHECK overrides the judged command (e.g. "php vendor/bin/phpunit --filter BootBudgetTest"); the load stays the probe.
check() { if [[ -n "${BOOT_BUDGET_CHECK:-}" ]]; then eval "$BOOT_BUDGET_CHECK"; else run; fi; }

for ((w = 0; w < workers; w++)); do
  (while [[ ! -f "${TMPDIR:-/tmp}/boot-budget-loop.stop.$$" ]]; do run >/dev/null 2>&1; done) &
done

pass=0
fail=0
for ((i = 1; i <= runs; i++)); do
  if out="$(check 2>&1)"; then pass=$((pass + 1)); else fail=$((fail + 1)); echo "$out" | tail -15; fi
done
touch "${TMPDIR:-/tmp}/boot-budget-loop.stop.$$"
wait
rm -f "${TMPDIR:-/tmp}/boot-budget-loop.stop.$$"
echo "boot-budget loop: runs=$runs load-workers=$workers pass=$pass fail=$fail cpus=$(nproc)"
[[ "$fail" -eq 0 ]]
