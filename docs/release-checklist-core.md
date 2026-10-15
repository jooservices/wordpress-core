# Core release checklist

1. Decide the version (SemVer). Public API = symbols in `docs/api/baseline-<version>.md` (`@api`).
2. Update `packages/theme-core/composer.json` `version`, `JOO_CORE_VERSION` in `core.php` and add a `## x.y.z - date` section to `packages/theme-core/CHANGELOG.md`; `php tools/check-version.php` must pass (the release tool of P6.3 writes the three places together).
3. Run the gates G1 to G5: `make lint analyse test-unit test-integration test-tooling`, `npx vitest run`, `node tools/check-registry.mjs`, `node tools/validate-schemas.mjs`, `make i18n` (no untranslated string).
4. Backward compatibility: `tools/bc-check.sh <last-tag>`; any break of an `@api` symbol needs a MAJOR version or a documented exception in the ADR log.
5. Regenerate docs: `php tools/api-list.php > docs/api/baseline-<version>.md`, `make docs`.
6. Check the runtime bundle: `node tools/runtime-bundle.mjs list` (<= 1.5 MB, no tests, sources or maps) and, after a theme build, `node tools/runtime-bundle.mjs check <built core dir>`.
7. Tag locally `core@x.y.z` (no remote), build `starter` and `jooservices`, run G6 to G9 (`make demo`, `make test-e2e`, `make perf`, `make health`) and archive the evidence under `artifacts/reports/`.
8. Update Jira: evidence comment, transition issues, record the release in the epic.
