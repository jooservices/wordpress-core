# JOOWT-287 runtime bundle evidence

Date: 2026-10-20. Machine: WSL. Initial base: `45d0ee0`.

## Acceptance results

- `tools/runtime-bundle.mjs list`: 244 files, 786 KB, below the 1.5 MB limit. The captured output is in [`runtime-bundle-list.txt`](runtime-bundle-list.txt); it contains no editor tests, frontend source assets, or source maps. PHP files under `src/` are the core runtime implementation.
- Starter package build: passed; 275 files, 477 KB. The built core check passed: 244 files, 786 KB.
- JOOservices package build (including its bundled resume plugin): passed; 311 files, 836 KB. The built core check passed: 244 files, 786 KB.
- `tools/build-theme.test.mjs`: passed, 6 tests.
- `make lint`: passed (headers, core i18n, PHPCS, ESLint, Stylelint, Prettier).
- `make test-unit`: passed (284 PHPUnit tests and 345 Vitest tests).

## Commands

```sh
/home/joos/Workspaces/scripts/docker/runtime node node tools/runtime-bundle.mjs list
/home/joos/Workspaces/scripts/docker/runtime php node tools/build-theme.mjs --theme starter --out artifacts/package/JOOWT-287
/home/joos/Workspaces/scripts/docker/runtime node node tools/runtime-bundle.mjs check artifacts/package/JOOWT-287/starter/core
/home/joos/Workspaces/scripts/docker/runtime php node tools/build-theme.mjs --theme jooservices --include-plugin jooservices-resume --out artifacts/package/JOOWT-287
/home/joos/Workspaces/scripts/docker/runtime node node tools/runtime-bundle.mjs check artifacts/package/JOOWT-287/jooservices/core
make lint
make test-unit
```

## Build and check output

```text
built artifacts/package/JOOWT-287/starter-0.1.0.zip: 275 files, 477 KB (core 244 files)
ok   244 files, 786 KB
built artifacts/package/JOOWT-287/jooservices-0.1.0.zip: 311 files, 836 KB (core 244 files)
ok   244 files, 786 KB
```
