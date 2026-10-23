# JOOWP-243 — stable boot-cost budget (rework)

## Approach

- `BootBudget` no longer has a hard-coded idle reference. A fixed CPU workload (the "spin", one unit, about the 3 ms spec target) is timed next to every boot sample, alternating before/after so neither side is always the one that runs second on a busy host.
- The boot cost is judged as `units = typical boot / typical spin` of the same run (typical = median of the fastest third, so scheduler preemption only ever adds noise to the slow samples). The ceiling is `MAX_BOOT_UNITS = 0.55`, so a doubled boot fails, while a busy host stretches both boot and spin and does not.
- The per-module millisecond budget (`maxBootMs()`) is scaled by `hostFactor` = median spin / fastest spin of the run (the idle baseline comes from the run), measured around the boots.
- `KernelTest::test_boot_cost_is_small` and `ModuleContractTest::test_boot_stays_within_the_budget` (the WordPress 6.6 matrix runs it) both assert the whole-boot units ceiling.
- Negative tests: `KernelTest::test_slow_module_exceeds_the_boot_time_budget` (injected `SlowBootModule`, clean boot of the same run must pass); `BootBudgetTest` measures real stand-in boots, with and without an injected 2x slowdown, and a busy-host (all timings x4.2) case.

## Doubled-boot check

The fixed 0.55-unit ceiling alone does not reject a doubled boot when the healthy boot is cheap. `KernelTest::test_doubled_boot_cost_exceeds_the_same_run_healthy_baseline` measures healthy boots and boots timed twice per sample (2x cost) in the same run and requires doubled > 1.5x healthy.

## AC4: JOOS-123

Removed by the owner amendment of 2026-10-23: the ticket is complete once the boot-cost test is stable and this report states what it covers. JOOS-123 is not closed by this change.

## Coverage — what ran and what did not

- Executed: `BootBudgetTest` (statistics, injected 2x slowdown, x4.2 busy host) under `make test-unit`, plus lint and analyse.
- **Not executed:** `KernelTest` (including the new tests) and `ModuleContractTest` (the WordPress 6.6 matrix path). They need the integration environment (`make test-integration`: WordPress install, Docker configuration), which is not available here.
- **No 20-run evidence is delivered.** An earlier probe script and loop, which booted the real Kernel on a hook stub, were removed as out of scope; they never executed `KernelTest` itself, so the "20 runs under load, no flake" test case is not demonstrated for `KernelTest`. It must be run in the integration environment; the first run prints `[boot cost] N units`, and if N is above about 0.5, raise `SPIN_ITERATIONS` rather than the ceiling.

## Other

`artifacts/package/jooservices-resume-0.1.0.zip` is restored on disk from `origin/develop` (92832 bytes). It is git-ignored, so it needs `git add -f` to stop showing as deleted; staging was refused by the sandbox policy here, so AI Flow must force-add it. The CI workflow and `phpstan-themes.neon` are back to their `develop` content.
