# Design reference

`reference-demo.html` is the approved behavioural and visual reference (the v2 demo). Open it in a browser; the
buttons at the top switch views, scheme, language, density and viewport.

Newer exploration files (v3 to v5, a different "systems journal" direction) exist in the Workspaces `artifacts/demos`
folder. They are **not** the reference for this theme.

`checklist.md` lists what must hold per view. `tokens.json` is the single source of the palette; `tests/design` fails
when `theme.json`, `styles/*.json` and `assets/src/css/components/tokens.css` drift apart.

## Deviations from the reference

| Area | Reference | Theme | Why |
| --- | --- | --- | --- |
| Light `pin` colour | `#a96a00` | `#9a6000` | 4.42:1 on white fails WCAG AA for the small "current company" label; `#9a6000` gives 5.19:1 (4.73:1 on the tinted card) |
| 404 status colour | `var(--err)` | fixed `rgb(255 107 107)` | The terminal window is dark in both schemes, so the light-scheme red (3.4:1) would be unreadable |
| Colour notation | hex | `rgb()` | stylelint bans hex in theme CSS so every colour goes through tokens |
| Dock | flex column inside the demo stage | sticky column of `.joo-frame`; core's fixed overlay is overridden | Content reflows instead of being covered |
| Search field | inline "Search articles… /" box | dialog opened by the core search-trigger block | Accessible dialog, same shortcut |
| Density toggle | "Compact list" button in the demo bar | not shipped | It controlled the demo only |
