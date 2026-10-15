# Testing

| Level | Command | Where it runs | Notes |
|---|---|---|---|
| PHP unit | `make test-unit` | host PHP, PHPUnit 9.6 + Brain Monkey | no WordPress; fast |
| JS unit | `make test-unit` (Vitest, jsdom) | host Node | helpers in `packages/test-kit/js/dom.js` |
| Integration | `make test-integration` | Docker `wordpress` container, wp-phpunit on schema `wp_tests` | `WP_VERSION=6.6` stack for the minimum version |
| E2E | `make test-e2e THEME=jooservices` | Playwright against the demo (3 viewports: phone 390, tablet 820, desktop 1440) | axe accessibility scan included |
| Tooling self-tests | `make test-tooling` | host | proves every linter fails on a violation |

PHPUnit 9.6 is intentional: the WordPress test suite does not support PHPUnit 10+.

## Base classes and helpers
- `JOOservices\TestKit\UnitTestCase`: Brain Monkey + Mockery, common escape/i18n stubs. Brain Monkey records hooks, it does not run callbacks: assert with `Filters\expectApplied()` / `Actions\expectAdded()`; real hook execution is tested in integration tests.
- `JOOservices\TestKit\IntegrationTestCase` (extends `WP_UnitTestCase`): `capture('wp_head')`, `request($url)`, `assertHeadContains()`.
- `packages/test-kit/e2e/helpers.ts`: `openView`, `expectStatus`, `collectConsoleErrors`, `runAxe`, `noHorizontalScroll`, `tapTargets`, `setScheme`.
- `tests/e2e/pages/*`: page objects built on roles and landmarks, never theme-specific classes.

## Reports
`artifacts/reports/<suite>/` (git-ignored): `unit/` (Clover, HTML, summary), `vitest/`, `integration/junit-<wp-version>.xml`, `e2e/` (JUnit + HTML). Coverage gates: `tools/coverage-gate.sh` (core 85%, plugin 80%, themes 70%) and Vitest thresholds in `vitest.config.js`.

## Integration test library
`docker/scripts/phpunit-integration.sh` picks the wp-phpunit release that matches the running WordPress series (the repository ships the latest; for 6.6 it installs `wp-phpunit/wp-phpunit:6.6.*` into `/tmp` inside the container, network needed).

## Module contract and factories (C16.2)
`JOOservices\TestKit\ModuleContractTest` (extend it, implement `manifest()` and `modules()`) runs every module through `ModuleContract`: valid unique id, extends `AbstractModule` (so `boot()` is idempotent), resolvable dependencies without cycles, no hooks or options written in `register()`, every `settings->get('a.b')` literal exists in `settings.schema.json`, every `joo/module/event` string exists in the naming registry, the rest of the system boots with the module disabled, and boot time/memory stay inside a budget (25 ms and 1 MB per module). `ModulesContractTest` in core applies it to all built-in modules; a deliberately bad fixture (`BadRegisterModule`) proves the checks fire.

Factories in `packages/test-kit/src/Factories`:

```php
$cats = TermFactory::tree( array( 'Research' => array( 'Homelab' ), 'AI' ) );
$post = PostFactory::create( array( 'title' => 'Hi' ), array( 'categories' => array( $cats['Homelab'] ), 'featured' => 1, 'lang' => 'en', 'image' => array( 'width' => 1200, 'height' => 630 ) ) );
$vi   = PostFactory::translationOf( $post, 'vi' );
$me   = UserFactory::owner( array( 'display_name' => 'Viet Vu' ), array( 'joo_title' => 'CTO' ) );
$co   = CompanyFactory::create( array( 'name' => 'ACME', 'pinned' => true, 'from' => '2022-03' ) );
$img  = AttachmentFactory::image( 800, 600 );
```

## Demo, health, matrix and performance (P8, P5)
| Command | What it does |
|---|---|
| `make demo THEME=x` | builds assets, starts Docker, installs WordPress and the theme, seeds the demo, runs the health check |
| `make seed THEME=x` / `make seed-reset THEME=x` | imports the deterministic demo (`tools/wxr-generator`); `_joo_demo` marks demo content, so a reset removes only that |
| `make health` | `tests/matrix/urls.json`: status, content type and markers of each view (draft/private/protected, sitemap, robots, feeds, REST) |
| `make test-matrix THEME=x ARGS="--only yoast,cf7"` | installs each Tier-1 plugin from wordpress.org, runs the health table plus `tests/e2e/specs/matrix.spec.ts` (one title, one canonical, no console errors, CLS), diffs the PHP log, restores the database snapshot. Licensed plugins without a zip in `tests/matrix/vendor/` are listed as SKIPPED with the reason |
| `make perf THEME=x` | Lighthouse (mobile) per page of `themes/<slug>/budgets.json`: category scores, CLS, TBT, LCP and bytes of CSS, JS and fonts |
| `make package THEME=x` | builds `artifacts/package/<slug>-<version>.zip` (see the theme README) |

Theme-specific Playwright specs live in `themes/<slug>/tests/e2e` and run when `E2E_THEME=<slug>` (set by `make test-e2e THEME=x`).

### Inverted assertions
Pages that must not be indexed (search, 404) are expected to FAIL Lighthouse's `is-crawlable` audit: a pass would mean `noindex` is missing. `tools/perf.mjs` asserts this for pages flagged `noindex` in `budgets.json`.

### Writing a theme spec
Select by role, landmark or `joo-` class of core blocks, never by a theme's private markup. Wait for lazy fragments with `joo-lazy[name="..."]` and `scrollIntoViewIfNeeded()`. On widths below 900px the rail is behind the Sidebar button: open it first.
