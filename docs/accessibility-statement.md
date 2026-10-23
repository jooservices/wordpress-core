# Accessibility statement: JOOservices theme

> **Draft for owner review.** Not yet published. The owner must confirm the wording, the known limitations and the contact section before this statement is shown to buyers.

## Conformance target

The JOOservices theme (`themes/jooservices`) targets **WCAG 2.2 level AA**. This is a target, not a claim of full conformance: conformance depends on the content a site owner adds and on the plugins they install, as well as on the theme.

## What is tested

- Automated Playwright tests under `themes/jooservices/tests/e2e` check:
  - **Reduced motion.** When the visitor prefers reduced motion, slider autoplay does not start, smooth scrolling and the 404 caret animation are off, and transitions are removed (`motion-zoom.spec.ts`).
  - **Pause control.** With normal motion, a pause control is present whenever slider autoplay is enabled.
  - **Reflow.** At a 320 px viewport (the equivalent of 400% zoom on a 1280 px screen) there is no horizontal scrolling and no loss of content or function.
  - **Text zoom.** With text-only zoom of 200% there is no loss of content or function.
- Automated axe checks and colour contrast checks for both colour schemes run as part of the project's end-to-end and design test suites.
- A manual screen reader pass is documented in [`docs/accessibility-screen-reader-notes.md`](accessibility-screen-reader-notes.md).

## Known limitations

- The manual screen reader pass (VoiceOver on macOS Safari; NVDA on Windows where available) has not been recorded yet, so screen reader behaviour is not yet verified by a person. See the notes document for status and for any defects found.
- Automated tests do not cover every assistive technology, browser or device combination.
- The featured slider and the terminal widget are interactive components; they follow the relevant ARIA patterns and are covered by keyboard tests, but they are the areas most likely to differ between screen readers.
- Content added by site owners (images, headings, link text, embedded media) and third-party plugins are outside the theme's control and may not meet WCAG 2.2 AA.
- Reduced-motion handling follows the visitor's operating system setting. The theme has no separate on-page motion switch.

## Feedback and contact

Accessibility problems can be reported through GitHub issues:
<https://github.com/jooservices/wordpress-core/issues>

The repository has no `SECURITY.md` or `SUPPORT.md`, so GitHub issues are the only channel. No other contact address and no response-time commitment is offered in this statement.

## Review

| Item | Status |
|---|---|
| Drafted | Ticket JOOWP-210 |
| Owner review | Pending |
