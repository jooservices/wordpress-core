# Company, profile and editorial data

## JOOservices Resume: `plugins/jooservices-resume`

This plugin owns company history and the **Profile and editorial fields** module (author profile, topic/category, and featured-post fields). It is independent of the theme core (own autoloader, text domain `jooservices-resume`, PHP >= 8.1, WP >= 6.6). The `joo_company` post type and `_joo_company_*` meta keys are the stable company data contract shared with theme core and the future bundle. Data survives deactivation and uninstall by default; defining `JOO_RESUME_DELETE_DATA_ON_UNINSTALL` as `true` opts into deleting plugin-owned data.

Compatibility note: the former lab-only folder and slug `jooservices-content` has no public installs. No old-slug alias or data migration is provided.

| Component | What it stores |
|---|---|
| `joo_company` (private CPT, REST base `joo-companies`) | role (<= 80), `from`/`to` months (`YYYY-MM`, empty `to` = present), https URL, logo id, pinned flag, `menu_order`, excerpt = short description. Only one company can be pinned: pinning one unpins the others. REST validates months, URL and period order (400); publishing needs a role and a start month. Classic meta box works without JavaScript; list columns Role, Period, Pinned, Order. `?post_type=joo_company` and `?p=<id>` on the front end are 404 |
| Term meta (category, tag) | `joo_short_description` (160), `joo_image_id` (image only), `joo_intro` (200), `joo_icon`; forms with nonce and `edit_term` check, media picker, list columns |
| User meta | `joo_title`, `joo_short_bio`, `joo_avatar_id`, `joo_socials` (max 8, https only); avatar override through `get_avatar_data`; email is never exposed |
| Featured posts | `_joo_featured`, `_joo_featured_order` (0..999); column, sortable order, "featured only" filter, bulk actions (editors) |

### Company data model (JOOS-87)

`Model\CompanyRepository` is the single read path (typed `CompanyRecord`); `Modules\CompanyDataModel` registers the fields, validates REST writes and strips private data from REST responses. Existing companies keep working: every new field is empty. Contract: `plugins/jooservices-resume/schema/company.schema.json` (public projection), validated in CI by `tools/validate-schemas.mjs`.

| Field (meta key) | Rules |
|---|---|
| `_joo_company_city`, `_country`, `_industry` | text, 80 |
| `_joo_company_summary` | card summary, plain text, 200 |
| `_joo_company_description` | limited rich text (p, br, strong, em, ul, ol, li, https/mailto links) |
| `_joo_company_employment` | `full_time`, `contract`, `freelance`, `founder` or empty |
| `_joo_company_remote` | boolean |
| `_joo_company_roles` | JSON (`{"v":1,"items":[...]}`): title, period, summary; inside the company period, no overlaps, newest first |
| `_joo_company_achievements` | JSON: text, optional numeric metric, unit, https link |
| `_joo_company_technologies` | JSON list of unique tags (max 40) |
| `_joo_company_custom` + option `joo_resume_custom_fields` | owner-defined fields (label, key, type text/number/date/url/list, public or private, order); values validated by type |
| `_joo_company_compensation`, `_joo_company_equity` | PRIVATE: never in REST for visitors; `_joo_company_public_fields` lists the ones the owner switched to public |

`current` is derived from an empty end month; `CompanyRecord::durationLabel()` gives `2 yrs 3 mos`. Order: pinned first, then `manual` (menu order, newest start) or `date`. REST write errors are 400 with a message per problem. The classic admin box and the repeater screens come with JOOS-88; the front end with JOOS-89.

### Admin screens (JOOS-88)

Decision: classic meta boxes plus a small vanilla script (`assets/company-editor.js`, no build step) instead of block-editor sidebar panels. They work with the existing classic company screen, every control is a native labelled field, and the repeaters are reachable and operable with the keyboard.

| Screen | What it does |
|---|---|
| Company edit | boxes: details, profile and location, roles, achievements and technologies, custom fields, private details and visibility. Repeaters add, remove and move rows with buttons (no drag needed), announce each change in a live region and keep names in screen order. Each group is saved only when valid; messages say what to fix. Unsaved-changes warning. |
| Company list | logo, role, period, current, pinned, visibility, order; filter (all, current, past, pinned); bulk actions pin (exactly one), hide, show |
| Companies > Settings (`manage_options`) | display defaults (date format, show duration, private labels; option `joo_resume_settings`) and the custom-field builder with a preview |
| Companies > Import / Export (`manage_options`) | export own JSON (complete, private fields included), JSON Resume `work` and CSV (public data only, formula-safe); import of own JSON or JSON Resume with a dry run: nothing is created unless every company is valid (1 MB, 200 companies, `.json`) |

Mapping lives in `Transfer\Mapper` (pure, unit-tested); `Model\CompanyWriter` is the single write path of the importer. Nonces and capability checks guard every action.

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
