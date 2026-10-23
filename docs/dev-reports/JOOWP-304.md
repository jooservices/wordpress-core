# JOOWP-304 verification

Date: 2026-10-23 UTC

## Change verified

The perf route accepts JOOWP worktrees. `make perf` delegates the Lighthouse browser to the Dev VM through `docker/scripts/perf-vm.sh`, and `tools/perf.mjs` connects to the supplied remote Chrome DevTools endpoint. Without a remote browser, it checks for a local Chromium executable and reports a clear error when it is missing. Regression coverage checks JOOWP ticket routing and the remote and missing-local-browser paths.

The CI failure was in `tests/tooling/ticket-prefix.sh`: its temporary branch check inherited the parent make process's exported `JOOS_TICKET`. In detached CI checkouts that value is empty, preventing the child Makefile from deriving the ticket from its temporary branch. The check now unsets `JOOS_TICKET` for that isolated Make invocation.

The branch retains the checkout-relative theme budget resolution coverage from the merge with develop.

## Checks

| Check | Result |
| --- | --- |
| `composer install --no-interaction --prefer-dist` | Passed |
| `npm ci --no-audit --no-fund` | Passed |
| `make lint` | Passed |
| `make analyse` | Passed: 0 violations, 0 uncovered dependencies, 0 warnings, 0 errors |
| `JOOS_TICKET= make test-tooling` | Passed, including the JOOWP ticket-prefix regression |
| `make test-unit` | Passed: 288 PHPUnit tests / 3,266 assertions; Vitest 48 files / 373 tests |

## Lighthouse evidence not produced

Per the owner amendment, the Dev VM Lighthouse run is out of scope for this completion. `make perf THEME=jooservices` was not run, so this worktree has no completed Lighthouse result or run evidence for AC1/AC2. The existing `artifacts/reports/perf/jooservices.json` predates this change and is not evidence from this run.
