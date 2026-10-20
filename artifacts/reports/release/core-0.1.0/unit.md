# Core 0.1.0 unit test report

- Date: 2026-10-20
- Repository: `wordpress-core`
- Branch: `JOOWT-232-core-r1-2-run-unit-and-vitest-suites-and`
- Source commit tested: `93518f1` (`fix(docs): Register the demo importer meta keys and refresh the surface reference (JOOWT-293)`)
- Command: `make test-unit`
- Runtime: shared Workspaces CLI runtime; PHPUnit 9.6.38 and Vitest 5.0.4
- Result: **Pass**

| Suite | Tests | Assertions reported | Skipped | Result |
| --- | ---: | ---: | ---: | --- |
| PHPUnit unit suites | 280 | 1,442 | 0 | Pass |
| Vitest | 318 across 45 files | Not reported by the configured Vitest reporter | 0 | Pass |
| **Total** | **598** | **1,442 PHPUnit assertions** | **0** | **Pass** |

The Vitest default and JUnit reporters report test totals, but do not expose a total count of `expect` calls. Its JUnit output recorded 318 tests, 0 failures, 0 errors, and 0 skipped tests. PHPUnit reported `OK (280 tests, 1442 assertions)` with no incomplete or skipped tests.

The first `make test-unit` invocation could not start because this worktree did not have `vendor/bin/phpunit`. `make deps` installed the locked PHP and JavaScript dependencies through the shared runtime; the complete `make test-unit` command then passed. No tests were changed or removed, and no suite failures or skips required fixes or explanations.
