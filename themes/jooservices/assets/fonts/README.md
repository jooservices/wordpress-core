# Fonts

Self-hosted, variable (`wght`) WOFF2 subsets. Both families are licensed under the SIL Open Font License 1.1 (see the `LICENSE-*.txt` files).

| File | Family | Source | Unicode range |
| --- | --- | --- | --- |
| `ibm-plex-sans-latin.woff2` | IBM Plex Sans 400-600 | `@fontsource-variable/ibm-plex-sans` 5.3.0 | Basic Latin and Latin-1 |
| `ibm-plex-sans-vietnamese.woff2` | IBM Plex Sans 400-600 | same | Vietnamese |
| `jetbrains-mono-latin.woff2` | JetBrains Mono 400-700 | `@fontsource-variable/jetbrains-mono` 5.3.0 | Basic Latin and Latin-1 |
| `jetbrains-mono-vietnamese.woff2` | JetBrains Mono 400-700 | same | Vietnamese |

Only `ibm-plex-sans-latin.woff2` is preloaded. Other scripts (Cyrillic, Greek) fall back to the system font.
`tools/check-fonts.mjs` fails the build when a file is over 60 KB or when the Vietnamese file lacks a required glyph.
