# Core R8 security and accessibility report

- Ticket: JOOWP-274
- Review scope: R8.1–R8.5; consolidation only, no new security or accessibility review
- Consolidated: 2026-10-22
- Owner acceptance: **Pending** (required by JOOWP-274)

## Decision summary

All Critical or High findings recorded or evidenced by the R8.1–R8.5 work are resolved. The available review evidence identifies no remaining R8 Bugs to link. R8.3's SonarQube scan did not run because its service rejected the configured credentials; this is an open verification gap, not a confirmed vulnerability. The owner should accept the stated residual risks after reviewing this page.

Severity values from the core accessibility report and the R8.3 scan report are preserved. For findings whose R8.1, R8.2, or R8.5 change history did not record a severity, this report supplies a consolidation assessment based on the described impact. Those assessments are not CVSS scores.

## Findings and dispositions

| Review | Finding | Severity | Status | Disposition and evidence |
|---|---|---|---|---|
| R8.1 — public REST routes (JOOWT-269) | A public comments fragment could return a snapshot produced for a logged-in or identified commenter, or for a post that is not public. Shared caching could expose that personalized response. | High (consolidation assessment) | **Fixed** | Snapshot generation and reads are limited to anonymous requests for published, unprotected posts; requests with commenter cookies stay inline; the comments fragment returns `private, no-store`; snapshot keys were versioned. Regression coverage is in [ContentTest](../../packages/theme-core/tests/Integration/ContentTest.php) and [LazyTest](../../packages/theme-core/tests/Integration/LazyTest.php). |
| R8.2 — admin page and WP-CLI (JOOWT-270) | WP-CLI settings and cache commands did not enforce the settings management capability for the selected WordPress user. | High (consolidation assessment) | **Fixed** | The settings, modules, doctor, and cache commands use a shared capability guard. The admin settings flow continues to check capability and nonce. Coverage is in [AdminTest](../../packages/theme-core/tests/Integration/AdminTest.php) and the [CLI check](../../tests/e2e/cli.sh). |
| R8.3 — dependencies, secrets, and static scan (JOOWT-271) | Completed Composer, npm production, and Gitleaks scans found no vulnerabilities or secrets. Composer reported abandoned dev-only `qossmic/deptrac`, a maintenance notice rather than a vulnerability. The SonarQube scan could not run because the service rejected authentication. | None observed in completed scans; unknown for the unrun scan | **Partial; verification gap open** | SonarQube authentication returned `valid=false` and project search returned 401, so no static-analysis result exists. The R8.3 evidence is recorded in commit `ceaad61`; its report is not present in this worktree. Restore valid service authentication and run the scan before treating static-analysis coverage as complete. |
| R8.4 — core blocks accessibility (JOOWT-272) | Author-box secondary text failed axe `color-contrast` because of `opacity: 0.8`. | Serious (axe) | **Fixed** | The opacity rule was removed. The [core accessibility report](core-a11y.md) records 12 passing browser checks across phone, tablet, and desktop and no other axe issues. No follow-up Bug was identified. |
| R8.5 — terminal accessibility (JOOWT-273) | Tab completion could keep focus in the command input when there was nothing to complete; announcing the full output log could overwhelm screen-reader users. | Moderate (consolidation assessment) | **Fixed** | Tab now moves focus normally when completion is unavailable; terminal output is not a live region, and a bounded polite status announces concise results. Focus styling and keyboard flows also have regression coverage in [terminal unit tests](../../packages/theme-core/resources/js/terminal/ui.test.js), [announcer tests](../../packages/theme-core/resources/js/terminal/announcer.test.js), and the [terminal browser spec](../../themes/jooservices/tests/e2e/terminal.spec.ts). No separate R8.5 results report was present in this worktree. |

## JOOWP-274 verification

| Check | Result |
|---|---|
| `make docs-check` | Pass. |
| Report link check | Pass. All local links in this report resolve. |
| `make lint` | Pass on rerun after the temporary package files from the failed test were gone. |
| `make analyse` | Pass. |
| `make test-unit` | PHPUnit passed: 288 tests / 3,266 assertions. Vitest passed 367 of 369 tests; the terminal UI and announcer suites passed. Two packaging tests could not find the `zip` executable (`spawnSync zip ENOENT`), so the full unit gate did not pass. |

## Remaining Bugs

No open R8 Bug issue key was found in the available R8.1–R8.5 evidence, and no confirmed Bug remains to link. The outstanding SonarQube run is an uncompleted verification task, not a confirmed product defect; the R8.3 evidence did not assign it a Bug.

## Residual risk for owner acceptance

- The R8.3 SonarQube static scan is outstanding. Completed dependency and secret scans found no Critical or High vulnerability, but they do not substitute for the missing static-analysis result.
- The R8.1, R8.2, and R8.5 changes are present in the merged code and regression tests, but their review findings and severity ratings were not preserved in standalone reports in this worktree. The dispositions above are consolidated from those changes and tests.
- The R8.4 browser audit used the Jooservices consumer fixture and the recorded viewport sizes. It does not certify every third-party theme or assistive-technology combination.
- The R8.5 change history includes unit and browser regression tests, but no standalone report of a screen-reader compatibility matrix.

**Owner decision:** Pending  
**Accepted by:** Pending  
**Date:** Pending
