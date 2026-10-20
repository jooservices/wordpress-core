# Static analysis

- Core: PHPStan level 8, no baseline allowed (`phpstan-core.neon`), with `phpVersion: 80100` to check compatibility with PHP 8.1.
- Themes, plugin, test-kit, tools: level 6 (`phpstan-themes.neon`). A baseline file is allowed only with a Jira key in a comment above each entry.
- `treatPhpDocTypesAsCertain: false` so docblock types never hide real type errors.
- WordPress symbols come from `szepeviktor/phpstan-wordpress`; WP-CLI from `php-stubs/wp-cli-stubs`.
- JavaScript is checked with `tsc --checkJs` (`tsconfig.json`), ESLint and Stylelint rules listed in their configs.
- Tools run with `php -d error_reporting=24575` (silences deprecations of third-party dev tools on PHP 8.5; production runtime is PHP 8.3).
- PHPStan targets PHP 8.1 for core (`phpVersion: 80100`) and PHP 8.3 for other analyzed code (`phpVersion: 80300`); PHPCS/PHPCompatibility sets `testVersion` to `8.3-`. These checks keep the PHP 8.5 developer runtime from silently expanding the source language/API target.
