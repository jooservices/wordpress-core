# JOOS-99 — Runtime-aware compatibility matrix

## Changes

- Added runtime metadata and result statuses to compatibility reports, including
  `UNSUPPORTED` and `BLOCKED`, and made the generated compatibility page use
  the newest result for each plugin.
- Added bounded nightly jobs: seven plugin groups and nine WordPress/PHP runtime
  cells, each producing a separate report.
- Added matrix helper tests and a browser compatibility smoke with theme CSS
  checks on the shared Playwright runner.
- Updated the 1.0 compatibility and support targets in the buyer documentation.

## Verification

| Check | Result | Evidence |
| --- | --- | --- |
| `make lint` | Pass | Header check, PHPCS (279 files; 1 skipped), ESLint, Stylelint, and Prettier. |
| `make analyse` | Pass | PHPStan core and themes report no errors; both TypeScript configs pass; Deptrac reports 0 violations and 0 uncovered dependencies. |
| PHPUnit unit suites | Pass | 272 tests / 1,408 assertions. |
| Vitest | Pass | Final shared-runtime run with `--pool=threads --maxWorkers=1`: 260 tests, 0 failures, 0 errors in `artifacts/reports/vitest/junit.xml`. Earlier fork-pool runs hit worker-start timeouts. |
| `make test-tooling` | Pass | Quality-tool failure fixtures, compatibility-doc generation/determinism, bundle checks, i18n checks, and commit-hook checks pass. |
| Runtime compatibility smoke | Pass | 1 pass / 0 fail on WordPress 7.1.3, PHP 8.3.35, MariaDB 12.3.3-MariaDB-ubu2404. Evidence: `artifacts/reports/matrix/jooservices-latest-php8.3-2026-10-16.json`. |
| Plugin compatibility matrix | Blocked | No plugin batch was run: the single shared WordPress lock was held by a live JOOS-90 integration process (PID 39004) when checked. The generated page does not claim new plugin results. |
| `make docs-check` | Fail | The generated `docs/reference/surface.md` is stale after the merged upstream documentation changes. It is outside this ticket’s files and was left untouched. |
| `make release THEME=jooservices` | Pass | Built reproducible release artifacts: theme 299 files / 717 KB; Resume plugin 35 files / 67 KB; ThemeForest archive 1,864 KB. |
| Merge current `develop` | Pass | Merged `8a1adef` (JOOS-120) as `955ea66`; automatic merge completed without conflicts. |

The plugin matrix remains the review limitation. Its seven bounded commands are
listed in `.github/workflows/nightly.yml`; they still need to run after the
shared WordPress lock is free. The full nine-cell runtime workflow and full
integration, E2E, and performance suites were not run locally.

## QA addendum (Claude)

- Merged `develop` again (JOOS-89 and later) and regenerated `docs/reference/surface.md` (it was stale after the Resume features, `make docs-check` failed on `develop`); lint, analyse, unit (PHPUnit 272, Vitest 260) and `make docs-check` pass.
- Ran the first plugin group of the nightly matrix on the shared stack (`MATRIX_VARIANT=latest-php8.3-04-forms`, child theme): the pipeline works end to end (installs each plugin, runs the browser checks, writes the JSON and Markdown report) and reported FAIL for Contact Form 7, WPForms Lite and Fluent Forms with `layout shift budget` on home, category and tag. The same failure for all three on every view points at the measurement or at a shared effect of form plugins, so it is tracked as a separate finding, not as a defect of the tooling.
