import { createHash } from 'node:crypto';
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';

const MAX_RESPONSE_BYTES = 5 * 1024 * 1024;
const REDIRECT_STATUSES = new Set([301, 302, 303, 307, 308]);

/**
 * Creates a small HTTP client that checks robots.txt, identifies itself, rate limits, and caches responses.
 * @param {{cacheDir: string, userAgent: string, fetchImpl?: typeof fetch, now?: () => number, sleep?: (ms: number) => Promise<void>, maxResponseBytes?: number}} options
 */
export function createTransport(options) {
  const {
    cacheDir,
    userAgent,
    fetchImpl = globalThis.fetch,
    now = () => Date.now(),
    sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms)),
    maxResponseBytes = MAX_RESPONSE_BYTES,
  } = options;
  if (!cacheDir) throw new Error('cacheDir is required');
  if (!userAgent) throw new Error('userAgent is required');
  if (typeof fetchImpl !== 'function') throw new Error('Fetch API is not available');
  const hostLastRequest = new Map();
  const robots = new Map();

  /** @param {string | URL} input @param {{source?: Record<string, any>, refresh?: boolean, redirectCount?: number}} [options] */
  async function requestText(input, { source, refresh = false, redirectCount = 0 } = {}) {
    const url = safeUrl(input);
    const cachePath = join(cacheDir, `${hash(url.href)}.json`);
    await ensureRobots(url, source, refresh);
    const cached = !refresh ? readCache(cachePath) : null;
    if (cached) return cached.body;
    if (redirectCount > 3) throw new Error(`Too many redirects while fetching ${url.href}`);

    await waitForHost(url, source);
    const response = await fetchImpl(url.href, {
      headers: {
        'User-Agent': userAgent,
        Accept:
          'application/json, application/atom+xml, application/rss+xml, application/xml, text/plain;q=0.9, */*;q=0.1',
      },
      redirect: 'manual',
      signal: AbortSignal.timeout(15000),
    });
    hostLastRequest.set(url.origin, now());

    if (REDIRECT_STATUSES.has(response.status)) {
      const location = response.headers.get('location');
      if (!location) throw new Error(`Redirect from ${url.href} did not include a Location header`);
      return requestText(new URL(location, url).href, { source, refresh, redirectCount: redirectCount + 1 });
    }
    if (response.status === 304 && cached) return cached.body;
    if (!response.ok) throw new Error(`HTTP ${response.status} while fetching ${url.href}`);

    const body = await response.text();
    if (Buffer.byteLength(body, 'utf8') > maxResponseBytes)
      throw new Error(`Response exceeded ${maxResponseBytes} bytes: ${url.href}`);
    writeCache(cachePath, {
      requested_url: url.href,
      fetched_at: new Date(now()).toISOString(),
      status: response.status,
      body,
    });
    return body;
  }

  /** @param {URL} url @param {Record<string, any> | undefined} source @param {boolean} refresh */
  async function ensureRobots(url, source, refresh) {
    if (robots.has(url.origin)) {
      enforceRobots(url, userAgent, robots.get(url.origin));
      return;
    }
    const robotsCachePath = join(cacheDir, 'robots', `${hash(url.origin)}.json`);
    const cached = !refresh ? readCache(robotsCachePath) : null;
    const robotsAge = cached ? now() - Date.parse(cached.fetched_at) : Number.POSITIVE_INFINITY;
    if (cached && robotsAge >= 0 && robotsAge < 24 * 60 * 60 * 1000) {
      robots.set(url.origin, cached.rules);
      enforceRobots(url, userAgent, cached.rules);
      return;
    }
    const robotsUrl = new URL('/robots.txt', url.origin);
    let rules;
    let status;
    try {
      const response = await fetchImpl(robotsUrl.href, {
        headers: { 'User-Agent': userAgent, Accept: 'text/plain' },
        redirect: 'manual',
        signal: AbortSignal.timeout(10000),
      });
      hostLastRequest.set(url.origin, now());
      status = response.status;
      if (response.status === 404) rules = [];
      else if (!response.ok) throw new Error(`robots.txt returned HTTP ${response.status}`);
      else rules = parseRobots(await response.text());
    } catch (error) {
      const decision = source?.robots_decision ?? 'fail closed';
      throw new Error(`Cannot verify robots.txt for ${url.origin} (${decision})`, { cause: error });
    }
    robots.set(url.origin, rules);
    writeCache(robotsCachePath, { origin: url.origin, fetched_at: new Date(now()).toISOString(), status, rules });
    enforceRobots(url, userAgent, rules);
  }

  /** @param {URL} url @param {Record<string, any>} [source] */
  async function waitForHost(url, source) {
    const configuredMs = Math.max(0, Number(source?.rate_limit_ms ?? 1000));
    const product = userAgent.split(/[\s/]/, 1)[0].toLowerCase();
    const groups = robots.get(url.origin) ?? [];
    const exact = groups.filter((group) => group.agents.some((agent) => agent !== '*' && product.includes(agent)));
    const selected = exact.length ? exact : groups.filter((group) => group.agents.includes('*'));
    const crawlDelay = Math.max(0, ...selected.map((group) => group.crawlDelayMs));
    const interval = Math.max(configuredMs, crawlDelay);
    const last = hostLastRequest.get(url.origin);
    if (last !== undefined) {
      const remaining = interval - (now() - last);
      if (remaining > 0) await sleep(remaining);
    }
  }

  return { requestText };
}

