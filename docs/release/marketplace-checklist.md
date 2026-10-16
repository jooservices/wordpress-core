# ThemeForest release checklist

Primary channel: Envato Market / ThemeForest. Reviewed against the current
[Envato WordPress Theme Requirements hub](https://help.author.envato.com/hc/en-us/articles/360000472383-WordPress-Theme-Requirements-Start-here)
and its [theme plugin rules](https://help.author.envato.com/hc/en-us/articles/360000481223-WordPress-Theme-Requirements-Part-3-Theme-Plugins).
Recheck live rules on the day of submission. This is a dry-run checklist, not
an approval or a claim that the item has been submitted.

Other live references used for this review:

- [General requirements](https://help.author.envato.com/hc/en-us/articles/360000481263-WordPress-Theme-Requirements-Part-1-General)
- [Theme feature requirements](https://help.author.envato.com/hc/en-us/articles/360000480723-WordPress-Theme-Requirements-Part-2-Features)
- [Coding requirements](https://help.author.envato.com/hc/en-us/articles/360000479946-WordPress-Theme-Requirements-Part-4-Coding)
- [Security requirements](https://help.author.envato.com/hc/en-us/articles/360000481243-WordPress-Theme-Requirements-Part-5-Theme-Security)
- [Gutenberg requirements](https://help.author.envato.com/hc/en-us/articles/360020255992-WordPress-Theme-Requirements-Part-6-Gutenberg)
- [Theme item preparation](https://help.author.envato.com/hc/en-us/articles/360000470826-Themes-Item-Preparation-Technical-Requirements)
- [Presentation image requirements](https://help.author.envato.com/hc/en-us/articles/360000424863-Item-Presentation-Requirements)
- [Item metadata requirements](https://help.author.envato.com/hc/en-us/articles/360000471066-Item-Information-and-Metadata-Requirements)
- [Allowed assets and licenses](https://help.author.envato.com/hc/en-us/articles/360000424643-What-Assets-Can-I-Use-In-My-Items)
- [Support best practices](https://help.author.envato.com/hc/en-us/articles/360000471703-Item-Support-Best-Practices)

Status key: **Ready** = evidence exists in this checkout; **Hold** = a required
release decision, asset, or external review is still missing.

## Code and platform requirements

| Status | Requirement | Evidence |
| --- | --- | --- |
| Ready | Product slugs match their folders and avoid `wp-`/`wordpress` slug prefixes. | `themes/jooservices/style.css` (`Text Domain: jooservices`), plugin slug `jooservices-resume`; proposed listing title in [`marketplace-assets.md`](marketplace-assets.md). |
| Hold | Theme/plugin names pass owner/legal trademark and marketplace naming review. | The title draft is descriptive and does not imply WordPress affiliation. A limited public web search surfaced the existing JOOservices brand; no authoritative trademark register clearance was performed. |
| Ready | No obfuscated or hidden executable code. | Product source is readable PHP/JS/CSS; generated theme archive is inspected by the repository packager. Recheck the final upload ZIP. |
| Ready | Validate/sanitize input, escape output, and protect privileged writes. | Theme and plugin source; `make lint`, PHPStan, unit and integration suites. Record final run IDs in the development report. |
| Ready | Text domains and translation files match product slugs; no runtime translation service is required. | `themes/jooservices/languages/`, plugin `languages/`, `tools/check-headers.php`. |
| Hold | No analytics, tracking pixels, third-party fonts, or third-party calls by default. | Source review found no tracking/client analytics code; color preference uses local `localStorage` key `joo-theme`. `Diagnostics` loopback probe runs only from an explicit admin/CLI self-test and calls the site's own URL. Google Fonts in the excluded `design/reference-demo.html` is not runtime code. Complete the privacy E2E and inspect the final archive/network panel before release. |
| Ready | No unsupported feature claim for WooCommerce. | Owner decision: WooCommerce is out of 1.0; listing copy does not advertise it. |
| Hold | Current Envato Theme Check plugin run has no required errors/notices. | Local/CI WordPress.org Theme Check is automated by `make test-marketplace` and the `marketplace` CI job. Envato requires its own Theme Check plugin as a separate pre-submission review; retain its report with the ticket. |
| Ready | JOOservices Resume is included in the main theme download. | `make package THEME=jooservices` builds `jooservices/plugins/jooservices-resume.zip` inside the theme archive; version comes from the plugin header. |
| Hold | JOOservices Resume uses an Envato-allowed install flow. | ThemeForest requires ThemeForest-generated TGM Plugin Activation for a bundled plugin. The generator output is not available in this branch; add it with force activation/deactivation disabled and verify the exact bundled version before submission. |
| Hold | No auto-install/activation of the companion plugin without user choice. | Verify the generated TGM configuration sets `force_activation` and `force_deactivation` false and leaves installation to the user. TGM source/configuration is not yet available in this branch. |
| Ready | Browser targets match owner decision and are documented. | [`browser-compatibility.md`](../../themes/jooservices/docs/browser-compatibility.md) records Safari 16.4+, desktop Chrome/Edge latest and previous, Firefox ESR/latest, iOS Safari 16.4+, and Android Chrome targets. |
| Hold | Browser matrix has release evidence for every target. | Current Playwright projects cover Chromium and optionally Firefox/WebKit; they do not replace iOS Safari or Android Chrome device/browser runs. Attach versioned evidence before listing. |

## Licensing and content

| Status | Requirement | Evidence |
| --- | --- | --- |
| Ready | Product license files and inventory are included. | Root [`LICENSES.md`](../../LICENSES.md), `themes/jooservices/LICENSE` + `LICENSES.md`, `plugins/jooservices-resume/LICENSE` + `LICENSES.md`. Verify each generated ZIP contains its license files. |
| Ready | Font licenses and credits are preserved with locally hosted font files. | `themes/jooservices/assets/fonts/README.md`, `LICENSE-IBM-Plex-Sans.txt`, `LICENSE-JetBrains-Mono.txt`; font files are under `assets/fonts/`. |
| Ready | Credits page exists and documents provenance. | [`credits.md`](credits.md). |
| Ready | Source license and third-party asset licenses are documented. | Owner decision: theme and plugin source use GPL-2.0-or-later. The GPL text ships with each product; IBM Plex Sans and JetBrains Mono retain their SIL Open Font License 1.1 files. Recheck Envato's [licensing options](https://help.author.envato.com/hc/en-us/articles/360000534626-Theme-Plugin-Licensing-Options) at submission and include the current buyer-facing item terms. |
| Hold | All demo text, photos, logos, and preview materials are cleared. | Existing `screenshot.png` contains personal/employer details and is not cleared. Replace with an owner-reviewed showcase capture. Current product copy is original; review seeded demo data and uploaded media before publishing. |

## Product listing and support

| Status | Requirement | Evidence |
| --- | --- | --- |
| Ready | English installation, configuration, and usage documentation ships. | Theme `README.md`, Theme `readme.txt`, `docs/` pages, plugin `readme.txt`; verify instructions against the final package. |
| Ready | Listing title, description, tags, feature points, changelog, demo URL criteria, preview plan, and video script are drafted. | [`marketplace-assets.md`](marketplace-assets.md); see Envato's [metadata requirements](https://help.author.envato.com/hc/en-us/articles/360000471066-Item-Information-and-Metadata-Requirements). |
| Hold | Public demo URL is stable, sanitized, and available to reviewers. | Owner must supply/approve the public URL; local 8080 demo is not a release URL. Verify anonymous access, no login, and no personal data before submission. |
| Hold | Support offer and response-time commitment are approved. | Draft only in `marketplace-assets.md`; do not publish until owner confirms coverage period, support channel, and service level. If support is offered, see [Envato support best practices](https://help.author.envato.com/hc/en-us/articles/360000471703-Item-Support-Best-Practices). |
| Hold | Owner reviews final screenshot, preview images, listing copy, demo, and package. | Capture review sign-off in JOOS-100 before submission. |

## Privacy and data handling

| Status | Requirement | Evidence |
| --- | --- | --- |
| Hold | No theme cookies, analytics, tracking pixels, or third-party resource loads are present in the normal theme runtime. | Source inspection found color scheme preference only in `localStorage` (`joo-theme`). User-selected embeds and external links are initiated by the site publisher/visitor. The new privacy E2E and clean-browser network check still need a passing run. |
| Ready | Buyer-facing privacy text explains companion plugin data. | Draft snippet in [`marketplace-assets.md`](marketplace-assets.md): data entered into WordPress remains under the site operator's control; profile fields may be displayed publicly; company type is non-publicly-queryable; uninstall preserves data unless the site operator opts in to deletion. |
| Hold | Site privacy policy and REST exposure are reviewed for the final plugin version. | `JOOservices Resume` registers author profile metadata and company metadata for WordPress REST; administrator must review which values are public, obtain required consent, and avoid storing sensitive data in display fields. Verify endpoint permissions with final integration checks. |

## Pre-submission gate

1. Run `make lint analyse test-unit test-integration test-marketplace` and the
   applicable E2E suite; attach the report paths to JOOS-100.
2. Run the current Envato Theme Check plugin against the packaged theme and
   resolve every required item.
3. Build theme and plugin archives; inspect their contents, license notices,
   font files, bundled plugin, and version metadata.
4. Replace the uncleared screenshot; review all images/text and verify the
   public demo is anonymous, responsive, and free of personal information.
5. Obtain owner approval of title, copy, licenses, support policy, privacy
   wording, final assets, and submission package. Record the decision in Jira.
6. Submit only after all Hold rows have evidence. Track reviewer feedback as
   separate bugs and do not widen this ticket for unrelated product work.

## Secondary channel: WordPress.org (optional)

This ticket targets ThemeForest. If the product is later prepared for
WordPress.org, run the current official Theme Check and satisfy the Theme
Review Handbook separately. The existing slug `jooservices` avoids the
reserved `wp-` prefix and `wordpress` substring. Plugin Check is not a
replacement for a WordPress.org review.
