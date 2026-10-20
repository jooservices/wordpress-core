# Core block accessibility report

- Ticket: JOOWT-272
- Audit date: 2026-10-21
- Machine: WSL
- Consumer fixture: `jooservices` on WordPress 7.1.3

## Method

The browser audit ran on the seeded ticket worktree site through `make test-e2e`, with Playwright 1.64.1 using the Dev VM browser. The selected E2E spec scanned each rendered block separately with axe-core 4.13.0 using WCAG 2.0/2.1 A and AA plus WCAG 2.2 AA tags at 390×844, 820×1180 and 1440×900. The Jooservices sidebar was opened with its keyboard control at narrow widths so its blocks were visible to the audit.

For fragment-capable sidebar blocks, the test scrolls the `<joo-lazy>` wrapper when lazy loading is enabled and audits the inline block when it is disabled.

The keyboard walkthrough used Tab to reach every visible block control and checked for a visible focus indicator. It rejects transparent outlines and counts a box shadow only when its focused value differs from that control's unfocused value. It also activated the search dialog, theme toggle, slider arrows, companies pager, category tree toggle, TOC disclosure and TOC link with the keyboard. The reading progress block has no interactive controls.

## Findings

| ID | Block | Severity | Status | Finding and action |
| --- | --- | --- | --- | --- |
| A11Y-01 | Author box | Serious (`color-contrast`) | Fixed | Core author secondary text was dimmed with `opacity: 0.8`, reducing its contrast against the active theme surface. Removed the opacity rule from `resources/blocks/author-box/style.css`; the author box now passes axe at all three widths. |

No other axe issues were found. No follow-up Bugs are required from this audit.

## Block results

| Core block | Axe 390 px | Axe 820 px | Axe 1440 px | Keyboard walkthrough |
| --- | --- | --- | --- | --- |
| Breadcrumbs | Pass | Pass | Pass | Links reached with Tab; visible focus confirmed |
| Table of contents | Pass | Pass | Pass | Summary toggled with Space; section link activated with Enter |
| Reading progress | Pass | Pass | Pass | No interactive controls; no focus stop expected |
| Author box | Pass | Pass | Pass | Author links reached with Tab; visible focus confirmed |
| Related posts | Pass | Pass | Pass | Links reached with Tab; visible focus confirmed |
| Tag list | Pass | Pass | Pass | Links reached with Tab; visible focus confirmed |
| Profile card | Pass | Pass | Pass | Links reached with Tab; visible focus confirmed |
| Companies | Pass | Pass | Pass | Pager reached and activated with Enter; page status updated |
| Category tree | Pass | Pass | Pass | Branch toggle reached and activated with Space |
| Top topics | Pass | Pass | Pass | Links reached with Tab; visible focus confirmed |
| Slider | Pass | Pass | Pass | Track arrow key changed the current slide |
| Search trigger | Pass | Pass | Pass | Enter opened the dialog; Escape closed it and returned focus |
| Theme toggle | Pass | Pass | Pass | Space changed the active color scheme |
| Post meta | Pass | Pass | Pass | Links reached with Tab; visible focus confirmed |

## Verification

Command:

```sh
make test-e2e THEME=jooservices ARGS="tests/e2e/specs/shared/core-accessibility.spec.ts --reporter=list --output=/tmp/joowt-272-qa"
```

Result: 12 passed across phone, tablet and desktop projects. The list reporter and `/tmp` output override are needed because the default report directory under the worktree is not writable by the `www-data` E2E process.

The worktree database was seeded before the audit. Seeding populated the isolated `wp_JOOWT_272` database, then its optional snapshot export stopped because GNU `cp` does not support the macOS-specific `-c` flag; this did not prevent the browser audit.
