# Terminal

A read-only Ubuntu-like shell docked at the side of the page. Nothing typed is sent anywhere.

## Data
`Terminal\FsBuilder` builds the tree from published, non-protected content; `GET /joo/v1/terminal/fs?lang=` serves it (cached with tags, HTTP-cacheable). Categories are directories (slug chain of the hierarchy), posts are `<slug>.md` in their primary category (links in the other categories; same slug in one directory becomes `<slug>-<id>.md`), `/tags/<slug>/` hold links, `/pages`, `/authors`, `/README.md` and a hidden `/.config`. Titles and excerpts are plain text; `terminal.max_posts` (default 500, newest first) and a 400 KB cap (`truncated: true`) bound the size.

## Engine (`resources/js/terminal/`)
`engine.js` is the pure shell core: `fs.js` (path resolution with `.`, `..`, `~`, link loop guard), `shell.js` (tokenizer, history, completion, did-you-mean, unsupported operators) and the command registration API. It has no DOM, network or `location` access (enforced by ESLint and a test). Its standalone production bundle is 4,599 B gzip against the 5,120 B (5 KiB) engine budget. Built-in command handlers and the browser UI are excluded from this engine measurement. Results are structured data: `{blocks, exitCode, navigate?, effects?}`.

Built-in commands: `help ls ll cd pwd cat head tree find grep open xdg-open go curl theme lang whoami hostname id uname date echo history clear exit logout man which neofetch ping sudo` and the write commands (`rm rmdir mv cp touch mkdir chmod chown ln tee vim vi nano`) which answer `Read-only file system`. `open` navigates to the URL of the node; an unknown path goes to the real 404 URL with the typed path; `curl` is simulated.

## UI
`<joo-terminal>` (dock view module, 1,250 B gzip) only toggles the panel; the first open loads the complete terminal bundle (8,421 B gzip; 9,216 B / 9 KiB budget per ADR 0012) and the FS JSON once. The full bundle includes the engine, built-in commands and UI. `ui.js` builds the DOM with `createElement`/`textContent` (output region `role="log"`), handles Tab, history, Ctrl+L, Ctrl+C, Esc and the backtick toggle, and applies effects: `navigate` only for same-origin http(s) URLs, `theme` through the `joo:theme` event (the theme toggle listens), `lang` through the `hreflang` alternate of the page. The dock is a right sidebar from 901 px and a bottom sheet below; it is hidden on 404 pages unless `terminal.hide_on_404` is off. Themes add commands through `joo/terminal/commands` (same-origin module URLs exporting an array of commands).

## 404 view
Block `joo/not-found`: real 404 page (status and robots from the SEO module) with a transcript, search form and three server-side suggestions; the path is echoed escaped. `<joo-404>` lets the visitor check another path against the embedded post index without a network request.
