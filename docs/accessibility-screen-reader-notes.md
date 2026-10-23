# Screen reader notes: JOOservices theme (JOOWP-210, AC5 and AC6)

## Status

**The manual screen reader pass has NOT been performed yet.** It needs a person with macOS (VoiceOver and Safari), and optionally Windows (NVDA), running the theme. It cannot be automated and was not run by the automated agent that prepared this ticket. No findings below are recorded because none have been observed; nothing here is invented.

Until the tester fills in this document and logs Bugs, AC5 and AC6 of JOOWP-210 remain open.

## Setup to record when the pass runs

| Field | Value |
|---|---|
| Date | _to fill_ |
| Tester | _to fill_ |
| Theme version / commit | _to fill_ |
| VoiceOver version, macOS version, Safari version | _to fill_ |
| NVDA version, Windows version, browser | _to fill, or write "NVDA not available" and say why_ |

## Pages in scope

1. Home page (`/`)
2. An article (any single post)
3. The 404 page (`/definitely-missing/`)
4. The terminal (open the dock on the home page)

## Checks per page and per screen reader

For each page, with each screen reader, check and note the result. Mark a defect with a Bug key.

| Check | How | Home | Article | 404 | Terminal |
|---|---|---|---|---|---|
| Landmarks list | VO rotor *Landmarks* / NVDA `D` or Elements List | | | | |
| Headings list | VO rotor *Headings* / NVDA `H` or Elements List: one `h1`, no skipped levels, meaningful text | | | | |
| Link list | VO rotor *Links* / NVDA Elements List: link text makes sense out of context (slider, cards, tags, pager) | | | | |
| Form labels | Search form, terminal input: each announces a label and role | | | | |
| Live regions: companies pagination | Home rail: go to the next page of companies and note whether the list change is announced (`ol.joo-companies__list` is `aria-live="polite"`) and not announced twice | n/a | n/a | n/a | n/a |
| Live regions: terminal output | Run `help`: the status region announces the command (`.joo-term__status`, polite) without reading the whole log (`.joo-term__log` is `aria-live="off"`) | n/a | n/a | n/a | |

Also note anything else found: slider (carousel and slide roles, pause control, slide announcements), focus order, focus return when the terminal closes.

## Findings

| # | Page | Screen reader | Description | Severity | Bug |
|---|---|---|---|---|---|
| | | | _none recorded yet_ | | |

Each defect found is logged as a Bug in Jira (project JOOWP) and listed here. Defects are not fixed within JOOWP-210.
