# Definition of Done

## Quality gates
| Gate | Command | Pass criteria |
|---|---|---|
| G1 Lint | `make lint` | PHPCS, ESLint, Stylelint, Prettier check, header check: zero errors |
| G2 Static | `make analyse` | PHPStan level 8 (core) / 6 (themes, plugin), `tsc --checkJs`, deptrac: zero errors |
| G3 Unit | `make test-unit` | PHPUnit unit and Vitest pass; coverage: core >= 85%, content plugin >= 80%, themes PHP >= 70% |
| G4 Integration | `make test-integration` | wp-phpunit passes on WordPress 6.6 and latest |
| G5 E2E | `make test-e2e THEME=<slug>` | Playwright passes at 390/820/1440 and axe finds no serious/critical violations |
| G6 Demo | `make demo THEME=<slug>` | clean checkout: WordPress installed, theme active, demo seeded, health check OK, all demo URLs as expected |
| G7 Budgets | `make perf THEME=<slug>` | Lighthouse mobile: Performance >= 95, SEO = 100, Accessibility >= 95, Best Practices >= 95; LCP <= 2.0s, CLS <= 0.05, TBT <= 150ms; article JS <= 30KB gzip, CSS <= 40KB gzip; lazy on and off |
| G8 Matrix | `make test-matrix THEME=<slug>` | every Tier-1 plugin: activate, render six URL types, no PHP notices, no console errors, CLS delta <= 0.02 |
| G9 Package | `make package THEME=<slug>` | zip builds with `core/`, passes Theme Check, no dev files, checksum recorded |

Lab measurements only; field metrics are not measured.

## Per level
- **Sub-task**: acceptance criteria met, tests written and passing, lint and static clean for touched code, committed with `JOOWT-<n>:`.
- **Story**: all Sub-tasks done, story acceptance criteria evidenced, docs updated, completion comment posted.
- **Epic**: all Stories done, gates G1..G6 pass on a clean checkout (G7..G9 where a theme ships), evidence report written.
- **Theme (owner rule)**: developed; unit tests pass; Docker installs WordPress + theme + seeded demo (`make demo`); demo presents every view; G7..G9 pass; evidence report saved.
