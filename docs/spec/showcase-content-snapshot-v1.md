# Showcase content snapshot format, version 1

The content fetch pipeline writes the snapshot outside Git to
`Workspaces/artifacts/jooservices-wp-themes/content-fetch/content-snapshot.json.gz`.
The adjacent `.sha256` file is the SHA-256 of the compressed bytes. The raw
HTTP response cache is in the sibling `content-cache/` directory. Neither is a
repository fixture or a buyer deliverable.

The gzip member contains UTF-8 JSON with recursively sorted object keys and a
trailing newline. Items are sorted by stable `id`; the gzip timestamp is zero.
These rules make two writes from the same snapshot byte-identical.

```json
{
  "format_version": 1,
  "source_catalog_sha256": "hex SHA-256 of tools/content-fetch/sources.json",
  "source_failures": [],
  "item_count": 1,
  "coverage": {
    "total_items": 1,
    "by_category": { "news-announcements": 1 },
    "by_language": { "en": 1 },
    "by_source": { "hacker-news": 1 },
    "tag_count": 0,
    "by_tag": {},
    "by_year": {},
    "matrix": {
      "category_count": 1,
      "languages": ["en"],
      "author_count": 1,
      "code_languages": [],
      "long_form": 0,
      "code_heavy": 0,
      "image_heavy": 0,
      "comment_heavy": 0
    }
  },
  "items": []
}
```

Each `items[]` record has these fields:

The top-level `source_failures` array contains sources that could not be
fetched during the refresh. Each entry has `source_id` and a short `message`;
one source failure does not discard results from other sources.

| Field | Type | Meaning |
|---|---|---|
| `id` | string | Stable `source_id:external_id` identifier, with non-identifier characters sanitized. |
| `source_id`, `source_name` | string | Source registry key and display name. |
| `source_url` | absolute HTTP(S) URL | Required primary source URL for the item. |
| `url` | absolute HTTP(S) URL | Item/canonical URL used for de-duplication and linking. |
| `external_id` | string | Provider's stable item identifier. |
| `title` | string | Required headline. |
| `date` | ISO timestamp or `null` | Provider publication/update time when supplied. |
| `author`, `author_url` | string or `null` | Source attribution identity when supplied. |
| `excerpt` | string | Up to 1,200 characters, present only when the item's allowlisted license permits reuse. Empty for headline-only records. |
| `body` | string | Full source text only for an allowlisted license and adapter that explicitly supports it. Empty for headline-only records. |
| `language` | BCP-47 base code | Lowercase language code such as `en` or `vi`; unknown values use `unknown`. |
| `tags`, `topics` | sorted string arrays | Provider tags and source editorial topic hints. |
| `license` | enum string | One of `ALL-RIGHTS-RESERVED`, `LICENSE-UNKNOWN`, `CC0-1.0`, `PUBLIC-DOMAIN-US`, `CC-BY-3.0`, `CC-BY-4.0`, `CC-BY-SA-2.5`, `CC-BY-SA-3.0`, `CC-BY-SA-4.0`, or `MIT`. |
| `license_url` | absolute URL or `null` | Applicable license or terms page. An item-level license URL takes precedence when the provider reports one. |
| `content_scope` | enum | `HEADLINE`, `EXCERPT`, or `BODY`; restricted/unknown items are forced to `HEADLINE`. |
| `attribution` | object or `null` | For reusable licensed text: `title`, `author`, `url`, `license`, `license_url`, and `changes`. |
| `fetched_at` | ISO timestamp | Required timestamp captured for the refresh run. It is stored once and never generated during replay. |
| `editorial_category` | enum string | One of the 20 categories in `tools/content-fetch/classify.mjs`, with a source/item override applied before snapshotting. |
| `metadata` | object | Minimal provider metadata; never a copied article body. |

## Tier rules

Tier A admits `ALL-RIGHTS-RESERVED` and `LICENSE-UNKNOWN` only at `HEADLINE`
scope. The source headline is contextual metadata; the later writer must make
an original short summary and link out. The fetcher does not manufacture a
summary from restricted material. Tier B admits only the explicit
redistributable allowlist and requires the full attribution block for CC BY,
CC BY-SA, and MIT. The audit command fails the full snapshot if any item fails
the selected tier. It does not silently drop rights-restricted items.

Items are de-duplicated by normalized URL, then by a title-token Jaccard score
of at least 0.86. Stable lexical `source_id`, item ID, and URL order decides
which duplicate is retained. `editorial-overrides.json` is optional input for
the library/API caller; its keys may be item ID, item URL, or source ID. The
override value must be one of the documented categories.

Coverage counts the fetched source snapshot only. `tag_count` is the number of
distinct provider tags; `by_tag` counts occurrences; `by_year` counts records
with a provider date in each calendar year. Long-form, code-heavy, image-heavy,
and comment-heavy targets that need authored/generated posts are reported as
zero until the downstream generator provides those shape fields. A source
snapshot is input evidence, not a claim that those generated-content targets
have been met.
