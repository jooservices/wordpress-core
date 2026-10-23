# JOOWP-301 layout stability evidence (2026-10-23)

Theme: `jooservices`. Required runtime: WordPress 7.1.3 / PHP 8.3.30.

## Environment limitation

The baseline browser smoke and plugin matrix use the shared WordPress site and Dev VM browser. The owner amendment dated 2026-10-23 places Lighthouse evidence through the Dev VM out of scope and requires the report to list evidence not produced. No baseline or matrix browser evidence was produced for this report.

## Acceptance criteria

| Criterion | Status | Evidence |
| --- | --- | --- |
| AC1: Baseline browser smoke stays within the existing CLS budget on home, category, tag, search, and single pages with no third-party plugin active. | Unconfirmed | The baseline runtime check and browser smoke were not run for this report. No current five-route CLS measurements were produced. The existing budget remains 0.02. |
| AC2: The jooservices plugin matrix has no failure caused by the layout-shift budget on WordPress 7.1.3 / PHP 8.3. | Unconfirmed | `make test-matrix THEME=jooservices` was not run for this report. No current per-plugin CLS results were produced. |

## Evidence not produced

- `MATRIX_VARIANT=baseline-no-third-party make test-matrix THEME=jooservices ARGS="--runtime-only"` was not run; no baseline runtime or browser-smoke measurements were produced for home, category, tag, search, or single.
- `make test-matrix THEME=jooservices` was not run; no WordPress 7.1.3 / PHP 8.3.30 per-plugin layout-shift outcomes were produced.
- Lighthouse/browser evidence through the Dev VM. The owner amendment dated 2026-10-23 places this evidence out of scope.

The archived [JOOWT-239 baseline report](jooservices-baseline-no-third-party-2026-10-20.md) and [plugin matrix report](jooservices-wp-7.1.3-php-8.3.30-2026-10-20.md) are historical evidence from 2026-10-20, before the JOOWP-301 code change; they do not confirm either criterion for this change.

## Runnable verification

The static regression guard is `themes/jooservices/tests/layout-stability.test.mjs`; both checks passed as part of Vitest.

| Command | Result |
| --- | --- |
| `composer install --no-interaction --prefer-dist` | Pass |
| `npm ci --no-audit --no-fund` | Pass |
| `make lint` | Pass |
| `make analyse` | Pass: PHPStan, TypeScript, and Deptrac report zero errors, warnings, violations, or uncovered rules. |
| `make test-unit` | Pass: PHPUnit 288 tests / 3,266 assertions; Vitest 49 files / 373 tests. |

These runnable checks do not confirm AC1 or AC2 at runtime.
