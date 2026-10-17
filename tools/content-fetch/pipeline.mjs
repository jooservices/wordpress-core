import { createHash } from 'node:crypto';
import { readFileSync, writeFileSync } from 'node:fs';
import { gunzipSync, gzipSync } from 'node:zlib';
import { adapt } from './adapters.mjs';
import { classify, coverageReport } from './classify.mjs';
import { assertItemAllowed, auditItem, licenseRule } from './license-policy.mjs';

/** Fetches and normalizes one configured source using only the injected transport. */
export async function fetchSource(source, transport, { fetchedAt = new Date().toISOString(), refresh = false } = {}) {
  if (!source.enabled) return [];
  if (!source.url) throw new Error(`${source.id}: an enabled source must have a URL`);
  const raw = await transport.requestText(source.url, { source, refresh });
  const records = await adapt(source, raw, {
    fetchedAt,
    fetchText: (url) => transport.requestText(url, { source, refresh }),
  });
  return records.map((record) => ({ ...record, editorial_category: classify(record) }));
}

/** Fetches enabled sources in manifest order. Sequential requests honor each origin's rate policy. */
export async function fetchCatalog(catalog, transport, { fetchedAt = new Date().toISOString(), refresh = false } = {}) {
  const records = [];
  const failures = [];
  for (const source of catalog.sources) {
    try {
      records.push(...(await fetchSource(source, transport, { fetchedAt, refresh })));
    } catch (error) {
      failures.push({ source_id: source.id, message: error instanceof Error ? error.message : String(error) });
    }
  }
  return { records: deduplicateRecords(records), failures };
}

/** Removes canonical URL duplicates first, then near-duplicate headlines, retaining stable first-source priority. */
export function deduplicateRecords(records, threshold = 0.86) {
  const seenUrls = new Set();
  const accepted = [];
  const stableRecords = [...records].sort((a, b) =>
    `${a.source_id ?? ''}:${a.id ?? ''}:${a.url ?? ''}`.localeCompare(
      `${b.source_id ?? ''}:${b.id ?? ''}:${b.url ?? ''}`
    )
  );
  for (const item of stableRecords) {
    const canonical = canonicalUrl(item.url ?? item.source_url);
    if (seenUrls.has(canonical)) continue;
    const duplicate = accepted.some((other) => titleSimilarity(item.title, other.title) >= threshold);
    if (duplicate) continue;
    seenUrls.add(canonical);
    accepted.push(item);
  }
  return accepted.sort((a, b) => a.id.localeCompare(b.id));
}

/** Creates a validated, stable snapshot document. */
export function createSnapshot(records, catalogHash = '', sourceFailures = []) {
  const items = [...records].sort((a, b) => a.id.localeCompare(b.id));
  for (const item of items) assertItemAllowed(item, 'A');
  return {
    format_version: 1,
    source_catalog_sha256: catalogHash,
    source_failures: [...sourceFailures].sort((a, b) => a.source_id.localeCompare(b.source_id)),
    item_count: items.length,
    coverage: coverageReport(items),
    items,
  };
}

/** Audits every item and returns errors suitable for a CLI report. */
export function auditSnapshot(snapshot, tier) {
  const errors = [];
  if (snapshot?.format_version !== 1) errors.push(`unsupported snapshot format_version: ${snapshot?.format_version}`);
  if (!Array.isArray(snapshot?.items)) errors.push('snapshot.items must be an array');
  for (const [index, item] of (snapshot?.items ?? []).entries()) {
    for (const error of auditItem(item, tier)) errors.push(`items[${index}] ${item?.id ?? ''}: ${error}`);
  }
  return errors;
}

/** Replays a frozen snapshot into generator-facing records without reading the clock or network. */
export function replaySnapshot(snapshot, tier = 'A') {
  const errors = auditSnapshot(snapshot, tier);
  if (errors.length)
    throw new Error(`Snapshot audit failed for Tier ${String(tier).toUpperCase()}:\n${errors.join('\n')}`);
  return snapshot.items.map((item) => {
    const rule = licenseRule(item.license);
    return {
      id: item.id,
      title: item.title,
      source_url: item.source_url,
      url: item.url,
      source_id: item.source_id,
      date: item.date,
      author: item.author,
      language: item.language,
      tags: item.tags,
      editorial_category: item.editorial_category,
      excerpt: item.excerpt,
      body: item.body,
      license: item.license,
      fetched_at: item.fetched_at,
      content_scope: item.content_scope,
      attribution: item.attribution,
      redistribution_allowed: rule.redistribution,
    };
  });
}

/** Serializes JSON with recursive key sorting for stable replay artifacts. */
export function stableStringify(value) {
  return `${JSON.stringify(sortKeys(value), null, 2)}\n`;
}

/** Reads an uncompressed JSON or gzip snapshot by extension. */
export function readSnapshot(path) {
  const bytes = readFileSync(path);
  const json = path.endsWith('.gz') ? gunzipSync(bytes).toString('utf8') : bytes.toString('utf8');
  return JSON.parse(json);
}

/** Writes deterministic gzip bytes plus a SHA-256 sidecar, or plain JSON when requested. */
export function writeSnapshot(path, snapshot) {
  const json = Buffer.from(stableStringify(snapshot));
  const bytes = path.endsWith('.gz') ? gzipSync(json, { level: 9 }) : json;
  writeFileSync(path, bytes);
  writeFileSync(`${path}.sha256`, `${sha256(bytes)}  ${path.split(/[\\/]/).at(-1)}\n`);
  return { bytes: bytes.length, sha256: sha256(bytes) };
}

/** SHA-256 for snapshot and manifest evidence. */
export function sha256(value) {
  return createHash('sha256').update(value).digest('hex');
}

/** Counts known license decisions; unknown identifiers remain audit errors, never silently admitted. */
export function licenseCoverage(items) {
  const counts = {};
  for (const item of items) counts[item.license] = (counts[item.license] ?? 0) + 1;
  return Object.fromEntries(Object.entries(counts).sort(([a], [b]) => a.localeCompare(b)));
}

export function manifestHash(path) {
  return sha256(readFileSync(path));
}

function canonicalUrl(value) {
  const url = new URL(value);
  url.hash = '';
  for (const key of [...url.searchParams.keys()]) if (/^(utm_|fbclid$|gclid$)/i.test(key)) url.searchParams.delete(key);
  url.hostname = url.hostname.toLowerCase();
  if (url.pathname !== '/') url.pathname = url.pathname.replace(/\/$/, '');
  return url.href;
}

function titleSimilarity(left = '', right = '') {
  const tokens = (text) =>
    new Set(
      String(text)
        .toLowerCase()
        .normalize('NFKD')
        .replace(/[^\p{L}\p{N}]+/gu, ' ')
        .trim()
        .split(/\s+/)
        .filter((token) => token.length > 1)
    );
  const a = tokens(left);
  const b = tokens(right);
  if (a.size < 4 || b.size < 4) return 0;
  let intersection = 0;
  for (const word of a) if (b.has(word)) intersection += 1;
  return intersection / (a.size + b.size - intersection);
}

function sortKeys(value) {
  if (Array.isArray(value)) return value.map(sortKeys);
  if (!value || typeof value !== 'object') return value;
  return Object.fromEntries(
    Object.keys(value)
      .sort()
      .map((key) => [key, sortKeys(value[key])])
  );
}
