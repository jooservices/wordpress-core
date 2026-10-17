import { XMLParser } from 'fast-xml-parser';
import { LICENSES } from './license-policy.mjs';

const xmlParser = new XMLParser({
  ignoreAttributes: false,
  attributeNamePrefix: '@_',
  textNodeName: '#text',
  parseTagValue: false,
  trimValues: true,
});

const asArray = (value) => (value === null || value === undefined ? [] : Array.isArray(value) ? value : [value]);
const scalar = (value) => (value && typeof value === 'object' ? (value['#text'] ?? '') : (value ?? ''));
const trim = (value) =>
  String(scalar(value))
    .replace(/<[^>]*>/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();

/**
 * Parses an upstream response with the source-specific adapter named by the registry.
 * @param {Record<string, any>} source Source registry record.
 * @param {string | object} raw Response body.
 * @param {{fetchedAt: string, fetchText?: (url: string) => Promise<string>}} options Adapter options.
 */
export async function adapt(source, raw, options) {
  const { fetchedAt, fetchText = async () => '' } = options;
  const payload = typeof raw === 'string' ? raw : JSON.stringify(raw);
  const license = (itemLicense) => normalizeLicense(itemLicense ?? source.license);
  const make = (values) => normalizeItem(source, { ...values, license: license(values.license) }, fetchedAt);

  switch (source.adapter) {
    case 'hacker-news': {
      const ids = JSON.parse(payload);
      const items = [];
      for (const id of ids.slice(0, source.max_items ?? ids.length)) {
        const storyUrl = `https://hacker-news.firebaseio.com/v0/item/${encodeURIComponent(id)}.json`;
        const story = JSON.parse(await fetchText(storyUrl));
        if (!story || story.deleted || story.dead || !story.title) continue;
        const url = story.url || `https://news.ycombinator.com/item?id=${encodeURIComponent(story.id)}`;
        items.push(
          make({
            source_url: url,
            url,
            external_id: String(story.id),
            title: trim(story.title),
            date: unixDate(story.time),
            author: trim(story.by),
            language: 'en',
            tags: ['hacker-news', story.type ?? 'story'],
            content_scope: 'HEADLINE',
            metadata: {
              score: Number(story.score ?? 0),
              discussion_url: `https://news.ycombinator.com/item?id=${story.id}`,
            },
          })
        );
      }
      return items;
    }
    case 'devto':
      return asArray(JSON.parse(payload))
        .slice(0, source.max_items)
        .map((entry) =>
          make({
            source_url: entry.url,
            url: entry.url,
            external_id: String(entry.id),
            title: trim(entry.title),
            date: dateValue(entry.published_at ?? entry.created_at),
            author: trim(entry.user?.name ?? entry.user?.username),
            author_url: entry.user?.username ? `https://dev.to/${entry.user.username}` : null,
            language: entry.language ?? 'en',
            tags: asArray(entry.tag_list).map(trim).filter(Boolean),
            // DEV article descriptions and bodies are copyrighted by default; only titles are retained.
            content_scope: 'HEADLINE',
            metadata: { reading_time_minutes: Number(entry.reading_time_minutes ?? 0) },
          })
        );
    case 'rss':
      return parseRss(source, payload, make);
    case 'atom':
      return parseAtom(source, payload, make);
    case 'nvd':
      return asArray(JSON.parse(payload).vulnerabilities)
        .slice(0, source.max_items)
        .map(({ cve }) => {
          const description = asArray(cve.descriptions).find((entry) => entry.lang === 'en')?.value ?? '';
          const cwe = asArray(cve.weaknesses)
            .flatMap((entry) => asArray(entry.description))
            .map((entry) => entry.value);
          return make({
            source_url: `https://nvd.nist.gov/vuln/detail/${encodeURIComponent(cve.id)}`,
            url: `https://nvd.nist.gov/vuln/detail/${encodeURIComponent(cve.id)}`,
            external_id: cve.id,
            title: cve.id,
            date: dateValue(cve.published),
            author: trim(cve.sourceIdentifier),
            language: 'en',
            tags: ['cve', ...cwe],
            content_scope: 'HEADLINE',
            metadata: {
              severity: firstSeverity(cve.metrics),
              description_available: Boolean(description),
              cna: trim(cve.sourceIdentifier),
            },
          });
        });
    case 'mdn-github':
      return parseMdn(source, JSON.parse(payload), make, fetchText);
    case 'wikipedia': {
      if (payload.trimStart().startsWith('<')) return parseWikipediaPage(source, payload, make);
      const response = JSON.parse(payload);
      const pages = response.query?.pages ?? (response.extract ? [response] : []);
      return asArray(pages).map((page) => {
        const pageUrl =
          page.content_urls?.desktop?.page ??
          (page.pageid ? `https://en.wikipedia.org/?curid=${encodeURIComponent(page.pageid)}` : source.url);
        const excerpt = trim(page.extract);
        return make({
          source_url: pageUrl,
          url: pageUrl,
          external_id: String(page.pageid ?? page.title),
          title: trim(page.title),
          date: dateValue(page.timestamp ?? page.modified),
          author: 'Wikipedia contributors',
          language: 'en',
          excerpt,
          content_scope: 'EXCERPT',
          attribution: {
            title: trim(page.title),
            author: 'Wikipedia contributors',
            url: pageUrl,
            license: LICENSES.CC_BY_SA_4,
            license_url: source.license_url,
            changes: 'Extract shortened to the source API introduction; review before republication.',
          },
          metadata: {
            page_id: page.pageid ?? null,
            revision_id: page.revision ?? page.revision_id ?? page.revisions?.[0]?.revid ?? null,
          },
        });
      });
    }
    case 'stack-exchange':
      return asArray(JSON.parse(payload).items)
        .slice(0, source.max_items)
        .map((entry) => {
          const itemLicense = licenseFromUrl(entry.license);
          const author = trim(entry.owner?.display_name);
          const title = trim(entry.title);
          const link = entry.link;
          const attribution =
            itemLicense && itemLicense !== LICENSES.UNKNOWN
              ? {
                  title,
                  author,
                  url: link,
                  license: itemLicense,
                  license_url: entry.license,
                  changes: 'Title only; no question or answer body was copied.',
                }
              : null;
          return make({
            source_url: link,
            url: link,
            external_id: String(entry.question_id),
            title,
            date: dateValue(entry.last_activity_date ? new Date(entry.last_activity_date * 1000).toISOString() : null),
            author,
            author_url: entry.owner?.link ?? null,
            language: 'en',
            tags: asArray(entry.tags),
            license: itemLicense ?? source.license,
            license_url: entry.license ?? source.license_url,
            content_scope: 'HEADLINE',
            attribution,
            metadata: { answer_count: Number(entry.answer_count ?? 0), score: Number(entry.score ?? 0) },
          });
        });
    case 'arxiv':
      return parseArxiv(source, payload, make);
    default:
      throw new Error(`Unsupported content adapter: ${source.adapter}`);
  }
}

function parseWikipediaPage(source, html, make) {
  const title = html.match(/<h1\b[^>]*id=["']firstHeading["'][^>]*>([\s\S]*?)<\/h1>/i)?.[1] ?? '';
  const content = html.match(/<div\b[^>]*id=["']mw-content-text["'][^>]*>([\s\S]*?)<\/div>\s*<\/main>/i)?.[1] ?? html;
  const paragraphs = [...content.matchAll(/<p\b[^>]*>([\s\S]*?)<\/p>/gi)]
    .map((match) => decodeHtml(trim(match[1].replace(/<sup\b[^>]*>[\s\S]*?<\/sup>/gi, ' '))))
    .filter((paragraph) => paragraph.length > 80)
    .slice(0, 2);
  if (!paragraphs.length)
    throw new Error(`${source.id}: Wikipedia page did not contain a reusable introduction paragraph`);
  const pageTitle = decodeHtml(trim(title));
  const pageUrl = source.url;
  return [
    make({
      source_url: pageUrl,
      url: pageUrl,
      external_id: pageTitle,
      title: pageTitle,
      date: null,
      author: 'Wikipedia contributors',
      language: 'en',
      excerpt: paragraphs.join(' ').slice(0, 1200),
      content_scope: 'EXCERPT',
      attribution: {
        title: pageTitle,
        author: 'Wikipedia contributors',
        url: pageUrl,
        license: LICENSES.CC_BY_SA_4,
        license_url: source.license_url,
        changes: 'The first two non-empty introductory paragraphs were shortened to 1,200 characters.',
      },
      metadata: { extraction: 'page-introduction' },
    }),
  ];
}

function decodeHtml(value) {
  return String(value)
    .replace(/&amp;/g, '&')
    .replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>')
    .replace(/&quot;/g, '"')
    .replace(/&#39;|&apos;/g, "'")
    .replace(/&#(\d+);/g, (_, code) => String.fromCodePoint(Number(code)))
    .replace(/&#x([0-9a-f]+);/gi, (_, code) => String.fromCodePoint(parseInt(code, 16)))
    .replace(/\s+/g, ' ')
    .trim();
}

function parseRss(source, payload, make) {
  const parsed = xmlParser.parse(payload);
  const rows = asArray(parsed.rss?.channel?.item ?? parsed.feed?.entry);
  return rows
    .slice(0, source.max_items)
    .map((entry, index) => {
      const link = typeof entry.link === 'string' ? entry.link : (entry.link?.['@_href'] ?? entry.guid ?? '');
      const itemLicenseUrl = trim(entry.license ?? entry['creativeCommons:license']);
      const license = itemLicenseUrl ? (licenseFromUrl(itemLicenseUrl) ?? LICENSES.UNKNOWN) : null;
      const itemSource = itemLicenseUrl ? { ...source, license_url: itemLicenseUrl } : source;
      return make({
        source_url: trim(link),
        url: trim(link),
        external_id: trim(entry.guid ?? entry.id ?? link) || `${source.id}-${index}`,
        title: trim(entry.title),
        date: dateValue(entry.pubDate ?? entry.published ?? entry.updated),
        author: trim(entry['dc:creator'] ?? entry.author?.name ?? entry.author),
        language: trim(entry['xml:lang'] ?? entry.language) || languageFromText(trim(entry.title)),
        tags: asArray(entry.category).map(trim).filter(Boolean),
        excerpt: license ? trim(entry.description ?? entry.summary) : '',
        body: '',
        license: license ?? source.license,
        license_url: itemLicenseUrl || source.license_url,
        content_scope: license ? 'EXCERPT' : 'HEADLINE',
        attribution: license
          ? makeAttribution(
              trim(entry.title),
              trim(link),
              trim(entry['dc:creator'] ?? entry.author),
              license,
              itemSource
            )
          : null,
      });
    })
    .filter((entry) => entry.source_url && entry.title);
}

function parseAtom(source, payload, make) {
  const parsed = xmlParser.parse(payload);
  return asArray(parsed.feed?.entry)
    .slice(0, source.max_items)
    .map((entry) => {
      const links = asArray(entry.link);
      const link = links.find((candidate) => candidate['@_rel'] === 'alternate') ?? links[0];
      const url = trim(link?.['@_href'] ?? entry.id);
      const itemLicenseUrl = trim(entry.license);
      const license = itemLicenseUrl ? (licenseFromUrl(itemLicenseUrl) ?? LICENSES.UNKNOWN) : null;
      const itemSource = itemLicenseUrl ? { ...source, license_url: itemLicenseUrl } : source;
      return make({
        source_url: url,
        url,
        external_id: trim(entry.id ?? url),
        title: trim(entry.title),
        date: dateValue(entry.updated ?? entry.published),
        author: trim(entry.author?.name),
        language: trim(entry['@_xml:lang'] ?? entry.lang) || languageFromText(trim(entry.title)),
        tags: [],
        excerpt: license ? trim(entry.summary ?? entry.content) : '',
        body: license ? trim(entry.content) : '',
        license: license ?? source.license,
        license_url: itemLicenseUrl || source.license_url,
        content_scope: license ? (entry.content ? 'BODY' : 'EXCERPT') : 'HEADLINE',
        attribution: license
          ? makeAttribution(trim(entry.title), url, trim(entry.author?.name), license, itemSource)
          : null,
      });
    })
    .filter((entry) => entry.source_url && entry.title);
}

async function parseMdn(source, entries, make, fetchText) {
  const files = asArray(entries)
    .filter((entry) => entry.type === 'file' && entry.name.endsWith('.md'))
    .slice(0, source.max_items);
  const records = [];
  for (const file of files) {
    if (!file.download_url) continue;
    const markdown = await fetchText(file.download_url);
    const frontmatter = markdown.match(/^---\s*\n([\s\S]*?)\n---/);
    const title =
      frontmatter?.[1].match(/^title:\s*["']?(.+?)["']?\s*$/m)?.[1] ??
      file.name.replace(/\.md$/, '').replaceAll('_', ' ');
    const description = frontmatter?.[1].match(/^short-title:\s*["']?(.+?)["']?\s*$/m)?.[1] ?? '';
    const pagePath = file.path
      .replace(/^files/, '')
      .replace(/\/index\.md$/, '')
      .replace(/\.md$/, '')
      .toLowerCase();
    const sourceUrl = `https://developer.mozilla.org/en-US/docs${pagePath}`;
    const body = markdown.replace(/^---\s*\n[\s\S]*?\n---\s*\n/, '').trim();
    records.push(
      make({
        source_url: sourceUrl,
        url: sourceUrl,
        external_id: file.sha ?? file.path,
        title: trim(title),
        date: null,
        author: 'Mozilla Contributors',
        language: 'en',
        excerpt: trim(description),
        body,
        license: source.license,
        content_scope: 'BODY',
        attribution: makeAttribution(
          trim(title),
          sourceUrl,
          'Mozilla Contributors',
          source.license,
          source,
          'Markdown excerpt from the MDN content repository.'
        ),
        metadata: { repository_path: file.path, git_sha: file.sha ?? null },
      })
    );
  }
  return records;
}

function parseArxiv(source, payload, make) {
  const parsed = xmlParser.parse(payload);
  return asArray(parsed.feed?.entry)
    .slice(0, source.max_items)
    .map((entry) => {
      const link = asArray(entry.link).find((candidate) => candidate['@_title'] === 'pdf') ?? asArray(entry.link)[0];
      const url = trim(link?.['@_href'] ?? entry.id);
      const author = asArray(entry.author)
        .map((candidate) => trim(candidate.name))
        .filter(Boolean)
        .join(', ');
      return make({
        source_url: trim(entry.id ?? url),
        url: trim(entry.id ?? url),
        external_id: trim(entry.id ?? url),
        title: trim(entry.title),
        date: dateValue(entry.published),
        author,
        language: 'en',
        tags: asArray(entry.category)
          .map((category) => category['@_term'])
          .filter(Boolean),
        // Abstract rights are not inferred from API availability; only metadata headlines are retained.
        content_scope: 'HEADLINE',
        metadata: { abstract_available: Boolean(trim(entry.summary)), pdf_url: trim(link?.['@_href']) },
      });
    })
    .filter((entry) => entry.source_url && entry.title);
}

function normalizeItem(source, values, fetchedAt) {
  const license = normalizeLicense(values.license);
  const allowText = license !== LICENSES.ALL_RIGHTS_RESERVED && license !== LICENSES.UNKNOWN;
  const contentScope = allowText ? (values.content_scope ?? 'HEADLINE') : 'HEADLINE';
  const title = trim(values.title);
  const record = {
    id: `${source.id}:${String(values.external_id ?? values.url ?? values.source_url).replace(/[^A-Za-z0-9._:-]+/g, '-')}`,
    source_id: source.id,
    source_name: source.name,
    source_url: trim(values.source_url),
    url: trim(values.url ?? values.source_url),
    external_id: String(values.external_id ?? ''),
    title,
    date: values.date ?? null,
    author: trim(values.author) || null,
    author_url: values.author_url ?? null,
    excerpt: allowText ? trim(values.excerpt).slice(0, 1200) : '',
    body: allowText ? String(values.body ?? '') : '',
    language: normalizeLanguage(values.language),
    tags: [...new Set((values.tags ?? []).map(trim).filter(Boolean))].sort((a, b) => a.localeCompare(b)),
    topics: [...(source.topics ?? [])],
    license,
    license_url: values.license_url ?? source.license_url ?? null,
    content_scope: contentScope,
    attribution: allowText
      ? (values.attribution ??
        (licenseNeedsAttribution(license)
          ? makeAttribution(title, values.source_url, values.author, license, source)
          : null))
      : null,
    fetched_at: fetchedAt,
    metadata: values.metadata ?? {},
  };
  if (!record.source_url || !record.url || !record.title)
    throw new Error(`${source.id}: adapter produced an item without source URL, item URL, or title`);
  return record;
}

function makeAttribution(
  title,
  url,
  author,
  license,
  source,
  changes = 'Metadata shortened for the showcase snapshot.'
) {
  return {
    title,
    author: author || 'Source author',
    url,
    license,
    license_url: source.license_url,
    changes,
  };
}

function normalizeLicense(value) {
  if (!value) return LICENSES.UNKNOWN;
  const recognized = Object.values(LICENSES).find((license) => license.toLowerCase() === String(value).toLowerCase());
  return recognized ?? LICENSES.UNKNOWN;
}

function licenseFromUrl(value) {
  if (!value) return null;
  const normalized = String(value).toUpperCase().replaceAll('_', '-');
  if (/CREATIVECOMMONS\.ORG\/LICENSES\/BY-SA\/2\.5/.test(normalized)) return LICENSES.CC_BY_SA_2_5;
  if (/CREATIVECOMMONS\.ORG\/LICENSES\/BY-SA\/3\.0/.test(normalized)) return LICENSES.CC_BY_SA_3;
  if (/CREATIVECOMMONS\.ORG\/LICENSES\/BY-SA\/4\.0/.test(normalized)) return LICENSES.CC_BY_SA_4;
  if (/CREATIVECOMMONS\.ORG\/LICENSES\/BY\/3\.0/.test(normalized)) return LICENSES.CC_BY_3;
  if (/CREATIVECOMMONS\.ORG\/LICENSES\/BY\/4\.0/.test(normalized)) return LICENSES.CC_BY_4;
  return Object.values(LICENSES).find((license) => normalized.includes(license)) ?? null;
}

function licenseNeedsAttribution(license) {
  return license.startsWith('CC-BY') || license === LICENSES.MIT;
}

function unixDate(value) {
  return value === null || value === undefined ? null : dateValue(new Date(Number(value) * 1000).toISOString());
}

function dateValue(value) {
  if (!value) return null;
  const parsed = new Date(value);
  return Number.isNaN(parsed.getTime()) ? null : parsed.toISOString();
}

function normalizeLanguage(value) {
  const language = String(value ?? 'unknown')
    .toLowerCase()
    .split(/[-_]/)[0];
  return /^[a-z]{2,3}$/.test(language) ? language : languageFromText(String(value ?? ''));
}

function languageFromText(text) {
  return /[ăâđêôơưĂÂĐÊÔƠƯạảãấầẩẫậắằẳẵặẹẻẽếềểễệịỉĩọỏốồổỗộớờởỡợụủứừửữựỳỵỷỹ]/i.test(text) ? 'vi' : 'en';
}

function firstSeverity(metrics = {}) {
  for (const version of ['cvssMetricV40', 'cvssMetricV31', 'cvssMetricV30', 'cvssMetricV2']) {
    const metric = asArray(metrics[version])[0]?.cvssData;
    if (metric?.baseSeverity) return metric.baseSeverity;
  }
  return null;
}
