// <joo-terminal>: the dock. It only toggles the panel; the engine, the UI and the FS JSON are fetched on the first open.
import { config, define, load, restUrl, t } from 'joo-runtime';

const isTyping = (el) => el instanceof Element && (el.matches('input, textarea, select') || el.isContentEditable);

export class JooTerminal extends HTMLElement {
  connectedCallback() {
    this.tab = this.querySelector('.joo-term__tab');
    this.panel = this.querySelector('.joo-term__panel');
    if (!this.tab || !this.panel) return;
    this.tab.hidden = false;
    this.tab.addEventListener('click', () => this.toggle());
    this.onKey = (event) => {
      if (event.key === '`' && !isTyping(document.activeElement) && !event.ctrlKey && !event.metaKey && !event.altKey) {
        event.preventDefault();
        this.toggle();
      } else if (event.key === 'Escape' && this.isOpen() && this.contains(document.activeElement)) {
        this.close();
      }
    };
    document.addEventListener('keydown', this.onKey);
    this.onViewport = () => {
      const height = window.visualViewport?.height;
      if (height) this.style.setProperty('--joo-term-vh', `${Math.round(height)}px`);
    };
    window.visualViewport?.addEventListener('resize', this.onViewport);
    if (this.dataset.openDefault === '1') this.open();
  }

  disconnectedCallback() {
    document.removeEventListener('keydown', this.onKey);
    window.visualViewport?.removeEventListener('resize', this.onViewport);
    this.api?.destroy?.();
    this.controller?.abort();
  }

  isOpen() {
    return this.dataset.state === 'open';
  }

  toggle() {
    if (this.isOpen()) this.close();
    else this.open();
  }

  open() {
    if (!this.isOpen()) {
      const active = document.activeElement;
      this.returnFocusTo =
        active instanceof HTMLElement && active !== document.body && !this.contains(active) ? active : this.tab;
    }
    this.dataset.state = 'open';
    this.panel.hidden = false;
    this.tab.setAttribute('aria-expanded', 'true');
    if (!this.started) {
      this.started = true;
      this.start();
    } else this.api?.focus();
  }

  close() {
    this.dataset.state = 'closed';
    this.panel.hidden = true;
    this.tab.setAttribute('aria-expanded', 'false');
    const target = this.returnFocusTo;
    this.returnFocusTo = null;
    if (target instanceof HTMLElement && target.isConnected && !target.closest('[hidden]')) target.focus();
    else this.tab.focus();
  }

  /** Loads the terminal module and the FS JSON once. */
  async start() {
    const loading = document.createElement('p');
    loading.className = 'joo-term__line joo-term__dim';
    loading.textContent = t('terminal.loading', 'loading file system…');
    loading.setAttribute('role', 'status');
    loading.setAttribute('aria-live', 'polite');
    loading.setAttribute('aria-atomic', 'true');
    loading.tabIndex = -1;
    this.panel.replaceChildren(loading);
    loading.focus();
    this.controller = new AbortController();
    try {
      const lang = config().lang ?? '';
      const [module, data, extra] = await Promise.all([
        load('terminal'),
        this.fetchFs(lang).catch(() => null),
        this.extraCommands(),
      ]);
      const failed = data === null;
      this.api = module.mountTerminal(this.panel, {
        shell: module.createShell(failed ? { nodes: [], site: {} } : data, extra),
        onClose: () => this.close(),
        shortcuts: config().terminalShortcuts,
      });
      if (failed) this.api.run('echo fs: failed to load (close and reopen to retry); help still works');
      if (this.isOpen()) this.api.focus();
    } catch {
      loading.textContent = t('terminal.failed', 'The terminal could not be loaded.');
      this.started = false;
    }
  }

  async fetchFs(lang) {
    const url = `${restUrl('terminal/fs')}${lang ? `?lang=${encodeURIComponent(lang)}` : ''}`;
    const response = await fetch(url, { signal: this.controller.signal, headers: { Accept: 'application/json' } });
    if (!response.ok) throw new Error(String(response.status));
    return response.json();
  }

  /** Extra command modules registered by a theme (same-origin URLs only). */
  async extraCommands() {
    const urls = Array.isArray(config().terminalCommands) ? config().terminalCommands : [];
    const commands = [];
    for (const url of urls) {
      try {
        const parsed = new URL(url, window.location.href);
        if (parsed.origin !== window.location.origin) continue;
        const module = await import(/* @vite-ignore */ parsed.href);
        commands.push(...(Array.isArray(module.default) ? module.default : []));
      } catch {
        /* a broken theme command must not break the terminal */
      }
    }
    return commands;
  }
}

define('joo-terminal', JooTerminal);
