# JOOWP-243 — stable boot-cost budget (rework)

## Approach

- `BootBudget` no longer has a hard-coded idle reference. A fixed CPU workload (the "spin", one unit, about the 3 ms spec target) is timed next to every boot sample, alternating before/after so neither side is always the one that runs second on a busy host.
- The boot cost is judged as `units = typical boot / typical spin` of the same run (typical = median of the fastest third, so scheduler preemption only ever adds noise to the slow samples). The ceiling is `MAX_BOOT_UNITS = 1.5`: a healthy boot is about 1 unit, a doubled boot is about 2 and fails, a busy host stretches both and does not.
- The per-module millisecond budget (`maxBootMs()`) is scaled by `hostFactor` = median spin / fastest spin of the run (the idle baseline comes from the run), measured around the boots.
- `KernelTest::test_boot_cost_is_small` and `ModuleContractTest::test_boot_stays_within_the_budget` (the WordPress 6.6 matrix runs it) both assert the whole-boot units ceiling.
- Negative tests: `KernelTest::test_slow_module_exceeds_the_boot_time_budget` (injected `SlowBootModule`, clean boot of the same run must pass); `BootBudgetTest` measures real stand-in boots, with and without an injected 2x slowdown, and a busy-host (all timings x4.2) case.

## Evidence

`tools/boot-budget-loop.sh 20 N` runs `BootBudgetTest` 20 times while N background loops of the same boots load the host (24 CPUs, no stress tool):

| load workers | runs | pass | fail |
| --- | --- | --- | --- |
| 4 | 20 | 20 | 0 |
| 4 (second run) | 20 | 20 | 0 |
| 24 (all CPUs saturated) | 20 | 19 | 1 |
| 32 (1.33x oversubscribed) | 20 | 20 | 0 |

One flake appeared at fully saturated CPUs (twice-measured: 19/20 each time); at 2x oversubscription (48 workers) it flakes more (11/20 before the estimator and ordering changes, not re-run after). Realistic busy-host load (4 workers) did not flake.

## Not verified here

`make test-integration` cannot run in this sandbox (it needs the shared Workspaces Docker configuration, which does not exist here), so `KernelTest` and `ModuleContractTest` were not executed. `MAX_BOOT_UNITS = 1.5` assumes a real boot of about one spin (about 3 ms); the first integration run prints `[boot cost] N units` and should confirm N is well below 1.5 before merge.
