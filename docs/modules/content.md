# Content data layer

## Companion plugin `plugins/jooservices-content`
Independent of the theme core (own autoloader, text domain `jooservices-content`, PHP >= 8.1, WP >= 6.6). Data survives deactivation; `uninstall.php` deletes data only when `JOO_CONTENT_DELETE_DATA_ON_UNINSTALL` is true.

| Component | What it stores |
|---|---|
| `joo_company` (private CPT, REST base `joo-companies`) | role (<= 80), `from`/`to` months (`YYYY-MM`, empty `to` = present), https URL, logo id, pinned flag, `menu_order`, excerpt = short description. Only one company can be pinned: pinning one unpins the others. REST validates months, URL and period order (400); publishing needs a role and a start month. Classic meta box works without JavaScript; list columns Role, Period, Pinned, Order. `?post_type=joo_company` and `?p=<id>` on the front end are 404 |
| Term meta (category, tag) | `joo_short_description` (160), `joo_image_id` (image only), `joo_intro` (200), `joo_icon`; forms with nonce and `edit_term` check, media picker, list columns |
| User meta | `joo_title`, `joo_short_bio`, `joo_avatar_id`, `joo_socials` (max 8, https only); avatar override through `get_avatar_data`; email is never exposed |
| Featured posts | `_joo_featured`, `_joo_featured_order` (0..999); column, sortable order, "featured only" filter, bulk actions (editors) |

## Core side (`Content\`)
`ContentProviderInterface` (`@api`): `profile()`, `pinnedCompany()`, `companies($page,$perPage,$excludePinned)`, `categoryTree($hideEmpty)`, `topics($limit,$strategy)`, `featured($limit)`, `related($postId,$limit)`; immutable value objects in `Content\Model`.

* `WpContentProvider` reads by meta key (no plugin classes are imported) and caches every answer in `FragmentCache` with tags (`posts`, `terms`, `companies`, `profile`, `settings`). Meta changes with a `_joo_` prefix, term meta, `sticky_posts` and status changes invalidate the right tag.
* `NullProvider` answers empty everywhere; it is chosen when the `joo_company` type is not registered (Site Health recommends the plugin).
* Related posts: `3 x same primary category + 2 x shared tags + 1 x shared category ancestor`, ties newest then highest id (`RelatedScorer`, deterministic). Featured falls back to sticky posts. Category tree counts include descendants; empty categories are hidden. Topics: `count` or the manual list (`content.topics_manual`), image falls back to the newest post image of the tag, intro to the term description.
* Only published, non-password-protected posts are returned.

## REST (`joo/v1`, GET, public)
`/companies` (page, per_page 1..20, exclude_pinned; headers `X-WP-Total`, `X-WP-TotalPages`), `/categories/tree`, `/topics` (limit 1..20, strategy), `/featured` (limit 1..12), `/related/{post_id}` (limit 1..8), `/profile`, `/fragment/{name}` (lazy module). Responses carry `Cache-Control: public, max-age=300, stale-while-revalidate=600`, a weak ETag and answer conditional requests with 304. Invalid arguments give 400. Query counts: warm cache <= 6 per endpoint; cold cache is bounded (measured 8 to 34, asserted in `ContentTest`).

Example:
```php
$provider = Kernel::container()->get( \JOOservices\ThemeCore\Content\ContentProviderInterface::class );
foreach ( $provider->featured( 5 ) as $card ) {
	echo esc_html( $card->title );
}
```
