// <joo-search>: opens the search dialog from the link or the "/" shortcut; Esc closes (native dialog behaviour).
import { define } from 'joo-runtime';

/**
 * Whether a keyboard event should open the search (the "/" key outside of form fields).
 * @param {KeyboardEvent} event Event.
 * @returns {boolean} True when it should open.
 */
export function shouldOpen(event) {
  if (event.key !== '/' || event.ctrlKey || event.metaKey || event.altKey) return false;
  const target = event.target;
  const tag = target?.tagName;
  return !(tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || target?.isContentEditable);
}

export class JooSearch extends HTMLElement {
  connectedCallback() {
    this.dialog = this.querySelector('dialog');
    this.link = this.querySelector('.joo-search__link');
    if (!this.dialog || typeof this.dialog.showModal !== 'function') return;
    this.link?.addEventListener('click', (event) => {
      event.preventDefault();
      this.open();
    });
    this.querySelector('.joo-search__close')?.addEventListener('click', () => this.dialog.close());
    this.dialog.addEventListener('click', (event) => {
      if (event.target === this.dialog) this.dialog.close();
    });
    this.onKey = (event) => {
      if (shouldOpen(event)) {
        event.preventDefault();
        this.open();
      }
    };
    document.addEventListener('keydown', this.onKey);
  }

  disconnectedCallback() {
    document.removeEventListener('keydown', this.onKey);
  }

  open() {
    if (!this.dialog.open) this.dialog.showModal();
    this.dialog.querySelector('input[type="search"]')?.focus();
  }
}

define('joo-search', JooSearch);
