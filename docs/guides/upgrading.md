# Upgrading theme core

Use this guide when you move a theme from one theme-core release to another. The theme's `joo-theme.json` declares the compatible core versions; the release changelog records changes theme authors may need to make.

## Core version range

The `core` field in `joo-theme.json` is a [Semantic Versioning](https://semver.org/) range. Core checks this range when the theme boots. It describes which core versions the theme supports; it does not install or update core for you. Core is bundled with the theme distribution.

For example, the current manifest value `"core": "^0.1"` allows core versions from `0.1.0` up to, but not including, `0.2.0`. It accepts compatible `0.1.x` patch releases and excludes the next minor line. When you have checked your theme against a new core minor release, update the range to include that line and release the theme with the change.

## Stable surface

Treat symbols tagged `@api` in `packages/theme-core/src` as public and stable within the core major version. Everything marked `@internal`, or without the `@api` tag, is an implementation detail and may change in any release.

Documented `joo/...` hooks are also part of the stable API. A hook is documented when its source docblock includes `@since` and `@api`; see the generated [hooks reference](../reference/hooks.md) for hook names. Check the hook's source docblock for its arguments and callback contract.

## Deprecations

Core announces a deprecated public symbol or hook in the release changelog, names its replacement when one exists, and keeps the old API available for at least one minor release after the deprecation is announced. Do not remove a stable API during that period. Removing a stable API is a breaking change and requires a major release.

When a deprecated hook is used, call `Hooks::deprecated()` with the hook name, the version that deprecated it, and its replacement (if any):

```php
Hooks::deprecated( 'joo/old/event', '0.2.0', 'joo/new/event' );
```

The helper reports WordPress's deprecated-hook notice in debug mode when the old hook has listeners. Record the deprecation and migration path in the release's changelog upgrade notes as well.

## Check backward compatibility

Before releasing core, compare the candidate with the last core release tag. Install the checker dependency once, then run it from the repository root:

```bash
composer install --working-dir=tools/bc --no-interaction --prefer-dist
tools/bc-check.sh <last-core-tag>
```

The command compares the `packages/theme-core` subtree at the given ref with `HEAD`. To compare with another candidate ref instead, pass `--to=<ref>` as the second argument. It prints the Roave report and saves a Markdown copy under `artifacts/reports/bc/`. Review that report before release; a breaking change to a stable API needs a major version bump.

## Changelog upgrade notes template

Add a newest-first entry to the theme's `CHANGELOG.md` for each theme release. Include this section when the release changes core compatibility or requires a theme author's action:

```markdown
## x.y.z - YYYY-MM-DD

### Upgrade notes
- Core range: `<old range>` → `<new range>` (or `No change`).
- Required action: `<theme author action>` (or `None`).
- Deprecated: `<symbol or hook>`, deprecated in `<version>`; use `<replacement>`.
- Removed: `<symbol or hook>` and the supported migration path, if applicable.

### Changed
- <Other user-facing changes>
```

Delete unused bullets and keep the required action explicit. If there is no upgrade work, say `None` so theme authors can tell that no code or configuration change is needed.
