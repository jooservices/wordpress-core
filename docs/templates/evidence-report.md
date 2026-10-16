# Release evidence: <theme> <version>

Date: <yyyy-mm-dd>. Commit: <hash>. Remote pushes: none unless stated.

## Definition of done
| Criterion | Evidence (command, count, link) |
|---|---|
| Developed | |
| Unit tests | |
| Docker installs WordPress and the theme; demo presents it | `make demo THEME=<slug>`, `make health` |
| E2E in Docker | Playwright totals per project |
| Integration (latest and minimum WordPress) | |
| Plugin matrix | pass / fail / blocked / unsupported / skipped with reasons, versions, runtime, and date |
| Performance and SEO | `make perf` table |
| Package | zip name, size, clean install check |
| Static gates | lint, analyse, registry, schema, i18n, tooling tests |

## Core reuse
Output of `tools/check-core-untouched.sh`.

## Defects found and fixed during the work

## Known limits and manual checks still open
