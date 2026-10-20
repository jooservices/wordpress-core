// <joo-404>: the retry form checks a path against the embedded post index (no network) and shows "200 OK" with a link
// on an exact match, otherwise the closest posts.
import { define, t } from 'joo-runtime';

/** Last segment of a path without extension, lowercase. */
export function slugOf(path) {
  const last = String(path).split('?')[0].split('#')[0].split('/').filter(Boolean).pop() ?? '';
  return last.replace(/\.[a-z0-9]+$/i, '').toLowerCase();
}

/** Dice coefficient on bigrams. */
export function similarity(a, b) {
  if (a === b) return 1;
  if (a.length < 2 || b.length < 2) return 0;
  const grams = (s) => {
    const map = new Map();
    for (let i = 0; i < s.length - 1; i += 1) map.set(s.slice(i, i + 2), (map.get(s.slice(i, i + 2)) ?? 0) + 1);
    return map;
  };
  const x = grams(a);
  const y = grams(b);
  let overlap = 0;
  for (const [gram, count] of x) overlap += Math.min(count, y.get(gram) ?? 0);
  return (2 * overlap) / (a.length - 1 + (b.length - 1));
}

/**
 * Finds an exact match or the closest posts.
 * @param {string} path Typed path.
 * @param {{s: string, t: string, u: string}[]} index Post index.
 * @returns {{exact: object|null, near: object[]}} Result.
 */
export function match(path, index) {
  const slug = slugOf(path);
  const exact = index.find((row) => row.s.toLowerCase() === slug) ?? null;
  const near = exact
    ? []
    : index
        .map((row) => ({ row, score: similarity(slug, row.s.toLowerCase()) }))
        .filter((x) => x.score > 0.3)
        .sort((a, b) => b.score - a.score)
        .slice(0, 3)
        .map((x) => x.row);
  return { exact, near };
}

export class Joo404 extends HTMLElement {
  connectedCallback() {
    const form = this.querySelector('.joo-404__retry');
    const script = this.querySelector('.joo-404__index');
    if (!form || !script) return;
    let index = [];
    try {
      index = JSON.parse(script.textContent ?? '[]');
    } catch {
      return;
    }
    form.hidden = false;
    form.addEventListener('submit', (event) => {
      event.preventDefault();
      const input = form.querySelector('input');
      this.show(form.querySelector('.joo-404__result'), match(input.value, index));
    });
  }

  show(target, { exact, near }) {
    target.replaceChildren();
    const anchor = (row) => {
      const link = document.createElement('a');
      link.href = row.u;
      link.textContent = row.t;
      return link;
    };
    /* eslint-disable joo/no-untranslated-string -- "HTTP/2 <code>" is protocol notation shown as a status line */
    if (exact) {
      target.append(document.createTextNode('HTTP/2 200 OK → '), anchor(exact));
    } else if (near.length) {
      target.append(document.createTextNode('HTTP/2 404 · ' + t('did_you_mean', 'Did you mean') + ' '));
      near.forEach((row, i) => target.append(...(i ? [document.createTextNode(' · ')] : []), anchor(row)));
    } else target.textContent = 'HTTP/2 404';
    /* eslint-enable joo/no-untranslated-string */
  }
}

define('joo-404', Joo404);
