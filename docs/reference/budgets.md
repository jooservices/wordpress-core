# Performance budgets

## JavaScript bundles

Measured from the current minified production assets built with:

```sh
scripts/docker/runtime node node tools/build-assets.mjs --root packages/theme-core
```

The build uses esbuild 0.28.2, target ES2022, minification, and Node 24.21.0's `gzipSync()` default settings. Gzip values are the byte length of the compressed emitted JavaScript file, without HTTP headers. The build manifest records these values, and `tools/build-assets.test.mjs` verifies the manifest calculation against the emitted files. Budgets use binary units (1 KiB = 1,024 bytes) and are written as exact byte limits.

| Bundle | Current gzip size | Budget | Result |
| --- | ---: | ---: | --- |
| Frontend runtime (`runtime`) | 1,068 B | 3,072 B (3 KiB) | Within budget |
| Featured slider (`blocks/featured-slider/view`) | 1,176 B | 5,120 B (5 KiB) | Within budget |
| Table of contents (`blocks/toc/view`) | 600 B | 3,072 B (3 KiB) | Within budget |
| Companies (`blocks/companies/view`) | 1,594 B | 2,560 B (2.5 KiB) | Within budget |
| Category tree (`blocks/category-tree/view`) | 563 B | 2,048 B (2 KiB) | Within budget |
| Pure terminal engine (`terminal-engine`) | 4,599 B | 5,120 B (5 KiB) | Within budget |
| Complete terminal bundle (`terminal`) | 8,991 B | 9,216 B (9 KiB) | Within budget |

The standalone terminal engine bundle includes filesystem and shell behavior plus command registration, but excludes the built-in command handlers and browser UI. The complete terminal bundle includes the engine, built-in commands, and UI. ADR 0012 sets the full-bundle limit from the measured current output; the spec and module documentation describe both scopes.

## Critical CSS

Captured from the starter and jooservices demo themes with `make critical THEME=starter` and `make critical THEME=jooservices`. The generator merges the three viewport captures for each template and minifies with Lightning CSS. The sizes below are measured from the emitted CSS files after local asset URL localization, using Node's `gzipSync()` default settings. Values exclude HTTP headers; the 14 KiB budget is 14,336 bytes.

| Theme | Template | Current gzip size | Budget | Result |
| --- | --- | ---: | ---: | --- |
| starter | 404 | 2,697 B | 14,336 B | Within budget |
| starter | archive | 3,258 B | 14,336 B | Within budget |
| starter | home | 3,478 B | 14,336 B | Within budget |
| starter | page | 3,011 B | 14,336 B | Within budget |
| starter | search | 3,303 B | 14,336 B | Within budget |
| starter | single | 3,168 B | 14,336 B | Within budget |
| jooservices | 404 | 5,069 B | 14,336 B | Within budget |
| jooservices | archive | 6,969 B | 14,336 B | Within budget |
| jooservices | home | 7,219 B | 14,336 B | Within budget |
| jooservices | page | 6,539 B | 14,336 B | Within budget |
| jooservices | search | 7,080 B | 14,336 B | Within budget |
| jooservices | single | 6,718 B | 14,336 B | Within budget |
