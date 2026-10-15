// <joo-companies>: swaps companies pages in place (no reload, URL unchanged). The pager links are real URLs, so
// everything also works without JavaScript. DOM nodes are built with textContent, never innerHTML.
import { define, restUrl, t } from 'joo-runtime';

const MONTH_RE = /^(\d{4})-(0[1-9]|1[0-2])$/;

/**
 * "2022-03" -> "Mar 2022" in the page language.
 * @param {string} value Month value.
 * @returns {string} Label (empty for invalid input).
 */
export function month(value) {
  const match = MONTH_RE.exec(value ?? '');
  if (!match) return '';
  const date = new Date(Date.UTC(Number(match[1]), Number(match[2]) - 1, 1));
  return new Intl.DateTimeFormat(document.documentElement.lang || undefined, {
    month: 'short',
    year: 'numeric',
    timeZone: 'UTC',
  }).format(date);
}

/**
 * "Mar 2022 – Jun 2025" or "Mar 2022 – Present".
 * @param {string} from Start month.
 * @param {string} to End month (empty = present).
 * @returns {string} Range label.
 */
export function range(from, to) {
  const start = month(from);
  const end = to ? month(to) : t('present', 'Present');
  return start ? `${start} – ${end}` : end;
}

const el = (tag, className, text) => {
  const node = document.createElement(tag);
  if (className) node.className = className;
  if (text !== undefined) node.textContent = text;
  return node;
};

/**
 * Builds one list item from a REST company.
 * @param {object} company Company from /joo/v1/companies.
 * @param {boolean} showDesc Whether to show the description.
 * @returns {HTMLLIElement} The item.
 */
export function renderCompany(company, showDesc) {
  const item = el('li', 'joo-company');
  const row = el('div', 'joo-company__row');
  const name = el('h3', 'joo-company__name');
  if (/^https:\/\//.test(company.url ?? '')) {
    const link = el('a', '', company.name);
    link.href = company.url;
    link.rel = 'noopener';
    name.append(link);
  } else name.textContent = company.name;
  row.append(name, el('span', 'joo-company__role', company.role));
  item.append(row, el('p', 'joo-company__range', range(company.from, company.to)));
  if (showDesc && company.description) item.append(el('p', 'joo-company__desc', company.description));
  return item;
}

export class JooCompanies extends HTMLElement {
  connectedCallback() {
    this.list = this.querySelector('.joo-companies__list');
    this.pager = this.querySelector('.joo-companies__pager');
    this.cache = new Map();
    this.page = Number(this.dataset.page) || 1;
    this.total = Number(this.dataset.totalPages) || 1;
    this.per = Number(this.dataset.perPage) || 2;
    this.showDesc =
      Boolean(this.querySelector('.joo-company__desc')) ||
      this.closest('.joo-companies')?.querySelector('.joo-company__desc') !== null;
    this.status = el('p', 'screen-reader-text');
    this.status.setAttribute('role', 'status');
    this.append(this.status);
    this.addEventListener('click', this.onClick);
  }

  disconnectedCallback() {
    this.removeEventListener('click', this.onClick);
    this.controller?.abort();
  }

  onClick = (event) => {
    const link = event.target instanceof Element ? event.target.closest('.joo-companies__pager a') : null;
    if (!link || event.defaultPrevented || event.ctrlKey || event.metaKey || event.shiftKey) return;
    const match = /joo_companies_page=(\d+)/.exec(link.getAttribute('href') ?? '');
    if (!match) return;
    event.preventDefault();
    this.show(Number(match[1]));
  };

  /** Loads and shows a page. */
  async show(page) {
    if (page < 1 || page > this.total || page === this.page) return;
    this.controller?.abort();
    this.controller = new AbortController();
    try {
      let items = this.cache.get(page);
      if (!items) {
        const url = `${this.dataset.endpoint || restUrl('joo/v1/companies')}?page=${page}&per_page=${this.per}&exclude_pinned=1`;
        const response = await fetch(url, { signal: this.controller.signal, headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error(String(response.status));
        items = await response.json();
        this.cache.set(page, items);
      }
      this.list.replaceChildren(...items.map((company) => renderCompany(company, this.showDesc)));
      this.page = page;
      this.dataset.page = String(page);
      this.updatePager();
      this.status.textContent = t('page_of', 'Page {page} of {total}')
        .replace('{page}', String(page))
        .replace('{total}', String(this.total));
      this.error?.remove();
      this.error = null;
      const heading = this.closest('.joo-companies')?.querySelector('.joo-block-title');
      if (heading) {
        heading.setAttribute('tabindex', '-1');
        heading.focus({ preventScroll: true });
      }
    } catch (error) {
      if (error?.name === 'AbortError') return;
      this.showError(page);
    }
  }

  updatePager() {
    this.pager?.querySelectorAll('a').forEach((a) => {
      const n = /joo_companies_page=(\d+)/.exec(a.getAttribute('href') ?? '')?.[1];
      const isNumber = /^\d+$/.test(a.textContent.trim());
      if (isNumber) {
        if (Number(n) === this.page) a.setAttribute('aria-current', 'page');
        else a.removeAttribute('aria-current');
      } else if (a.rel === 'next' || a.rel === 'prev') {
        const target = a.rel === 'next' ? this.page + 1 : this.page - 1;
        a.hidden = target < 1 || target > this.total;
        a.setAttribute('href', `?joo_companies_page=${target}#companies`);
      }
    });
  }

  showError(page) {
    this.error?.remove();
    this.error = el('p', 'joo-companies__error', t('load_failed', 'Could not load this page.'));
    const retry = el('button', '', t('retry', 'Retry'));
    retry.type = 'button';
    retry.addEventListener('click', () => this.show(page));
    this.error.append(' ', retry);
    this.list.after(this.error);
  }
}

define('joo-companies', JooCompanies);
