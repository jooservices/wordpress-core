# Starter

The reference block theme on the JOOservices core, and the template of `make new-theme`. It is markup and tokens only: all behaviour (SEO, performance, lazy loading, blocks, terminal) comes from the bundled core. `functions.php` plus `inc/` stay under 50 lines (a test enforces it).

* `joo-theme.json`: manifest (modules, design, image sizes, SEO defaults, lazy and performance settings)
* `theme.json` and `styles/`: tokens (palette, typography, spacing) and the dark and light variations
* `templates/`, `parts/`, `patterns/`: block markup using only WordPress and `joo/*` blocks
* `make link-core THEME=starter` links the core into `core/`; the build copies the runtime files instead
