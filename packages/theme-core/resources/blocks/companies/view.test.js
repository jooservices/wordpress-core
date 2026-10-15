import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { month, range, renderCompany } from './view.js';
import './view.js';

const render = (html) =>
  document.body.replaceChildren(...new DOMParser().parseFromString(html, 'text/html').body.childNodes);

const markup = `
<section class="joo-companies" id="companies">
  <h2 class="joo-block-title">Companies</h2>
  <joo-companies data-per-page="2" data-page="1" data-total-pages="3" data-endpoint="https://x.test/wp-json/joo/v1/companies">
    <ol class="joo-companies__list"><li class="joo-company"><p class="joo-company__desc">old</p></li></ol>
    <nav class="joo-companies__pager">
      <a rel="prev" href="?joo_companies_page=0#companies" hidden>←</a>
      <a href="?joo_companies_page=1#companies" aria-current="page">1</a>
      <a href="?joo_companies_page=2#companies">2</a>
      <a href="?joo_companies_page=3#companies">3</a>
      <a rel="next" href="?joo_companies_page=2#companies">→</a>
    </nav>
  </joo-companies>
</section>`;

const company = (n) => ({
  name: `Co ${n}`,
  role: 'Engineer',
  from: '2020-03',
  to: '',
  description: `desc ${n}`,
  url: 'https://co.test',
});

beforeEach(() => {
  document.documentElement.lang = 'en';
  render(markup);
});

afterEach(() => vi.restoreAllMocks());

const click = (selector) => {
  const event = new MouseEvent('click', { bubbles: true, cancelable: true });
  document.querySelector(selector).dispatchEvent(event);
  return event;
};
const flush = () => new Promise((resolve) => setTimeout(resolve, 0));

describe('companies pagination', () => {
  it('formats months and ranges', () => {
    expect(month('2022-03')).toBe('Mar 2022');
    expect(month('2022-13')).toBe('');
    expect(range('2022-03', '2025-06')).toBe('Mar 2022 – Jun 2025');
    expect(range('2022-03', '')).toBe('Mar 2022 – Present');
  });

  it('builds items with textContent and only https links', () => {
    const item = renderCompany({ ...company(1), name: '<b>x</b>', url: 'javascript:alert(1)' }, true);
    expect(item.querySelector('h3').textContent).toBe('<b>x</b>');
    expect(item.querySelector('a')).toBeNull();
    expect(item.querySelector('b')).toBeNull();
    const linked = renderCompany(company(2), false);
    expect(linked.querySelector('a').href).toBe('https://co.test/');
    expect(linked.querySelector('.joo-company__desc')).toBeNull();
  });

  it('loads a page in place without navigating, updates the pager, announces and moves focus', async () => {
    globalThis.fetch = vi.fn().mockResolvedValue({ ok: true, json: async () => [company(3), company(4)] });
    const event = click('.joo-companies__pager a[href*="=2"]');
    await flush();
    expect(event.defaultPrevented).toBe(true);
    expect(fetch.mock.calls[0][0]).toBe('https://x.test/wp-json/joo/v1/companies?page=2&per_page=2&exclude_pinned=1');
    const names = [...document.querySelectorAll('.joo-company__name')].map((n) => n.textContent);
    expect(names).toEqual(['Co 3', 'Co 4']);
    expect(document.querySelector('[aria-current="page"]').textContent).toBe('2');
    expect(document.querySelector('[role="status"]').textContent).toBe('Page 2 of 3');
    expect(document.activeElement).toBe(document.querySelector('.joo-block-title'));
    expect(document.querySelector('joo-companies').dataset.page).toBe('2');
    expect(document.querySelector('a[rel="prev"]').hidden).toBe(false);
    expect(document.querySelector('a[rel="next"]').getAttribute('href')).toBe('?joo_companies_page=3#companies');
  });

  it('serves a visited page from memory', async () => {
    globalThis.fetch = vi.fn().mockResolvedValue({ ok: true, json: async () => [company(3)] });
    click('.joo-companies__pager a[href*="=2"]');
    await flush();
    click('.joo-companies__pager a[href*="=1"]');
    await flush();
    click('.joo-companies__pager a[href*="=2"]');
    await flush();
    expect(fetch).toHaveBeenCalledTimes(2);
  });

  it('shows an error with retry and keeps the old content', async () => {
    globalThis.fetch = vi
      .fn()
      .mockResolvedValueOnce({ ok: false, status: 500 })
      .mockResolvedValueOnce({ ok: true, json: async () => [company(9)] });
    click('.joo-companies__pager a[href*="=3"]');
    await flush();
    expect(document.querySelector('.joo-companies__error')).not.toBeNull();
    expect(document.querySelector('.joo-company__desc').textContent).toBe('old');
    document.querySelector('.joo-companies__error button').click();
    await flush();
    expect(document.querySelector('.joo-companies__error')).toBeNull();
    expect(document.querySelector('.joo-company__name').textContent).toBe('Co 9');
  });

  it('aborts the previous request when another page is chosen', async () => {
    const signals = [];
    globalThis.fetch = vi.fn((_url, options) => {
      signals.push(options.signal);
      return new Promise(() => {});
    });
    click('.joo-companies__pager a[href*="=2"]');
    click('.joo-companies__pager a[href*="=3"]');
    expect(signals[0].aborted).toBe(true);
    expect(signals[1].aborted).toBe(false);
  });

  it('leaves modified clicks and unknown links to the browser', async () => {
    globalThis.fetch = vi.fn();
    const link = document.querySelector('.joo-companies__pager a[href*="=2"]');
    const event = new MouseEvent('click', { bubbles: true, cancelable: true, ctrlKey: true });
    link.dispatchEvent(event);
    expect(event.defaultPrevented).toBe(false);
    expect(fetch).not.toHaveBeenCalled();
  });
});
