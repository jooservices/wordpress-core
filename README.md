# jooservices-wp-themes

Monorepo for JOOservices WordPress block themes, the shared theme core and a companion content plugin.

- `packages/theme-core`: reusable engine (SEO, performance, lazy loading, settings, compatibility, blocks, terminal)
- `plugins/jooservices-content`: companion plugin (companies, term/user meta, featured posts)
- `themes/starter`: reference theme (second consumer of core)
- `themes/jooservices`: theme #1 for jooservices.com
- `docker/`: local WordPress stack (loopback only)
- `tools/`, `tests/`, `docs/`

Quickstart and conventions: see `CONTRIBUTING.md` and `docs/`.
Run `make help` for all tasks.
