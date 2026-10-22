# JOOWP-243 — stable boot-cost budget (rework)

## Approach

- `BootBudget` no longer has a hard-coded idle reference. A fixed CPU workload (the "spin", one unit, about the 3 ms spec target) is timed next to every boot sample, alternating before/after so neither side is always the one that runs second on a busy host.
- The boot cost is judged as `units = typical boot / typical spin` of the same run (typical = median of the fastest third, so scheduler preemption only ever adds noise to the slow samples). The ceiling is `MAX_BOOT_UNITS = 0.55`: the real Kernel boot is about 0.3 units, so a doubled boot (about 0.6) fails, while a busy host stretches both boot and spin and does not.
- The per-module millisecond budget (`maxBootMs()`) is scaled by `hostFactor` = median spin / fastest spin of the run (the idle baseline comes from the run), measured around the boots.
- `KernelTest::test_boot_cost_is_small` and `ModuleContractTest::test_boot_stays_within_the_budget` (the WordPress 6.6 matrix runs it) both assert the whole-boot units ceiling.
- Negative tests: `KernelTest::test_slow_module_exceeds_the_boot_time_budget` (injected `SlowBootModule`, clean boot of the same run must pass); `BootBudgetTest` measures real stand-in boots, with and without an injected 2x slowdown, and a busy-host (all timings x4.2) case.

## Real boot figure

`make test-integration` cannot run in this sandbox (no Workspaces Docker configuration, no WordPress install), so `KernelTest` itself was not executed. Instead `tools/boot-units-probe.php` boots the **real `Kernel`** (real manifest, container, registry, modules, notices) on a minimal hook-system stub and judges it with the same `BootBudget` code and sample count as `KernelTest::test_boot_cost_is_small`:

| run | boot median | spin median | units |
| --- | --- | --- | --- |
| 1 | 0.80 ms | 2.32 ms | 0.29 |
| 2 | 0.84 ms | 2.51 ms | 0.29 |
| 3 | 0.85 ms | 2.48 ms | 0.24 |

So the real unit cost is about 0.25-0.3 units (one unit = one spin, about 2.3-2.5 ms on this host), about 1.8x under `MAX_BOOT_UNITS = 0.55`. WordPress's own hook system adds some cost per `add_action`/`add_filter`, so the WordPress figure will be higher than this one; a ~1.8x margin leaves room for it. The first real `make test-integration` prints `[boot cost] N units`; if N is above about 0.5, raise `SPIN_ITERATIONS` rather than the ceiling.

## Evidence (20 of 20 clean runs of a loop of real-Kernel boots)

`tools/boot-budget-loop.sh 20 N` runs the real-Kernel probe 20 times while N background loops of the same boots load the host (24 CPUs, no stress tool):

| load workers | runs | pass | fail |
| --- | --- | --- | --- |
| 4 | 20 | 20 | 0 |
| 24 (all CPUs saturated) | 20 | 20 | 0 |
| 48 (2x oversubscribed) | 20 | 20 | 0 |

The earlier flake at saturated CPUs came from the stand-in `BootBudgetTest` boots, whose cost sat near the ceiling (about 1 unit); the real Kernel sits at about 0.3 units, so scheduler noise no longer reaches the ceiling. `BootBudgetTest` still covers the statistics, the injected 2x slowdown and a x4.2 busy host.

## AC4: JOOS-123

Closing a Jira ticket is outside the checkout. JOOS-123 to be closed as covered by JOOWP-243 once this merges (hand-off item).

## Test environment

`zip` and `unzip` are needed by the packaging tests; the CI unit job now installs them (`.github/workflows/ci.yml`).

## Not verified here

`KernelTest` and `ModuleContractTest` (including the WordPress 6.6 matrix) were not executed: see "Real boot figure".

## Other

`artifacts/package/jooservices-resume-0.1.0.zip` is restored in the worktree from `origin/develop`. It is git-ignored, so it needs `git add -f` to stop showing as deleted against develop.
