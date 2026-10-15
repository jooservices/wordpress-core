# Design checklist

Tick every item against the running demo (`make demo THEME=jooservices`) at 390, 820 and 1440px, in both schemes.


## HOME

- [ ] HOME-01 Header shows brand, primary navigation, search trigger, language switcher and theme toggle
- [ ] HOME-02 Left rail shows profile, companies, categories, top topics and tags in that order
- [ ] HOME-03 Featured slider shows five pinned posts with image, pill, meta, title and excerpt
- [ ] HOME-04 Slider arrows, dots and pause control work with keyboard and touch
- [ ] HOME-05 Slider autoplay stops on hover, focus and when reduced motion is requested
- [ ] HOME-06 Latest articles list shows the thumbnail, meta, title, excerpt, Read link and tags
- [ ] HOME-07 Pagination links keep the 44px touch target
- [ ] HOME-08 Empty state message appears when there are no posts
- [ ] HOME-09 Featured posts are not repeated in the list when hide_featured_in_list is on
- [ ] HOME-10 Only the first slide image is eager and has fetchpriority high

## SINGLE

- [ ] SINGLE-01 Breadcrumb trail has Home, category and the post title
- [ ] SINGLE-02 Title is the only h1 and wraps without overflow for 120 character titles
- [ ] SINGLE-03 Byline shows author, category, date, updated date and reading time
- [ ] SINGLE-04 Cover image keeps 21:9 on desktop and 16:8 on phone
- [ ] SINGLE-05 Table of contents appears with three or more headings and links to anchors
- [ ] SINGLE-06 Prose width is 70ch and code blocks scroll horizontally inside their box
- [ ] SINGLE-07 Code blocks have a copy button that announces the result
- [ ] SINGLE-08 Block quote, table, list and image styles follow the tokens
- [ ] SINGLE-09 Previous and next links show their titles and stack on phone
- [ ] SINGLE-10 Author box shows avatar, name, title, bio and socials
- [ ] SINGLE-11 Related posts load lazily and show three cards
- [ ] SINGLE-12 Comments load on demand and nested replies are indented
- [ ] SINGLE-13 Reading progress bar fills while scrolling and is hidden from assistive technology as decoration
- [ ] SINGLE-14 Tags under the article link to their archives

## ARCHIVE

- [ ] ARCHIVE-01 Category banner shows eyebrow, title and description
- [ ] ARCHIVE-02 Tag banner uses the Topic eyebrow
- [ ] ARCHIVE-03 Breadcrumb ends with the archive name
- [ ] ARCHIVE-04 List uses the same card as the home page
- [ ] ARCHIVE-05 Pagination keeps the archive base URL
- [ ] ARCHIVE-06 Empty archive shows the empty message
- [ ] ARCHIVE-07 Author archive shows the profile card in the banner
- [ ] ARCHIVE-08 Date archive uses the Archive eyebrow

## SEARCH

- [ ] SEARCH-01 Search banner echoes the query
- [ ] SEARCH-02 Search form is prefilled and has a visible label for assistive technology
- [ ] SEARCH-03 Results use the card list
- [ ] SEARCH-04 No results shows suggestions text and keeps the form
- [ ] SEARCH-05 Search pages are noindex
- [ ] SEARCH-06 Blank search redirects to the home page

## ABOUT

- [ ] ABOUT-01 About uses the full width template without the rail
- [ ] ABOUT-02 Profile card is repeated at the top of the content
- [ ] ABOUT-03 Companies block lists the current company first and then the history
- [ ] ABOUT-04 Contact links are reachable by keyboard

## 404

- [ ] 404-01 Response status is 404 with noindex and no-store
- [ ] 404-02 Window chrome with three dots is drawn
- [ ] 404-03 Large 404 number is red and readable on the terminal background
- [ ] 404-04 Transcript shows the request path escaped
- [ ] 404-05 Suggestions list three similar posts
- [ ] 404-06 Retry form appears only with JavaScript
- [ ] 404-07 Search form works without JavaScript
- [ ] 404-08 Rail and terminal dock are absent

## SHELL

- [ ] SHELL-01 Skip link is the first focusable element and targets main
- [ ] SHELL-02 Landmarks header, nav, main, aside and footer exist once each
- [ ] SHELL-03 Layout holds from 320px to 3840px without horizontal scroll
- [ ] SHELL-04 Rail folds behind the Sidebar button below 900px container width
- [ ] SHELL-05 Sidebar button toggles aria-expanded and closes on Escape
- [ ] SHELL-06 Footer shows about, explore, connect and legal columns
- [ ] SHELL-07 Dark is the default scheme and follows the system preference until the visitor chooses
- [ ] SHELL-08 Theme toggle persists the choice and does not flash on load
- [ ] SHELL-09 Language switcher appears when more than one language is configured
- [ ] SHELL-10 Focus outlines are visible on every interactive element

## TERMINAL

- [ ] TERMINAL-01 Dock is a sticky right column on desktop and a bottom sheet on phone
- [ ] TERMINAL-02 Tab opens the panel and focus moves to the input
- [ ] TERMINAL-03 Backtick toggles the dock when no input has focus
- [ ] TERMINAL-04 ls, cd, cat, tree, grep, open, theme and lang commands work
- [ ] TERMINAL-05 Terminal is read only and has no network access of its own
- [ ] TERMINAL-06 Close button and Escape return focus to the tab
- [ ] TERMINAL-07 Open or closed state is not persisted and the terminal makes no network request after the first load
- [ ] TERMINAL-08 Reduced motion disables the caret blink

_68 items._