/** Groups robots.txt directives and selects the most-specific matching user-agent group. */
export function parseRobots(text) {
  const groups = [];
  let current = null;
  let sawRule = false;
  for (const rawLine of String(text).split(/\r?\n/)) {
    const line = rawLine.split('#', 1)[0].trim();
    if (!line) continue;
    const separator = line.indexOf(':');
    if (separator < 0) continue;
    const key = line.slice(0, separator).trim().toLowerCase();
    const value = line.slice(separator + 1).trim();
    if (key === 'user-agent') {
      if (!current || sawRule) {
        current = { agents: [], rules: [], crawlDelayMs: 0 };
        groups.push(current);
        sawRule = false;
      }
      current.agents.push(value.toLowerCase());
    } else if (current && ['allow', 'disallow'].includes(key)) {
      if (value) current.rules.push({ type: key, path: value });
      sawRule = true;
    } else if (current && key === 'crawl-delay' && Number.isFinite(Number(value))) {
      current.crawlDelayMs = Math.max(current.crawlDelayMs, Number(value) * 1000);
      sawRule = true;
    }
  }
  return groups;
}

/** Applies the longest matching robots rule; Allow wins a same-length tie. */
export function enforceRobots(url, userAgent, groups) {
  const product = userAgent.split(/[\s/]/, 1)[0].toLowerCase();
  const exact = groups.filter((group) => group.agents.some((agent) => agent !== '*' && product.includes(agent)));
  const selected = exact.length ? exact : groups.filter((group) => group.agents.includes('*'));
  const allRules = selected.flatMap((group) => group.rules);
  const path = `${url.pathname}${url.search}`;
  const matches = allRules
    .map((rule) => ({ ...rule, specificity: rule.path.replace(/\*|\$$/g, '').length, regex: robotsPattern(rule.path) }))
    .filter((rule) => rule.regex.test(path))
    .sort((a, b) => b.specificity - a.specificity || (a.type === 'allow' ? -1 : 1));
  if (matches[0]?.type === 'disallow') throw new Error(`robots.txt disallows ${url.origin}${path}`);
}

function robotsPattern(pattern) {
  const end = pattern.endsWith('$');
  const escaped = pattern
    .slice(0, end ? -1 : undefined)
    .replace(/[.+?^${}()|[\]\\]/g, '\\$&')
    .replace(/\*/g, '.*');
  return new RegExp(`^${escaped}${end ? '$' : ''}`);
}

function safeUrl(input) {
  let url;
  try {
    url = new URL(input);
  } catch {
    throw new Error(`Invalid URL: ${input}`);
  }
  if (!['http:', 'https:'].includes(url.protocol)) throw new Error(`Only HTTP(S) URLs are allowed: ${url.href}`);
  if (url.username || url.password) throw new Error('Credentials in source URLs are not allowed');
  return url;
}

function readCache(path) {
  try {
    return JSON.parse(readFileSync(path, 'utf8'));
  } catch {
    return null;
  }
}

function writeCache(path, value) {
  mkdirSync(dirname(path), { recursive: true });
  writeFileSync(path, JSON.stringify(value));
}

function hash(value) {
  return createHash('sha256').update(value).digest('hex');
}
