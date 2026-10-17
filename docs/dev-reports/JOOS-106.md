# JOOS-106 — showcase content sourcing

## Implemented

- Added a source registry with 21 enabled API/feed sources and one disabled
  vendor-feed template. Entries record URLs, terms and license references,
  robots policy, request interval, item cap, and topic hints.
- Added adapters for Hacker News, DEV.to, RSS, Atom, NVD, MDN, Wikipedia, Stack
  Exchange, and arXiv. Restricted or unknown material is headline-only. Reuse
  of CC-licensed text carries item-level attribution.
- Added a transport with the configured User-Agent, robots checks, redirect
  destination checks, per-origin throttling, a response timeout and size cap,
  and a local raw-response cache. Failed sources are recorded without
  discarding results from other sources.
- Added deterministic gzip snapshots, SHA-256 sidecars, Tier A/B audits,
  offline replay, de-duplication, 20 editorial categories, validated overrides,
  and coverage by category, language, source, tag, year, author, and content
  shape.
- Made `tools/mcp-smoke.mjs` safe to import in tests: `.env` is read only when
  the file is run as a CLI, so importing its pure response parser does not need
  local credentials.
- Documented the snapshot contract and source operating guide in
  `docs/spec/showcase-content-snapshot-v1.md` and
  `docs/guides/content-sourcing.md`.

## Local snapshot

The fetched snapshot is intentionally kept outside Git, as required for this
worktree's generated artifacts:

`/Users/vietvu/Workspaces/artifacts/jooservices-wp-themes/content-fetch/content-snapshot.json.gz`

SHA-256 of the compressed file:

`a6239412e19e9e90f76d1f44816bf23f629e41271dd1f9fd5c3ef69d5eaf1194`

The snapshot contains 508 records from nine source IDs: DEV.to (100), Hacker
News (297), NVD (20), VTV technology/cybersecurity/AI (21/29/30), WordPress
news (9), MDN (1), and Wikipedia (1). It represents 16 of 20 editorial
categories, four languages including 81 Vietnamese records, 348 distinct
authors, and 195 provider tags. The year counts are 1988 (2), 1989 (3), 1990
(11), 1991 (4), and 2026 (486); the source dates are heavily concentrated in
2026.

The license counts are 486 `ALL-RIGHTS-RESERVED`, 20 `LICENSE-UNKNOWN`, one
`CC-BY-SA-2.5`, and one `CC-BY-SA-4.0`. All records pass Tier A. Tier B correctly
rejects 506 records; only two records in this snapshot have a redistributable
license. This mixed snapshot is not a Tier B package.

## How to run

```sh
# Full refresh; fetch responses from configured sources.
bash docker/scripts/runtime.sh node tools/content-fetch.mjs refresh

# Rebuild from cached responses and fetch cache misses only.
bash docker/scripts/runtime.sh node tools/content-fetch.mjs refresh --cached true

# Read-only operations against the frozen local snapshot.
bash docker/scripts/runtime.sh node tools/content-fetch.mjs audit --tier A
bash docker/scripts/runtime.sh node tools/content-fetch.mjs coverage
bash docker/scripts/runtime.sh node tools/content-fetch.mjs replay --tier A

# Offline adapter, license, classifier, cache, and replay tests.
make test-unit
```

Refresh is the only network operation. Unit tests use fictional recorded
fixtures and a local fake HTTP server. The cached mode reuses raw responses;
cache misses are fetched subject to robots and rate limits.

## Verification

| Check | Result |
|---|---|
| `make lint` | Pass. |
| `make analyse` | Pass; zero violations, warnings, or errors. |
| `make test-unit` | Pass; PHPUnit 280 tests / 1,442 assertions, Vitest 298 tests across 43 files. |
| `tools/content-fetch.test.mjs` | 28/28 pass, including 20/20 labelled category cases. |
| Tier A snapshot audit | Pass, 508 records. |
| Tier B snapshot audit | Fails closed for 506 non-redistributable records, as expected. |

Eleven configured sources failed closed during refresh: eight GitHub release
feeds and Lobsters were disallowed by their current robots rules; Stack Exchange
robots could not be verified; and arXiv was disallowed. Their source IDs and
messages are stored in the snapshot's `source_failures` array. No blocked URL
was requested.

## Known limits and owner review

- This ticket supplies sourced inputs. It does not generate the 40 long-form,
  30 code-heavy, 20 image-heavy, or 10 comment-heavy posts, or the static site
  pages. Those shape targets remain zero until the downstream JOOS-93 generator
  creates and measures original content. The source snapshot has four languages
  but no generated code-language coverage yet.
- The source snapshot reaches the 300-item, 12-category, 150-tag, six-author,
  and 50-Vietnamese-record thresholds. Its 16 represented categories include
  zero counts for four editorial categories; the generator still needs to
  populate those categories. Source dates are mostly from 2026 rather than
  evenly distributed across three years.
- The PO's Tier A/Tier B decision and review of 30 representative items per
  tier remain pending. The audit tool is ready to enforce the selected policy.
- Feed availability depends on current provider robots rules. The blocked
  sources remain configured for visibility and are not bypassed.
