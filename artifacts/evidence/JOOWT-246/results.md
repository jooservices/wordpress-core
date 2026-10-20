# JOOWT-246 Core Block Cross-Engine E2E

Run on 2026-10-20 UTC (2026-10-21 in Bangkok) using the shared `scripts/docker/e2e-vm` runner. The Playwright process ran in shared `wp-web`; WebKit and Firefox browsers ran on the Dev VM.

## Results

| Engine | Tests | Failures | Engine limits |
| --- | ---: | ---: | --- |
| WebKit | 4 passed | 0 | None observed |
| Firefox | 4 passed | 0 | None observed |

Both runs exercised `tests/e2e/specs/shared/core-blocks-rtl.spec.ts`: the fixture coverage check, RTL layout and screenshots for every core block, terminal dock positioning, and LTR icon direction. No core defect was found, so no core implementation fix was needed.

## Evidence

- [WebKit JUnit](webkit/reports/junit.xml) and [HTML report](webkit/reports/html/index.html)
- [Firefox JUnit](firefox/reports/junit.xml) and [HTML report](firefox/reports/html/index.html)
- Per-block screenshots and full-page captures are in each engine's `screenshots/` directory.

## Reproduction

For each engine, the run used `E2E_ENGINES=<engine>` with its matching `--project=<engine>` and the shared core-block RTL spec path. Reports and screenshots were staged under `/tmp` in `wp-web` because its test process runs as `www-data`; the outputs were then copied into this evidence folder.
