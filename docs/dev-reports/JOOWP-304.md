# JOOWP-304 verification

Date: 2026-10-23 UTC

## Change verified

The perf route accepts JOOWP worktrees. `make perf` delegates the Lighthouse browser to the Dev VM through `docker/scripts/perf-vm.sh`, and `tools/perf.mjs` connects to the supplied remote Chrome DevTools endpoint. If no remote browser is configured, it checks the local Chromium executable and reports a clear error when it is missing. Regression coverage checks JOOWP ticket routing through the perf scripts and the remote and missing-local-browser paths.

The merge with `develop` keeps both browser fallback coverage and checkout-relative theme budget resolution coverage in `tools/perf.test.mjs`.

## Checks

| Check | Result |
| --- | --- |
| `composer install --no-interaction --prefer-dist` | Passed |
| `npm ci --no-audit --no-fund` | Passed |
| `make lint` | Passed |
| `make analyse` | Passed: 0 violations, 0 uncovered dependencies, 0 warnings, 0 errors |
| `make test-tooling` | Passed, including the JOOWP ticket-prefix perf routing regression |
| `make test-unit` | Passed: 288 PHPUnit tests / 3,266 assertions; Vitest 48 files / 373 tests |

## Lighthouse evidence not produced

Per the owner amendment, the Dev VM Lighthouse run is out of scope for this completion. `make perf THEME=jooservices` was not run, so this worktree has no completed Lighthouse result or run evidence for AC1/AC2. Existing `artifacts/reports/perf/jooservices.json` is pre-existing and is not evidence from this change.

The supplied CI job log was not available in this environment.
