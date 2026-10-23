# JOOWP-303 performance budget resolution

Date: 2026-10-23 (UTC)

## Change

`make perf THEME=starter` routes the Lighthouse command through the current checkout's `docker/scripts/runtime.sh`. That wrapper sets the selected worktree as the working directory before invoking the shared Node runtime. The Lighthouse CDP host and port are now included in the wrapper's environment pass-through list. `tools/perf.mjs` resolves budgets from the running checkout and reports the absolute budget path when it cannot read the file.

The starter budgets remain at `themes/starter/budgets.json`. The file and its budget values were not changed; `docs/reference/budgets.md` already documents that path and the same thresholds.

## Validation

- `composer install --no-interaction --prefer-dist`: passed.
- `npm ci --no-audit --no-fund`: passed.
- `make lint`: passed.
- `make analyse`: passed with 0 violations, 0 errors, and 0 uncovered dependencies.
- `make test-unit`: passed; PHPUnit 288 tests / 3,266 assertions, Vitest 48 files / 371 tests. The perf suite includes starter path resolution and missing-path diagnostics.
- `bash -n docker/scripts/perf-vm.sh docker/scripts/runtime.sh`: passed; a host-wrapper smoke check resolved all five starter pages.

## Lighthouse evidence not produced

Per the 2026-10-23 owner amendment, a Lighthouse run through the shared Workspaces runtime is out of scope. No fresh `make perf THEME=starter` result or current-worktree Lighthouse JSON is claimed here. The committed JOOWT-288 report and `artifacts/reports/perf/starter.json` are historical evidence and were not treated as a run of this change.
