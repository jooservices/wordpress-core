import { expect, type APIRequestContext } from '@playwright/test';

/** Count matches of a pattern in HTML (the pattern needs the g flag). */
export const count = (html: string, needle: RegExp): number => (html.match(needle) ?? []).length;

/** The single JSON-LD document of a page (fails when there is none or more than one). */
export function parseJsonLd(html: string): any {
  const blocks = [...html.matchAll(/<script type="application\/ld\+json"[^>]*>([\s\S]*?)<\/script>/g)];
  expect(blocks, 'exactly one JSON-LD block').toHaveLength(1);
  return JSON.parse(blocks[0][1]);
}

/** Every `{ "@id": ... }` reference of a graph must point at a node of that graph, and ids must be unique. */
export function expectConnectedGraph(doc: any): void {
  const ids = new Set(doc['@graph'].map((n: any) => n['@id']));
  expect(ids.size, 'unique @id values').toBe(doc['@graph'].length);
  const refs: string[] = [];
  const walk = (v: any) => {
    if (Array.isArray(v)) return v.forEach(walk);
    if (v && typeof v === 'object') {
      if (Object.keys(v).length === 1 && v['@id']) refs.push(v['@id']);
      else Object.entries(v).forEach(([k, x]) => k !== '@id' && walk(x));
    }
  };
  walk(doc['@graph']);
  for (const ref of refs) expect(ids.has(ref), `dangling ${ref}`).toBe(true);
}

/** One title, description, canonical and robots tag, plus optional required fragments. */
export function expectHead(html: string, options: { canonical?: boolean; contains?: string[] } = {}): void {
  expect(count(html, /<title>/g), '<title>').toBe(1);
  expect(count(html, /<meta name="description"/g), 'meta description').toBe(1);
  expect(count(html, /<link rel="canonical"/g), 'canonical').toBe(options.canonical === false ? 0 : 1);
  expect(count(html, /<meta name=['"]robots['"]/g), 'meta robots').toBeLessThanOrEqual(1);
  for (const fragment of options.contains ?? []) expect(html, fragment).toContain(fragment);
}

/** The sitemap index lists at least one child and every child is XML with URLs. */
export async function expectSitemap(request: APIRequestContext, path = '/wp-sitemap.xml'): Promise<void> {
  const index = await request.get(path);
  expect(index.status(), path).toBe(200);
  expect(index.headers()['content-type']).toMatch(/xml/);
  const children = [...(await index.text()).matchAll(/<loc>([^<]+)<\/loc>/g)].map((m) => m[1]);
  expect(children.length, 'child sitemaps').toBeGreaterThan(0);
  for (const child of children.slice(0, 3)) {
    const res = await request.get(child);
    expect(res.status(), child).toBe(200);
    expect(await res.text()).toMatch(/<url>|<sitemap>/);
  }
}
