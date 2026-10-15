import { test, expect } from '@playwright/test';

import { count, expectConnectedGraph, expectHead, parseJsonLd } from '../../../../packages/test-kit/e2e/seo';

// Theme-agnostic SEO assertions against the running Docker WordPress (real HTTP, real headers).
const jsonLd = parseJsonLd;

test.describe('seo over HTTP', () => {
  test('home has one title, description, canonical, robots and a valid graph', async ({ request }) => {
    const res = await request.get('/');
    expect(res.status()).toBe(200);
    const html = await res.text();
    expect(count(html, /<title>/g)).toBe(1);
    expect(count(html, /<meta name="description"/g)).toBe(1);
    expect(count(html, /<link rel="canonical"/g)).toBe(1);
    expect(count(html, /<meta name=['"]robots['"]/g)).toBe(1);
    expect(html).toContain('max-image-preview:large');
    const doc = jsonLd(html);
    const types = doc['@graph'].map((n: any) => n['@type']);
    expect(types).toEqual(expect.arrayContaining(['WebSite', 'Organization']));
  });

  test('a post exposes a connected BlogPosting graph, Open Graph and Twitter tags', async ({ request }) => {
    const posts = await (await request.get('/wp-json/wp/v2/posts?per_page=1&_fields=link')).json();
    test.skip(posts.length === 0, 'no published post');
    const res = await request.get(posts[0].link);
    expect(res.status()).toBe(200);
    const html = await res.text();
    for (const tag of ['og:title', 'og:description', 'og:url', 'og:type', 'twitter:card', 'twitter:title']) {
      expect(html, tag).toContain(tag);
    }
    const doc = jsonLd(html);
    expectConnectedGraph(doc);
    const article = doc['@graph'].find((n: any) => n['@type'] === 'BlogPosting');
    expect(article).toBeTruthy();
    expect(article.headline).toBeTruthy();
    expect(article.wordCount).toBeGreaterThan(0);
  });

  test('a missing URL is a real 404 with noindex and no canonical', async ({ request }) => {
    const res = await request.get('/definitely-missing-url/');
    expect(res.status()).toBe(404);
    expect(res.headers()['x-robots-tag']).toMatch(/noindex/);
    const html = await res.text();
    expect(html).toMatch(/noindex/);
    expect(html).not.toContain('rel="canonical"');
  });

  test('search is noindex and a blank search redirects to home with 301', async ({ request }) => {
    const search = await request.get('/?s=hello');
    expect(search.status()).toBe(200);
    expect(search.headers()['x-robots-tag']).toMatch(/noindex/);
    const blank = await request.get('/?s=', { maxRedirects: 0 });
    expect(blank.status()).toBe(301);
    expect(blank.headers().location).toMatch(/\/$/);
  });

  test('sitemap and robots.txt are published', async ({ request }) => {
    const index = await request.get('/wp-sitemap.xml');
    expect(index.status()).toBe(200);
    expect(await index.text()).toContain('<sitemapindex');
    const robots = await (await request.get('/robots.txt')).text();
    expect(count(robots, /^Sitemap:/gim)).toBe(1);
  });
});
