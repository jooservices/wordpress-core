// <joo-theme-toggle>: switches data-theme on <html>, remembers the choice and follows the system until the user chooses.
import { define } from 'joo-runtime';

const KEY = 'joo-theme';

/** @returns {string|null} Stored choice. */
export function stored() {
  try {
    const value = localStorage.getItem(KEY);
    return value === 'light' || value === 'dark' ? value : null;
  } catch {
    return null;
  }
}

/** @returns {string} Current scheme of the page. */
export function current() {
  return document.documentElement.dataset.theme === 'light' ? 'light' : 'dark';
}

/**
 * Applies a scheme to the page and keeps the color-scheme meta tag in sync.
 * @param {string} scheme light or dark.
 */
export function apply(scheme) {
  document.documentElement.dataset.theme = scheme;
  document.querySelector('meta[name="color-scheme"]')?.setAttribute('content', scheme);
}

export class JooThemeToggle extends HTMLElement {
  connectedCallback() {
    this.button = this.querySelector('button');
    if (!this.button) return;
    this.button.hidden = false;
    this.sync();
    this.button.addEventListener('click', () => {
      const next = current() === 'dark' ? 'light' : 'dark';
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
      if (value !== 'light' && value !== 'dark') return;
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
    this.button.setAttribute('aria-pressed', String(current() === 'dark'));
  }
}

define('joo-theme-toggle', JooThemeToggle);
