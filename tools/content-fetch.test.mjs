// @vitest-environment node
import { createServer } from 'node:http';
import { mkdirSync, mkdtempSync, readFileSync, rmSync } from 'node:fs';
import { join } from 'node:path';
import { once } from 'node:events';
import { afterEach, describe, expect, it } from 'vitest';
import { ROOT } from './lib/paths.mjs';
import { runCli } from './content-fetch.mjs';
import { adapt } from './content-fetch/adapters.mjs';
import { classify, EDITORIAL_CATEGORIES, coverageReport } from './content-fetch/classify.mjs';
import { assertItemAllowed, auditItem, LICENSES } from './content-fetch/license-policy.mjs';
import {
  auditSnapshot,
  createSnapshot,
  deduplicateRecords,
  fetchCatalog,
  readSnapshot,
  replaySnapshot,
  stableStringify,
  writeSnapshot,
} from './content-fetch/pipeline.mjs';
import { createTransport, enforceRobots, parseRobots } from './content-fetch/transport.mjs';

const fixtureDir = new URL('./content-fetch/fixtures/', import.meta.url);
const readFixture = (name) => readFileSync(new URL(name, fixtureDir), 'utf8');
const fetchedAt = '2026-10-16T12:00:00.000Z';
const source = (id, adapter, options = {}) => ({
  id,
  name: `${id} fixture`,
  url: `https://example.test/${id}`,
  adapter,
  enabled: true,
  license: LICENSES.ALL_RIGHTS_RESERVED,
  license_url: 'https://example.test/terms',
  max_items: 10,
  topics: ['fixture'],
  ...options,
});

let tempDirs = [];
afterEach(() => {
  for (const path of tempDirs) rmSync(path, { recursive: true, force: true });
  tempDirs = [];
});

function tempDir() {
  const root = join(ROOT, '.tmp');
  mkdirSync(root, { recursive: true });
  const path = mkdtempSync(join(root, 'content-fetch-'));
  tempDirs.push(path);
  return path;
}

