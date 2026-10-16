# Demo tour

`make demo THEME=jooservices` gives a site with 38 published posts, 3 pages, 6 companies, 72 comments and 12 images (deterministic: the same seed always creates the same content). Open the `WP_SITE_URL` from the worktree `.env` (defaults to `http://127.0.0.1:8082`); admin credentials are in `.env`. Allow about two minutes for the tour.

| Step | Where | What to show |
|---|---|---|
| 1 | Home | Dark scheme by default, header (brand, menu, search, language, theme toggle), the rail on the left, the featured slider (arrows, dots, pause), then the latest articles and pagination |
| 2 | Toggle | Switch to light and back; reload to show it is remembered and does not flash |
| 3 | Rail | Companies paginate in place, the category tree opens and closes, top topics and tags link to archives |
| 4 | Post: *The complete release checklist, in twenty steps* | Table of contents, reading progress, heading anchors, author box, related posts, comments on demand |
| 5 | Post: *Long listing: a queue worker* | Code block with language label and Copy button, horizontal scroll inside the box |
| 6 | Search | Press the Search button, type `docker`, Enter; then `zzzz` for the empty state |
| 7 | Terminal | Click the tab on the right (or press the backtick key): `ls`, `tree`, `cd ai`, `cat prompt-caching`, `grep -ri docker .`, `theme light`, `help` |
| 8 | 404 | Open `/ai/wordpress-mpc-v1`: the transcript, the closest post suggested first, the retry form |
| 9 | About | Full-width page with profile card and company history |
| 10 | Phone | Narrow the window below 900px: the rail folds behind the Sidebar button, the dock becomes a bar at the end of the page |
| 11 | Vietnamese | Four posts are tagged `vi`; the language switcher lists both languages when a translation exists |
| 12 | Hidden content | The draft, private and password-protected posts are not public (the protected post shows a password form; password `demo`) |

Reset the demo at any time with `make seed-reset THEME=jooservices`.
