// <joo-lazy>: loads a deferred fragment when it nears the viewport. Server markup (see Lazy\FragmentLazy):
//   <joo-lazy name data-src><div class="joo-lazy__placeholder" aria-busy="true">…</div><noscript>fallback HTML</noscript></joo-lazy>
import { define, inView, t } from 'joo-runtime';

const TIMEOUT_MS = 8000;
const MAX_ATTEMPTS = 3;

/**
 * Parse server HTML into a fragment without executing anything: scripts, embedded objects and inline event
 * handlers are removed even though the endpoint only returns escaped, server-built markup.
 * @param {string} html Markup.
 * @returns {DocumentFragment} Safe fragment.
 */
export function safeFragment(html) {
  const fragment = document.createRange().createContextualFragment(html);
  fragment.querySelectorAll('script, object, embed, iframe[srcdoc]').forEach((node) => node.remove());
  fragment.querySelectorAll('*').forEach((element) => {
    for (const attribute of Array.from(element.attributes)) {
      const unsafeUrl =
        /^(href|src|action|formaction)$/i.test(attribute.name) && /^\s*javascript:/i.test(attribute.value);
      if (/^on/i.test(attribute.name) || unsafeUrl) element.removeAttribute(attribute.name);
    }
  });
  return fragment;
}

class JooLazy extends HTMLElement {
  connectedCallback() {
    this.state = 'idle';
    this.querySelector('[data-joo-load]')?.addEventListener('click', () => this.load());
    const deepLink = this.getAttribute('name') === 'comments' && /^#comment-\d+$/.test(window.location.hash);
    if (deepLink) this.load();
    else this.stopWatching = inView(this, () => this.load(), { rootMargin: '400px 0px' });
  }

  disconnectedCallback() {
    this.stopWatching?.();
    this.controller?.abort();
  }

  /**
   * Fetch and insert the fragment (retries with exponential backoff, then falls back to the inline copy).
   * @param {number} [attempt] Attempt counter.
   * @returns {Promise<void>} Resolves when finished.
   */
  async load(attempt = 0) {
    if (this.state === 'loading' || this.state === 'done') return;
    const src = this.getAttribute('data-src');
    if (!src) return;
    this.state = 'loading';
    this.controller = new AbortController();
    const timer = setTimeout(() => this.controller.abort(), TIMEOUT_MS);
    try {
      const response = await fetch(src, { signal: this.controller.signal, headers: { Accept: 'application/json' } });
      if (!response.ok) throw new Error(`HTTP ${response.status}`);
      if (!/json/i.test(response.headers.get('content-type') || '')) throw new Error('unexpected content type');
      const data = await response.json();
      if (typeof data.html !== 'string') throw new Error('invalid payload');
      this.replaceChildren(safeFragment(data.html));
      this.state = 'done';
      this.removeAttribute('aria-busy');
      this.dispatchEvent(new CustomEvent('joo:lazy-loaded', { bubbles: true }));
      if (this.getAttribute('name') === 'comments' && window.location.hash)
        document.querySelector(window.location.hash)?.scrollIntoView();
    } catch (error) {
      this.state = 'idle';
      if (error?.name === 'AbortError' && !this.isConnected) return;
      if (attempt + 1 < MAX_ATTEMPTS) {
        setTimeout(() => this.load(attempt + 1), 500 * 2 ** attempt);
      } else {
        this.fallback();
      }
    } finally {
      clearTimeout(timer);
    }
  }

  /** Show the inline copy (from <noscript>) or a retry button when the fetch keeps failing. */
  fallback() {
    const noscript = this.querySelector('noscript');
    if (noscript?.textContent?.trim()) {
      this.replaceChildren(safeFragment(noscript.textContent));
      this.state = 'done';
      this.removeAttribute('aria-busy');
      return;
    }
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'joo-lazy__retry';
    button.textContent = t('lazy.retry', 'Retry');
    button.addEventListener('click', () => this.load());
    this.replaceChildren(button);
  }
}

define('joo-lazy', JooLazy);
