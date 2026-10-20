# Core block E2E evidence

- Source report: [Core block accessibility report](../../../../docs/dev-reports/core-a11y.md), JOOWT-272.
- Audit date: 2026-10-21; machine: WSL; consumer fixture: `jooservices` on WordPress 7.1.3.
- Command: `make test-e2e THEME=jooservices ARGS="tests/e2e/specs/shared/core-accessibility.spec.ts --reporter=list --output=/tmp/joowt-272-qa"`.
- Result: **12 passed** across phone, tablet, and desktop projects. The targeted audit covered the core blocks with axe and keyboard interaction checks; no axe issues remained after the reported contrast finding was fixed.

This is evidence for the targeted core-block accessibility E2E spec. It is not evidence that the entire configured `make test-e2e` suite ran. The source report does not record the tested Git commit, so this artifact does not claim a same-commit G5 result for the current `develop` snapshot. This file transcribes the existing audit and records no new measurement.
