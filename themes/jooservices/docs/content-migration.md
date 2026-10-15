# Content migration notes

What the owner enters or maps when moving jooservices.com to this theme.

| Item | Where | Notes |
|---|---|---|
| Profile (name, title, short bio, socials, avatar) | Users, your profile | Feeds the profile card, author box and structured data. Pick the owner under Settings, JOOservices if the first administrator is not the author |
| Companies | Companies (companion plugin) | Role, start month and end month (empty = present), URL, logo; tick **pinned** on the current company (only one) |
| Categories | Posts, Categories | Parent categories give the tree; set the short description (160 characters); a primary category (Yoast or Rank Math, or the Primary category field) decides the breadcrumb |
| Topics (tags) | Posts, Tags | Optional image and a short intro for the Top topics block |
| Featured posts | Post editor, Featured panel | Tick Featured and set the order; the first five fill the slider |
| Language | Post editor, Language panel | `en` or `vi`; set **translation of** on the Vietnamese post to link the pair (hreflang is generated) |
| Menus | Appearance, Menus | Assign one to **Primary menu**; the footer links are in the Footer template part (Site Editor) |
| Pages | Pages | About uses the **Full width** template; the privacy and contact pages are linked in the footer |
| Images | Media | Featured images should be at least 900 px wide; sizes `joo-card`, `joo-cover`, `joo-thumb` and `joo-avatar` are created for new uploads; regenerate old ones with `wp media regenerate` |
| Redirects | Hosting or an SEO plugin | Keep old URLs alive; the theme does not create redirects. The permalink structure used by the demo is `/%category%/%postname%/` |

Imported WordPress export files work as long as the post meta keys `_joo_featured`, `_joo_featured_order`, `_joo_lang`, `_joo_translation_of` and `_joo_primary_category` are kept (see `docs/spec/naming-registry.json`).
