# Core spec in one page

Core = engine; theme = presentation. A theme boots core with `Kernel::boot()` and declares behaviour in `joo-theme.json`. Modules: assets, perf, lazy, seo, compat, content, blocks, i18n, terminal, admin. Settings: `joo_{slug}_settings` with precedence filters > saved > manifest > core default. Everything public is tagged `@api`; names are registered in `naming-registry.json`. Failures never break the front-end. Distribution: core is copied into each theme zip (no runtime dependencies). Full text: `core-spec-v0.1.md`.
