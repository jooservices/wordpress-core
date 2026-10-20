# Performance budgets

The marked JSON block is the single source for enforced code-budget values. `make perf` and its underlying checks read these limits directly; the tables below record the measured results.

<!-- enforced-code-budgets:start -->
```json
{
  "boot": {
    "emptyModuleSetMedianMilliseconds": 3,
    "emptyModuleSetPeakBytes": 1572864
  },
  "runtimeBundle": {
    "maxBytes": 1572864
  },
  "javascript": {
    "runtime": 3072,
    "blocks/featured-slider/view": 5120,
    "blocks/toc/view": 3072,
    "blocks/companies/view": 2560,
    "blocks/category-tree/view": 2048,
    "terminal-engine": 5120,
    "terminal": 9216
  },
  "criticalCss": {
    "maxGzipBytes": 14336,
    "themes": ["starter", "jooservices"],
    "templates": ["404", "archive", "home", "page", "search", "single"]
  }
}
```
<!-- enforced-code-budgets:end -->

The runtime copy limit applies to the allow-listed core files. `make perf` checks it with `tools/runtime-bundle.mjs`.

## Core boot

Measured on the WSL workstation on 2026-10-21 with WordPress 7.1.3 and PHP 8.3.30 in the shared PHP runtime. The empty-module result is within the enforced limits; the default-set result is informational.

| Module set | Modules booted | Median boot time (30 samples) | Peak incremental heap (maximum of 30) | Result |
| --- | ---: | ---: | ---: | --- |
| Empty | 0 | 0.255 ms | 498,208 B (486.5 KiB) | Within budget |
| Default JOOservices | 10 | 0.586 ms | 2,611,312 B (2,550.1 KiB) | Informational |

Run `make boot-cost` to repeat the measurement against the ticket worktree's WordPress site. It invokes WP-CLI with plugins and themes skipped, loads `packages/theme-core/core.php`, and boots `themes/jooservices/joo-theme.json`. The `joo/core/modules` filter is set to an empty list for the first scenario and to `Kernel::BUILTIN_MODULES` for the second, so both sets use the same manifest and WordPress database.

Each scenario performs 30 consecutive `Kernel::boot()` calls in the same PHP process. Before each sample the script restores the WordPress hook snapshot, resets the kernel and notices, and collects cycles. It measures elapsed time with `hrtime()` around `Kernel::boot()`; the table reports the median. For memory, it records `memory_get_usage(false)` immediately before the boot, resets PHP's peak counter, then subtracts that baseline from `memory_get_peak_usage(false)`. The table reports the largest of those 30 per-boot heap peaks. WordPress bootstrap is excluded; the core manifest, container, and selected modules are included. Later samples reuse PHP's loaded classes, so this is a warm kernel-boot measurement. Memory values use binary units (1 KiB = 1,024 B).

## Lighthouse theme budgets

`themes/starter/budgets.json` and `themes/jooservices/budgets.json` define the same Lighthouse limits for home, single, category, search, and about pages. Both themes must reach performance 90, accessibility 95, best practices 90, and SEO 95; CLS is capped at 0.02, TBT at 200 ms, LCP at 2,700 ms, CSS at 30 KiB, JavaScript at 60 KiB, fonts at 100 KiB, and HTML at 60 KiB. The font allowance covers the two Latin typefaces plus the Vietnamese IBM Plex subset, measured at 100,140 bytes on the jooservices pages. Search pages are marked noindex and must fail the crawlability audit.

Run `make perf THEME=starter` or `make perf THEME=jooservices`. The shared Node runtime runs Lighthouse and the browser runs on the Workspaces Dev VM.

## JavaScript bundles

Measured from the current minified production assets built with:

```sh
scripts/docker/runtime node node tools/build-assets.mjs --root packages/theme-core
```

The build uses esbuild 0.28.2, target ES2022, minification, and Node 24.21.0's `gzipSync()` default settings. Gzip values are the byte length of the compressed emitted JavaScript file, without HTTP headers. The build manifest records these values, and `tools/build-assets.test.mjs` verifies the manifest calculation against the emitted files. The enforced limits use binary units (1 KiB = 1,024 bytes) and are written as exact byte limits in the JSON block above.

| Bundle | Current gzip size | Result |
| --- | ---: | --- |
| Frontend runtime (`runtime`) | 1,068 B | Within budget |
| Featured slider (`blocks/featured-slider/view`) | 1,176 B | Within budget |
| Table of contents (`blocks/toc/view`) | 600 B | Within budget |
| Companies (`blocks/companies/view`) | 1,594 B | Within budget |
| Category tree (`blocks/category-tree/view`) | 563 B | Within budget |
| Pure terminal engine (`terminal-engine`) | 2,326 B | Within budget |
| Complete terminal bundle (`terminal`) | 8,536 B | Within budget |

The standalone terminal engine bundle includes filesystem and shell behavior plus command registration, but excludes the built-in command handlers and browser UI. The complete terminal bundle includes the engine, built-in commands, and UI. ADR 0012 sets the full-bundle limit at 9 KiB; this table records the current build, and the spec and module documentation describe both scopes.

## Critical CSS

Captured from the starter and jooservices demo themes with `make critical THEME=starter` and `make critical THEME=jooservices`. The generator merges the three viewport captures for each template and minifies with Lightning CSS. The sizes below are measured from the emitted CSS files after local asset URL localization, using Node's `gzipSync()` default settings. Values exclude HTTP headers.

| Theme | Template | Current gzip size | Result |
| --- | --- | ---: | --- |
| starter | 404 | 2,697 B | Within budget |
| starter | archive | 3,258 B | Within budget |
| starter | home | 3,478 B | Within budget |
| starter | page | 3,011 B | Within budget |
| starter | search | 3,303 B | Within budget |
| starter | single | 3,168 B | Within budget |
| jooservices | 404 | 5,069 B | Within budget |
| jooservices | archive | 6,969 B | Within budget |
| jooservices | home | 7,219 B | Within budget |
| jooservices | page | 6,539 B | Within budget |
| jooservices | search | 7,080 B | Within budget |
| jooservices | single | 6,718 B | Within budget |
