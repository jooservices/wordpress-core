# Showcase content sourcing

`tools/content-fetch.mjs` has four explicit operations. Refresh is the only
network operation; tests, audit, coverage, and replay read fixtures or a frozen
snapshot and need no external network access.

```sh
# Fetch configured public feeds/APIs and create the local snapshot.
bash docker/scripts/runtime.sh node tools/content-fetch.mjs refresh

# Restrict a manual refresh to one source and cap its entries.
bash docker/scripts/runtime.sh node tools/content-fetch.mjs refresh --source hacker-news,mdn-content --max-items 5

# Rebuild using the raw response cache; uncached URLs are fetched normally.
bash docker/scripts/runtime.sh node tools/content-fetch.mjs refresh --cached true

# Audit and replay the saved artifact without network access.
bash docker/scripts/runtime.sh node tools/content-fetch.mjs audit --tier A
bash docker/scripts/runtime.sh node tools/content-fetch.mjs coverage
bash docker/scripts/runtime.sh node tools/content-fetch.mjs replay --tier A
```

The default snapshot and HTTP cache live below
`Workspaces/artifacts/jooservices-wp-themes/content-fetch/`; both are local
artifacts and must not be committed. The default file is
`content-snapshot.json.gz`, with `content-snapshot.json.gz.sha256` beside it.
The snapshot contract is specified in
[`../spec/showcase-content-snapshot-v1.md`](../spec/showcase-content-snapshot-v1.md).

The registry `tools/content-fetch/sources.json` is the source/terms contract.
Each configured source records the public URL, adapter, terms link, license
assumption, robots decision, request interval, maximum item count, topics, and
enabled state. Vendor blogs remain disabled until a specific feed, terms page,
reuse permission, and robots decision are reviewed and entered. No adapter
logs in, bypasses a paywall, or downloads publisher images. MDN and Wikipedia
adapters retrieve text only where the applicable content license permits
reuse; other adapters keep restricted material headline-only.

| Adapter | Sources | Content handling |
|---|---|---|
| `hacker-news` | Official Firebase API | Up to 300 headline and discussion/source records from the top-stories feed. The API documents public data and no rate limit; this client still waits one second between requests. |
| `devto` | DEV API | Up to 100 headlines and provider metadata; article descriptions and bodies are not copied. Requests send the required identifying User-Agent. |
| `rss` | Lobsters, PHP, WordPress.org, VTV technology, VTV cybersecurity and AI feeds, reviewed vendor feeds | Headline and link unless an individual feed item declares a recognized reuse license. |
| `atom` | GitHub release feeds for PHP, Laravel, WordPress, Node.js, Docker/Moby, Kubernetes, Linux, and Go | Release title and links only. Repository software licenses are not presumed to license release notes. |
| `nvd` | NVD CVE API | CVE identifier, date, severity, and weakness identifiers; no CNA description. NVD material may include CNA-authored data, so the registry defaults each item to license unknown rather than assuming all records are public domain. |
| `mdn-github` | MDN content repository | Markdown content under CC BY-SA 2.5 or later, with a Mozilla Contributors attribution block and source page link. |
| `wikipedia` | Wikipedia page introduction | Up to two opening paragraphs under CC BY-SA 4.0 with Wikipedia contributors attribution and the page link. API endpoints disallowed by robots are not called. |
| `stack-exchange` | Stack Exchange questions API | Question title and attribution identity only. The per-item API license is retained; question/answer bodies are not requested or copied. |
| `arxiv` | arXiv Atom API | Paper metadata/headline only. Abstracts are not redistributed unless a future per-item license check can establish permission. |

Refresh visits sources sequentially. A source-level robots, HTTP, or parse
failure is retained in `source_failures` and does not discard records from
other sources; an empty result is an empty source, not a failure. If no source
returns items, refresh exits without replacing the current snapshot.

Robots rules are checked for every origin and cached for at most 24 hours. An
unavailable robots policy fails closed; HTTP 404 means the origin has no
robots.txt. Redirects are handled manually and their destination origin is
checked before the request. Requests use the registry User-Agent, a per-origin
minimum interval combined with robots `Crawl-delay`, a 15-second timeout, a
5 MiB response ceiling, and a SHA-256-keyed raw-response cache. By default,
`refresh` bypasses cached content while still enforcing robots rules. Pass
`--cached true` to rebuild from cached responses; cache misses are fetched
normally, and robots rules are still enforced.

Run adapter and policy tests through the repository unit suite:

```sh
make test-unit
```

The recorded fixtures contain fictional values and exercise all adapter types,
license gates, classification, URL/similarity deduplication, snapshot
determinism, and transport behavior against a local fake HTTP server. No unit
test contacts an external source.

## Provider references

- [Hacker News API](https://github.com/HackerNews/API)
- [DEV API](https://developers.forem.com/api/v0)
- [Lobsters About](https://lobste.rs/about)
- [GitHub release Atom feeds](https://github.com/cli/cli/releases.atom) and [GitHub Terms](https://docs.github.com/en/site-policy/github-terms/github-terms-of-service)
- [PHP News RSS reference](https://www.php.net/archive/2002.php)
- [WordPress.org News feed](https://wordpress.org/news/feed/)
- [NVD API documentation](https://nvd.nist.gov/developers/vulnerabilities)
- [MDN reuse and attribution policy](https://developer.mozilla.org/en-US/docs/MDN/Writing_guidelines/Attrib_copyright_license)
- [Wikimedia Terms](https://foundation.wikimedia.org/wiki/Terms_of_Use) and [CC BY-SA 4.0](https://creativecommons.org/licenses/by-sa/4.0/)
- [Stack Exchange public network terms](https://stackoverflow.com/legal/terms-of-service/public) and [API filters](https://api.stackexchange.com/docs/filters)
- [arXiv API terms](https://info.arxiv.org/help/api/tou.html)
- [VTV RSS directory](https://vtv.vn/rss.htm)
