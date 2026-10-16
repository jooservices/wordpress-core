# License inventory

The owner decided that the theme and plugin source use GNU
GPL-2.0-or-later; see [`LICENSE`](LICENSE) for the GPL version 2 text. The
product metadata and packaged license files follow that decision. Third-party
assets keep their own licenses, including the local fonts under SIL Open Font
License 1.1. ThemeForest's current item-license terms apply to the sale and
must be checked again before submission. Do not copy Envato preview assets
into the product archive unless their licenses expressly allow
redistribution.

| Material | License / provenance | Evidence and packaging |
| --- | --- | --- |
| First-party theme and plugin code | GNU GPL-2.0-or-later, per the owner decision for the 2026-10-16 release baseline | Root `LICENSE`; product copies at `themes/jooservices/LICENSE` and `plugins/jooservices-resume/LICENSE`; source headers and package metadata use the same license. |
| IBM Plex Sans variable font files | SIL Open Font License 1.1 | `themes/jooservices/assets/fonts/LICENSE-IBM-Plex-Sans.txt`; local WOFF2 files ship in the theme archive. |
| JetBrains Mono variable font files | SIL Open Font License 1.1 | `themes/jooservices/assets/fonts/LICENSE-JetBrains-Mono.txt`; local WOFF2 files ship in the theme archive. |
| Icons | No third-party icon font or icon package is bundled. | Inline symbols/SVG markup are first-party theme code; verify the final archive before submission. |
| Demo text, generated media, screenshot, and preview images | Tier B sold-demo content is licence-clean; first-party preview images are generated from that content. | Demo text and gradient images come from the deterministic WXR generator. The current screenshot still needs replacement and owner review; retain review evidence before marketplace submission. |
| Other runtime libraries | No additional third-party runtime library is bundled in the theme or Resume plugin. | Recheck generated archives and dependency manifests at each release. |

This inventory is a release control, not a substitute for an asset-by-asset
rights review. Keep source URL, author, license, required attribution, and
permission evidence for every third-party asset added later.
