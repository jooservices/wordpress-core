# JOOWP-304 verification

Date: 2026-10-23 UTC

## Change verified

The perf route accepts JOOWP worktrees. `docker/scripts/perf-vm.sh` now binds its temporary SSH local forward only to the Docker bridge gateway address (`docker network inspect bridge`, then `docker0`; override with `PERF_CDP_BIND`; a wildcard address is rejected) and does not use `ssh -g`, so the unauthenticated DevTools port is not exposed on other host interfaces. The Node runtime verifies `/json/version` at that address before Lighthouse starts.

`tools/perf.mjs` also checks the configured DevTools endpoint from the runtime before Lighthouse starts. This gives an early, explicit error when the runtime cannot reach Chromium. Regression coverage checks the public endpoint validation, JOOWP ticket routing, non-loopback forwarding, and the runtime reachability probe.

The CI failure was in `tests/tooling/ticket-prefix.sh`: its temporary branch check inherited the parent make process's exported `JOOS_TICKET`. In detached CI checkouts that value is empty, preventing the child Makefile from deriving the ticket from its temporary branch. The check now unsets `JOOS_TICKET` for that isolated Make invocation.

The branch retains the checkout-relative theme budget resolution coverage from the merge with develop.

## Checks

| Check | Result |
| --- | --- |
| `composer install --no-interaction --prefer-dist` | Passed |
| `npm ci --no-audit --no-fund` | Passed |
| `make lint` | Passed after formatting the test change |
| `make analyse` | Passed: 0 violations, 0 uncovered dependencies, 0 warnings, 0 errors |
| `bash tests/tooling/ticket-prefix.sh` | Passed, including the wildcard listener and Node runtime probe routing assertions |
| `make test-unit` | Passed: PHPUnit 288 tests / 3,266 assertions; Vitest 48 files / 375 tests |
| Node runtime connectivity smoke | Passed: the shared Node runtime reached a temporary DevTools-shaped endpoint through an enumerated non-loopback host interface using `checkRemoteChrome` |

## Lighthouse evidence not produced

Per the owner amendment, the Dev VM Lighthouse run is out of scope for this completion. `make perf THEME=jooservices` was not run, so a completed Lighthouse result and run evidence for AC1/AC2 were not produced. The runtime smoke test verifies the container-to-forward address path only; it is not a Lighthouse result. The existing `artifacts/reports/perf/jooservices.json` predates this change and is not evidence from this run.
