// <joo-theme-toggle>: switches data-theme on <html>, remembers the choice and follows the system until the user chooses.
import { define } from 'joo-runtime';

const KEY = 'joo-theme';

/** @returns {string[]} Schemes supported by the active theme. */
export function schemes() {
  const value = document.querySelector('joo-theme-toggle')?.dataset.schemes;
  const allowed = typeof value === 'string' ? value.split(',') : ['dark', 'light'];
  const safe = allowed.filter((scheme) => /^[a-z][a-z0-9-]{0,31}$/.test(scheme));
  return safe.length ? safe : ['dark'];
}

/** @returns {string|null} Stored choice. */
export function stored() {
  try {
    const value = localStorage.getItem(KEY);
    return schemes().includes(value) ? value : null;
  } catch {
    return null;
  }
}

/** @returns {string} Current scheme of the page. */
export function current() {
  const value = document.documentElement.dataset.theme;
  return schemes().includes(value) ? value : schemes()[0];
}

/**
 * Applies a scheme to the page and keeps the color-scheme meta tag in sync.
 * @param {string} scheme A name in the active theme's design.colorSchemes list.
 */
export function apply(scheme) {
  if (!schemes().includes(scheme)) return;
  document.documentElement.dataset.theme = scheme;
  document.querySelector('meta[name="color-scheme"]')?.setAttribute('content', 'light dark');
}

export class JooThemeToggle extends HTMLElement {
  connectedCallback() {
    this.button = this.querySelector('button');
    if (!this.button) return;
    this.button.hidden = false;
    this.sync();
    this.button.addEventListener('click', () => {
      const available = schemes();
      const next = available[(available.indexOf(current()) + 1) % available.length];
      apply(next);
      try {
        localStorage.setItem(KEY, next);
      } catch {
        /* private mode: the choice lasts for this page only */
      }
      this.sync();
    });
    this.media = window.matchMedia?.('(prefers-color-scheme: light)');
    this.onSystem = (event) => {
      if (stored()) return;
      apply(event.matches ? 'light' : 'dark');
      this.sync();
    };
    this.media?.addEventListener?.('change', this.onSystem);
    // The terminal's `theme` command asks for a scheme through this event.
    this.onRequest = (event) => {
      const value = event.detail?.value;
      if (!schemes().includes(value)) return;
      apply(value);
      try {
        localStorage.setItem(KEY, value);
      } catch {
        /* private mode */
      }
      this.sync();
    };
    window.addEventListener('joo:theme', this.onRequest);
  }

  disconnectedCallback() {
    this.media?.removeEventListener?.('change', this.onSystem);
    window.removeEventListener('joo:theme', this.onRequest);
  }

  sync() {
    const colorScheme = window.getComputedStyle(document.documentElement).colorScheme;
    this.button.dataset.colorScheme = colorScheme.includes('light') && !colorScheme.includes('dark') ? 'light' : 'dark';
  }
}

define('joo-theme-toggle', JooThemeToggle);
