# Static analysis

- Core: PHPStan level 8, no baseline allowed (`phpstan-core.neon`).
- Themes, plugin, test-kit, tools: level 6 (`phpstan-themes.neon`). A baseline file is allowed only with a Jira key in a comment above each entry.
- `treatPhpDocTypesAsCertain: false` so docblock types never hide real type errors.
- WordPress symbols come from `szepeviktor/phpstan-wordpress`; WP-CLI from `php-stubs/wp-cli-stubs`.
- JavaScript is checked with `tsc --checkJs` (`tsconfig.json`), ESLint and Stylelint rules listed in their configs.
- Tools run with `php -d error_reporting=24575` (silences deprecations of third-party dev tools on PHP 8.5; production runtime is PHP 8.3).
- PHPStan sets `phpVersion: 80300` and PHPCS/PHPCompatibility sets `testVersion` to `8.3-`, so the PHP 8.5 developer runtime cannot silently expand the source language/API target past PHP 8.3.
