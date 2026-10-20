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
