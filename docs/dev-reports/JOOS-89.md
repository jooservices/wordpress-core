# JOOS-89 — Resume front end (release 1.0)

## Changes
- `Frontend\Renderer` (one markup for shortcode, block and fragments), `Frontend\Surfaces` (shortcode `[joo_resume]`, block `joo/resume`, REST, structured data), `blocks/resume/block.json`, `assets/resume.css`.
- REST: `jooservices-resume/v1/resume` and `/resume/json-resume`; public projection only, `ETag`, `Cache-Control`, `304`.
- Structured data: `worksFor` and `hasOccupation` on the owner Person node through the theme graph filter and Yoast's `wpseo_schema_person`.
- Empty state, current-only, limit, order, one or many companies, missing logo, long text (`overflow-wrap`), print style (A4, URLs, no breaks), RTL via logical properties.
- Registry: block `joo/resume`, REST routes `/resume`, `/resume/json-resume`.

## Verification
| Check | Result |
|---|---|
| `make lint`, `make analyse`, `node tools/check-registry.mjs` | pass |
| `make test-unit` | PHPUnit 272 tests, Vitest 247 |
| Plugin integration (WordPress 7.1.3) | 39 tests / 359 assertions (new `FrontendTest`: semantic markup, escaping, private data never printed, display settings, order, limit, current only, shortcode equals block, REST validators and 304, JSON Resume, `worksFor` on the Person node, Yoast filter) |
| Playwright `resume-frontend.spec.ts` | 15 passed on a quiet host (phone, tablet, desktop): 30 companies and long text with no serious axe issue and no horizontal scroll, current-only, RTL, print (header and footer hidden, companies kept together), REST public and cacheable |
| Defects found by the E2E run and fixed | the theme rail card overflowed with very long company names (`overflow-wrap` in the companies block CSS); the theme print stylesheet did not hide the header, footer and progress bar |
| Regression `specs/admin` + `specs/shared` | 145 passed, 10 failed only with `Page crashed`, `Target crashed` or login timeouts while the Docker VM was out of memory (load average 100 to 160); repeated runs under load kept crashing, so the final clean confirmation is pending a bigger Docker VM |

## Not covered by this ticket
- The theme's existing `joo/companies` rail card and the About timeline keep using the theme-core content provider (old fields). Moving them to this renderer, or extending the core content provider with the new fields, is a follow-up (proposed backlog).
- Lighthouse and the Rich Results validator need a public URL (showcase site, JOOS-92/93); the structure is covered by integration tests.
