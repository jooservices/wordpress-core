# JOOWT-246 Core Shared E2E: WebKit and Firefox

Run on 2026-10-21 against the seeded `jooservices` worktree site. Playwright 1.64.1 ran in the shared `wp-web` runtime and connected to the WebKit and Firefox browsers on the Dev VM. Only `tests/e2e/specs/shared` was selected; theme-specific specs were excluded.

## Results

| Engine | Tests | Failures | Engine limits |
| --- | ---: | ---: | --- |
| WebKit | 38 passed | 0 | None observed |
| Firefox | 38 passed | 0 | None observed |

Each engine ran all seven shared spec files: `content-api` (8), `core-accessibility` (4), `core-blocks-rtl` (4), `http` (6), `seo` (5), `smoke` (3), and `views` (8). No core defect was found, so no core implementation fix was needed.

The accessibility spec expects the responsive sidebar rendered by `jooservices`; the initial WebKit probe against `starter` lacked that theme-specific control. Both recorded runs therefore use the seeded `jooservices` site, with the same theme-agnostic shared specs.

## Evidence

- [WebKit JUnit](webkit/reports/junit.xml) and [HTML report](webkit/reports/html/index.html)
- [Firefox JUnit](firefox/reports/junit.xml) and [HTML report](firefox/reports/html/index.html)
- Each engine's 18 RTL screenshots are in its `screenshots/` directory.

## Reproduction

From this worktree, run each command separately:

```sh
make test-e2e THEME=jooservices E2E_ENGINES=webkit \
  E2E_ARTIFACT_DIR="$PWD/artifacts/evidence/JOOWT-246/webkit" \
  ARGS="tests/e2e/specs/shared --project=webkit"

make test-e2e THEME=jooservices E2E_ENGINES=firefox \
  E2E_ARTIFACT_DIR="$PWD/artifacts/evidence/JOOWT-246/firefox" \
  ARGS="tests/e2e/specs/shared --project=firefox"
```
