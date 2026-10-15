# Coding standards

PHP follows WordPress-Extra (security and correctness sniffs) with documented relaxations for modern PSR-4 code (see `phpcs.xml.dist`): class-per-file naming, camelCase methods and variables in OO code, short array syntax, short ternary. PHP files use tabs (WordPress standard); JS/CSS/JSON/YAML/Markdown use two spaces.

Not enabled: `WordPress-Docs` (docblock completeness). Docblocks are mandatory for every `@api` symbol and every `joo/...` hook (checked by the docs generator, P7.3).

Commands: `make lint` (PHPCS, header check, ESLint, Stylelint, Prettier), `make analyse` (PHPStan core level 8, themes level 6, deptrac).