describe('source adapters with recorded fixtures', () => {
  it('adapts Hacker News headlines and retains discussion links without story body text', async () => {
    const responses = {
      'https://hacker-news.firebaseio.com/v0/item/41001.json': JSON.stringify({
        id: 41001,
        title: 'Fictional HN cache index change',
        url: 'https://example.test/cache-index',
        by: 'fixture-user',
        time: 1767780000,
        score: 12,
        text: 'Do not copy story text.',
      }),
      'https://hacker-news.firebaseio.com/v0/item/41002.json': JSON.stringify({
        id: 41002,
        title: 'Ask HN: fictional queue tuning',
        by: 'other-user',
        time: 1767780100,
        type: 'story',
      }),
    };
    const items = await adapt(source('hacker-news', 'hacker-news'), readFixture('hacker-news.json'), {
      fetchedAt,
      fetchText: async (url) => responses[url],
    });
    expect(items).toHaveLength(2);
    expect(items[0]).toMatchObject({
      source_url: 'https://example.test/cache-index',
      license: LICENSES.ALL_RIGHTS_RESERVED,
      content_scope: 'HEADLINE',
      excerpt: '',
      body: '',
      fetched_at: fetchedAt,
    });
    expect(items[0].metadata.discussion_url).toBe('https://news.ycombinator.com/item?id=41001');
  });

  it('adapts DEV API headlines without copying the article description', async () => {
    const items = await adapt(source('devto', 'devto'), readFixture('devto.json'), { fetchedAt });
    expect(items[0]).toMatchObject({
      title: 'A fictional guide to stable queue workers',
      language: 'en',
      author: 'Fixture Author',
      content_scope: 'HEADLINE',
      excerpt: '',
      body: '',
    });
    expect(items[0].tags).toEqual(['php', 'queues']);
  });

  it('adapts RSS and retains content only when an item declares an allowlisted license', async () => {
    const restricted = await adapt(source('lobsters', 'rss'), readFixture('lobsters.xml'), { fetchedAt });
    const licensed = await adapt(source('vendor-cc', 'rss'), readFixture('creative-commons.xml'), { fetchedAt });
    expect(restricted[0]).toMatchObject({
      license: LICENSES.ALL_RIGHTS_RESERVED,
      content_scope: 'HEADLINE',
      excerpt: '',
    });
    expect(licensed[0]).toMatchObject({
      license: LICENSES.CC_BY_SA_4,
      content_scope: 'EXCERPT',
      author: 'Fixture Writer',
    });
    expect(licensed[0].attribution).toMatchObject({
      title: 'Licensed fixture about open data',
      author: 'Fixture Writer',
      license: LICENSES.CC_BY_SA_4,
    });
  });

  it('adapts GitHub Atom releases as headlines even when the feed embeds release notes', async () => {
    const items = await adapt(source('github-node', 'atom'), readFixture('github-releases.xml'), { fetchedAt });
    expect(items[0]).toMatchObject({
      url: 'https://github.com/example/runtime/releases/tag/v1.2.0',
      content_scope: 'HEADLINE',
      excerpt: '',
      body: '',
      license: LICENSES.ALL_RIGHTS_RESERVED,
    });
  });

  it('adapts NVD CVE identifiers and severity without copying third-party descriptions', async () => {
    const items = await adapt(source('nvd-cve', 'nvd', { license: LICENSES.UNKNOWN }), readFixture('nvd.json'), {
      fetchedAt,
    });
    expect(items[0]).toMatchObject({
      title: 'CVE-2099-12345',
      content_scope: 'HEADLINE',
      excerpt: '',
      license: LICENSES.UNKNOWN,
    });
    expect(items[0].metadata).toMatchObject({ severity: 'HIGH', description_available: true });
    expect(items[0].tags).toContain('CWE-79');
  });

  it('adapts MDN repository pages with CC BY-SA attribution', async () => {
    const items = await adapt(
      source('mdn', 'mdn-github', { license: LICENSES.CC_BY_SA_2_5 }),
      readFixture('mdn-directory.json'),
      {
        fetchedAt,
        fetchText: async () => readFixture('mdn-page.md'),
      }
    );
    expect(items[0]).toMatchObject({
      title: 'Fictional API reference',
      license: LICENSES.CC_BY_SA_2_5,
      content_scope: 'BODY',
      author: 'Mozilla Contributors',
    });
    expect(items[0].body).toContain('This made-up paragraph');
    expect(items[0].attribution).toMatchObject({
      title: 'Fictional API reference',
      author: 'Mozilla Contributors',
      license: LICENSES.CC_BY_SA_2_5,
    });
  });

  it('adapts Wikipedia extracts with page attribution', async () => {
    const items = await adapt(
      source('wikipedia', 'wikipedia', { license: LICENSES.CC_BY_SA_4 }),
      readFixture('wikipedia.json'),
      { fetchedAt }
    );
    expect(items[0]).toMatchObject({
      license: LICENSES.CC_BY_SA_4,
      content_scope: 'EXCERPT',
      author: 'Wikipedia contributors',
    });
    expect(items[0].attribution.url).toBe('https://en.wikipedia.org/?curid=771');
  });

  it('adapts Wikimedia REST page summaries with their canonical page URL', async () => {
    const items = await adapt(
      source('wikipedia', 'wikipedia', { license: LICENSES.CC_BY_SA_4 }),
      readFixture('wikipedia-rest.json'),
      { fetchedAt }
    );
    expect(items[0]).toMatchObject({
      source_url: 'https://en.wikipedia.org/wiki/Fictional_DevOps_summary',
      license: LICENSES.CC_BY_SA_4,
      content_scope: 'EXCERPT',
      date: '2026-01-11T10:00:00.000Z',
    });
  });

  it('extracts only introductory paragraphs from a robots-allowed Wikipedia page and records modifications', async () => {
    const items = await adapt(
      source('wikipedia', 'wikipedia', {
        license: LICENSES.CC_BY_SA_4,
        url: 'https://en.wikipedia.org/wiki/Fictional_DevOps_topic',
      }),
      readFixture('wikipedia-page.html'),
      { fetchedAt }
    );
    expect(items[0]).toMatchObject({
      title: 'Fictional DevOps topic',
      content_scope: 'EXCERPT',
      license: LICENSES.CC_BY_SA_4,
      author: 'Wikipedia contributors',
    });
    expect(items[0].excerpt).toContain('fictional introductory paragraph');
    expect(items[0].attribution.changes).toContain('shortened to 1,200 characters');
  });

  it('adapts Stack Exchange questions using per-item license and contributor attribution without bodies', async () => {
    const items = await adapt(
      source('stack-overflow', 'stack-exchange', { license: LICENSES.UNKNOWN }),
      readFixture('stack-exchange.json'),
      { fetchedAt }
    );
    expect(items[0]).toMatchObject({
      license: LICENSES.CC_BY_SA_4,
      content_scope: 'HEADLINE',
      author: 'Fixture Contributor',
      excerpt: '',
      body: '',
    });
    expect(items[0].attribution).toMatchObject({
      author: 'Fixture Contributor',
      url: 'https://stackoverflow.com/questions/871/fictional-worker-recovery',
    });
  });

  it('adapts arXiv metadata without inferring a reuse license from API availability', async () => {
    const items = await adapt(source('arxiv', 'arxiv', { license: LICENSES.UNKNOWN }), readFixture('arxiv.xml'), {
      fetchedAt,
    });
    expect(items[0]).toMatchObject({
      title: 'Fictional reproducibility note for software experiments',
      license: LICENSES.UNKNOWN,
      content_scope: 'HEADLINE',
      excerpt: '',
      body: '',
    });
    expect(items[0].metadata.abstract_available).toBe(true);
  });

  it('requires every normalized item to carry provenance, license, and fetch time', async () => {
    const items = await adapt(source('devto', 'devto'), readFixture('devto.json'), { fetchedAt });
    for (const item of items) {
      expect(item.source_url).toMatch(/^https:\/\//);
      expect(item.license).toBeTruthy();
      expect(item.fetched_at).toBe(fetchedAt);
    }
  });

  it('documents each active source URL, license, terms, robots decision, and request interval', async () => {
    const catalog = JSON.parse(readFileSync(new URL('./content-fetch/sources.json', import.meta.url), 'utf8'));
    const active = catalog.sources.filter((entry) => entry.enabled);
    expect(active.length).toBeGreaterThanOrEqual(15);
    for (const entry of active) {
      expect(entry.url).toMatch(/^https:\/\//);
      expect(entry.terms_url).toMatch(/^https:\/\//);
      expect(Object.values(LICENSES)).toContain(entry.license);
      expect(entry.robots_decision).toContain('deny');
      expect(entry.rate_limit_ms).toBeGreaterThan(0);
    }
    expect(catalog.sources.find((entry) => entry.id === 'vendor-blog-template').enabled).toBe(false);
    expect(catalog.sources.find((entry) => entry.id === 'hacker-news').max_items).toBe(300);
    await expect(runCli(['refresh', '--source', 'hacker-news', '--max-items', '501'])).rejects.toThrow(
      '--max-items must be an integer from 1 to 500'
    );
  });
});

describe('license policy', () => {
  const headline = {
    id: 'test:1',
    source_url: 'https://example.test/source',
    title: 'A source headline',
    license: LICENSES.ALL_RIGHTS_RESERVED,
    content_scope: 'HEADLINE',
    fetched_at: fetchedAt,
  };

  it('allows reserved and unknown sources only as headlines in Tier A', () => {
    expect(auditItem(headline, 'A')).toEqual([]);
    expect(auditItem({ ...headline, license: LICENSES.UNKNOWN }, 'A')).toEqual([]);
    expect(auditItem({ ...headline, excerpt: 'copied text', content_scope: 'EXCERPT' }, 'A')).toContain(
      `${LICENSES.ALL_RIGHTS_RESERVED} permits headline facts only`
    );
  });

  it('rejects non-redistributable items from Tier B and allows attributed CC BY-SA', () => {
    expect(auditItem(headline, 'B')).toEqual([`${LICENSES.ALL_RIGHTS_RESERVED} is not allowed for Tier B`]);
    const licensed = {
      ...headline,
      license: LICENSES.CC_BY_SA_4,
      content_scope: 'EXCERPT',
      attribution: {
        title: 'Source title',
        author: 'Source author',
        url: headline.source_url,
        license_url: 'https://creativecommons.org/licenses/by-sa/4.0/',
      },
    };
    expect(auditItem(licensed, 'B')).toEqual([]);
    expect(auditItem({ ...licensed, attribution: null }, 'B')).toContain(
      `${LICENSES.CC_BY_SA_4} requires an attribution block`
    );
  });

  it('fails closed for missing provenance, malformed timestamps, and unrecognized license identifiers', () => {
    expect(auditItem({ ...headline, source_url: '', fetched_at: 'yesterday', license: 'custom-license' }, 'A')).toEqual(
      expect.arrayContaining([
        'source_url is required',
        'fetched_at must be an ISO timestamp with a timezone',
        'license is not on the allowlist: custom-license',
      ])
    );
  });

  it('throws a useful error when the explicit license audit fails', () => {
    expect(() => assertItemAllowed(headline, 'B')).toThrow(/Tier B|not allowed/);
  });
});

describe('classification and coverage', () => {
  const labeled = [
    ['A tool launch announced this week', 'news-announcements'],
    ['How to write a reliable webhook tutorial', 'tutorials-howtos'],
    ['Hands-on review of a new terminal tool', 'product-tool-reviews'],
    ['Release notes and changelog for version 4.2', 'release-notes-changelogs'],
    ['CVE-2099-44 security advisory for a library', 'security-advisories'],
    ['Engineering career opinion about hiring', 'opinion-career'],
    ['Case study: postmortem of a failed migration', 'case-studies-postmortems'],
    ['Quick tips and tricks cheat sheet for git', 'tips-cheatsheets'],
    ['Glossary: what is a transaction isolation level?', 'glossary-faq'],
    ['Database benchmark comparison with latency numbers', 'comparisons-benchmarks'],
    ['Home lab server rack build with a small NAS', 'hardware-homelab'],
    ['LLM agents and machine learning research notes', 'ai-llm'],
    ['Deploy Kubernetes containers to a cloud cluster', 'devops-cloud'],
    ['Laravel PHP backend queue worker patterns', 'backend'],
    ['Frontend JavaScript accessibility with CSS', 'frontend'],
    ['PostgreSQL database indexing and SQL query plans', 'databases'],
    ['DNS networking TLS firewall configuration', 'networking-security'],
    ['Open-source repository notes for maintainers', 'open-source'],
    ['Podcast episode from the developer conference', 'events-podcasts'],
    ['Interview and reading list of books and resources', 'books-resources-interviews-roundups'],
  ];

  it('classifies at least 90% of the labeled sample (20/20)', () => {
    const correct = labeled.filter(([title, expected]) => classify({ title }) === expected).length;
    expect(correct / labeled.length).toBeGreaterThanOrEqual(0.9);
    expect(correct).toBe(20);
  });

  it('uses validated editorial overrides ahead of keyword classification', () => {
    const item = { id: 'override:1', title: 'CVE-2099 release notice' };
    expect(classify(item, { [item.id]: 'opinion-career' })).toBe('opinion-career');
    expect(() => classify(item, { [item.id]: 'made-up-category' })).toThrow(/Unknown editorial category/);
    expect(EDITORIAL_CATEGORIES).toHaveLength(20);
  });

  it('does not let broad source topic hints determine an individual item category', () => {
    expect(classify({ title: 'A new capability arrives today', topics: ['open-source', 'devops-cloud'] })).toBe(
      'news-announcements'
    );
  });

  it('reports counts by category, source, language, and content matrix dimension', () => {
    const report = coverageReport([
      {
        source_id: 'a',
        language: 'en',
        date: '2025-02-01T00:00:00.000Z',
        editorial_category: 'backend',
        author: 'One',
        tags: ['javascript', 'queues'],
        code_languages: ['php'],
        shape: { long_form: true, code_heavy: true },
      },
      {
        source_id: 'b',
        language: 'vi',
        date: '2026-03-01T00:00:00.000Z',
        editorial_category: 'ai-llm',
        author: 'Two',
        tags: ['python', 'queues'],
        code_languages: ['python'],
        shape: { image_heavy: true, comment_heavy: true },
      },
    ]);
    expect(report.total_items).toBe(2);
    expect(report.by_category.backend).toBe(1);
    expect(report.by_language.vi).toBe(1);
    expect(report.tag_count).toBe(3);
    expect(report.by_tag.queues).toBe(2);
    expect(report.by_year).toEqual({ 2025: 1, 2026: 1 });
    expect(report.matrix).toMatchObject({
      category_count: 2,
      author_count: 2,
      long_form: 1,
      code_heavy: 1,
      image_heavy: 1,
      comment_heavy: 1,
    });
    expect(report.matrix.code_languages).toEqual(['php', 'python']);
  });
});

describe('deduplication, snapshots, and offline replay', () => {
  const record = (id, title, url = `https://example.test/${id}`) => ({
    id,
    source_id: 'fixture',
    source_url: url,
    url,
    title,
    date: '2026-01-01T00:00:00.000Z',
    author: null,
    language: 'en',
    tags: [],
    editorial_category: 'news-announcements',
    excerpt: '',
    body: '',
    license: LICENSES.ALL_RIGHTS_RESERVED,
    license_url: 'https://example.test/terms',
    content_scope: 'HEADLINE',
    attribution: null,
    fetched_at: fetchedAt,
  });

  it('deduplicates normalized URLs and highly similar headlines deterministically', () => {
    const items = [
      record(
        'fixture:1',
        'A guide to reliable queue worker recovery patterns',
        'https://example.test/post?utm_source=feed'
      ),
      record('fixture:2', 'A guide to reliable queue worker recovery patterns', 'https://example.test/post'),
      record('fixture:3', 'A guide to reliable queue worker recovery patterns today', 'https://example.test/near-copy'),
      record('fixture:4', 'A distinct distributed database indexing tutorial', 'https://example.test/other'),
    ];
    expect(deduplicateRecords(items).map((item) => item.id)).toEqual(['fixture:1', 'fixture:4']);
    expect(deduplicateRecords([...items].reverse()).map((item) => item.id)).toEqual(['fixture:1', 'fixture:4']);
  });

  it('writes byte-identical gzip snapshots and replays them without timestamps or network changes', () => {
    const dir = tempDir();
    const snapshot = createSnapshot(
      [record('fixture:2', 'Second fixture headline'), record('fixture:1', 'First fixture headline')],
      'catalog-hash'
    );
    const firstPath = join(dir, 'first.json.gz');
    const secondPath = join(dir, 'second.json.gz');
    const first = writeSnapshot(firstPath, snapshot);
    const second = writeSnapshot(secondPath, snapshot);
    expect(first.sha256).toBe(second.sha256);
    expect(readFileSync(firstPath)).toEqual(readFileSync(secondPath));
    const read = readSnapshot(firstPath);
    expect(replaySnapshot(read, 'A')).toEqual(replaySnapshot(read, 'A'));
    expect(stableStringify(replaySnapshot(read, 'A'))).toBe(stableStringify(replaySnapshot(read, 'A')));
    expect(auditSnapshot(read, 'A')).toEqual([]);
    expect(auditSnapshot(read, 'B').length).toBeGreaterThan(0);
  });

  it('rejects a snapshot item with missing required provenance before replay', () => {
    const snapshot = createSnapshot([record('fixture:1', 'A valid source headline')]);
    snapshot.items[0].source_url = '';
    expect(auditSnapshot(snapshot, 'A')).toContain('items[0] fixture:1: source_url is required');
    expect(() => replaySnapshot(snapshot, 'A')).toThrow(/Snapshot audit failed/);
  });

  it('keeps valid source results and records a source failure instead of aborting a full refresh', async () => {
    const result = await fetchCatalog(
      {
        sources: [
          source('devto', 'devto', { url: 'https://example.test/devto' }),
          source('blocked', 'rss', { url: 'https://example.test/blocked' }),
        ],
      },
      {
        requestText: async (url) => {
          if (url.endsWith('/devto')) return readFixture('devto.json');
          throw new Error('robots.txt disallows fixture source');
        },
      },
      { fetchedAt }
    );
    expect(result.records).toHaveLength(1);
    expect(result.failures).toEqual([{ source_id: 'blocked', message: 'robots.txt disallows fixture source' }]);
  });
});

describe('content fetch CLI offline commands', () => {
  it('audits, reports coverage, and replays a stored fixture snapshot without network access', async () => {
    const dir = tempDir();
    const snapshotPath = join(dir, 'snapshot.json');
    const replayPath = join(dir, 'replay.json');
    const records = await adapt(source('devto', 'devto'), readFixture('devto.json'), { fetchedAt });
    writeSnapshot(snapshotPath, createSnapshot(records, 'fixture-catalog'));
    const output = { stdout: '', stderr: '' };
    const io = {
      stdout: { write: (text) => ((output.stdout += text), true) },
      stderr: { write: (text) => ((output.stderr += text), true) },
    };

    expect(await runCli(['audit', '--snapshot', snapshotPath, '--tier', 'A'], io)).toBe(0);
    expect(output.stdout).toContain('Tier A audit passed for 1 snapshot items.');
    expect(await runCli(['coverage', '--snapshot', snapshotPath], io)).toBe(0);
    expect(output.stdout).toContain('"total_items": 1');
    expect(await runCli(['replay', '--snapshot', snapshotPath, '--tier', 'A', '--out', replayPath], io)).toBe(0);
    expect(JSON.parse(readFileSync(replayPath, 'utf8')).items[0].id).toBe(records[0].id);

    expect(await runCli(['audit', '--snapshot', snapshotPath, '--tier', 'B'], io)).toBe(1);
    expect(output.stderr).toContain('is not allowed for Tier B');
  });
});

describe('robots.txt-aware cached transport using a local fake server', () => {
  it('identifies itself, enforces allow/disallow and crawl delay, and avoids repeated requests from cache', async () => {
    const requests = [];
    const server = createServer((request, response) => {
      requests.push({ url: request.url, userAgent: request.headers['user-agent'] });
      if (request.url === '/robots.txt') {
        response.writeHead(200, { 'content-type': 'text/plain' });
        response.end('User-agent: *\nDisallow: /blocked\nAllow: /blocked/open\nCrawl-delay: 0.1\n');
      } else {
        response.writeHead(200, { 'content-type': 'text/plain' });
        response.end(`fixture body for ${request.url}`);
      }
    });
    server.listen(0, '127.0.0.1');
    await once(server, 'listening');
    const address = server.address();
    const port = typeof address === 'object' && address ? address.port : 0;
    const dir = tempDir();
    const productAgent = 'JOOservicesShowcaseContentBot/1.0 (fixture test)';
    let clock = 1000;
    const slept = [];
    const transport = createTransport({
      cacheDir: dir,
      userAgent: productAgent,
      now: () => clock,
      sleep: async (ms) => {
        slept.push(ms);
        clock += ms;
      },
    });
    const sourceConfig = { id: 'fake', rate_limit_ms: 250, robots_decision: 'deny on unavailable robots.txt' };
    try {
      const firstUrl = `http://127.0.0.1:${port}/blocked/open/story`;
      expect(await transport.requestText(firstUrl, { source: sourceConfig })).toContain('/blocked/open/story');
      expect(await transport.requestText(`http://127.0.0.1:${port}/second`, { source: sourceConfig })).toContain(
        '/second'
      );
      const beforeCache = requests.length;
      expect(await transport.requestText(firstUrl, { source: sourceConfig })).toContain('/blocked/open/story');
      expect(requests).toHaveLength(beforeCache);
      await expect(
        transport.requestText(`http://127.0.0.1:${port}/blocked/private`, { source: sourceConfig })
      ).rejects.toThrow(/robots.txt disallows/);
      expect(requests.filter((entry) => entry.url.startsWith('/blocked/private'))).toHaveLength(0);
      expect(
        requests.filter((entry) => entry.url !== '/robots.txt').every((entry) => entry.userAgent === productAgent)
      ).toBe(true);
      expect(slept).toEqual([250, 250]);
    } finally {
      server.close();
      await once(server, 'close');
    }
  });

  it('selects the most specific robots rule and rejects an unsupported URL scheme', async () => {
    const groups = parseRobots('User-agent: *\nDisallow: /private\nAllow: /private/public\n');
    expect(() =>
      enforceRobots(new URL('https://example.test/private/public/page'), 'JOOservicesShowcaseContentBot/1.0', groups)
    ).not.toThrow();
    expect(() =>
      enforceRobots(new URL('https://example.test/private/page'), 'JOOservicesShowcaseContentBot/1.0', groups)
    ).toThrow(/disallows/);
    await expect(
      createTransport({ cacheDir: tempDir(), userAgent: 'JOOservicesShowcaseContentBot/1.0' }).requestText(
        'file:///etc/passwd'
      )
    ).rejects.toThrow(/HTTP\(S\)/);
  });
});
