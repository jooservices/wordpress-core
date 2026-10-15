import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import data from '../../js/terminal/__fixtures__/demo-fs.json';

const state = { extra: [] };

vi.mock('joo-runtime', async () => {
  const real = await vi.importActual('../../js/runtime.js');
  return {
    ...real,
    config: () => ({
      lang: 'en',
      rest: 'https://x.test/wp-json/joo/v1/',
      i18n: {},
      terminalCommands: state.extra,
      assets: {},
    }),
    restUrl: (path) => `https://x.test/wp-json/joo/v1/${path}`,
    load: () => import('../../js/terminal.js'),
  };
});

const render = (html) =>
  document.body.replaceChildren(...new DOMParser().parseFromString(html, 'text/html').body.childNodes);

const markup = (open = '') => `
<joo-terminal class="joo-term" data-state="closed"${open}>
  <button type="button" class="joo-term__tab" aria-expanded="false" aria-controls="joo-term-panel" hidden>tab</button>
  <div class="joo-term__panel" id="joo-term-panel" hidden></div>
</joo-terminal>`;

const flush = () => new Promise((resolve) => setTimeout(resolve, 150));
const tab = () => document.querySelector('.joo-term__tab');
const panel = () => document.querySelector('.joo-term__panel');

beforeEach(async () => {
  state.extra = [];
  globalThis.fetch = vi.fn().mockResolvedValue({ ok: true, json: async () => data });
  await import('./view.js');
});

afterEach(() => document.body.replaceChildren());

describe('terminal dock', () => {
  it('shows the tab only with JavaScript and stays closed', () => {
    render(markup());
    expect(tab().hidden).toBe(false);
    expect(panel().hidden).toBe(true);
    expect(document.querySelector('joo-terminal').dataset.state).toBe('closed');
    expect(fetch).not.toHaveBeenCalled();
  });

  it('loads the terminal and the file system once, on the first open', async () => {
    render(markup());
    tab().click();
    await flush();
    expect(fetch).toHaveBeenCalledTimes(1);
    expect(fetch.mock.calls[0][0]).toBe('https://x.test/wp-json/joo/v1/terminal/fs?lang=en');
    expect(tab().getAttribute('aria-expanded')).toBe('true');
    expect(panel().hidden).toBe(false);
    expect(document.querySelector('.joo-term__input')).not.toBeNull();
    expect(document.activeElement).toBe(document.querySelector('.joo-term__input'));
    tab().click();
    tab().click();
    await flush();
    expect(fetch).toHaveBeenCalledTimes(1);
  });

  it('sends no request when commands are typed', async () => {
    render(markup());
    tab().click();
    await flush();
    const input = document.querySelector('.joo-term__input');
    for (const cmd of [
      'ls',
      'cd ai',
      'cat wordpress-mcp-v1.md',
      'tree',
      'grep -ri docker .',
      'curl /ai',
      'help',
      'neofetch',
      'date',
      'whoami',
      'history',
    ]) {
      input.value = cmd;
      document.querySelector('.joo-term__form').dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
    }
    expect(fetch).toHaveBeenCalledTimes(1);
  });

  it('toggles with the backtick key unless typing and closes with Escape returning focus to the tab', async () => {
    render(markup());
    document.dispatchEvent(new KeyboardEvent('keydown', { key: '`', bubbles: true, cancelable: true }));
    await flush();
    expect(document.querySelector('joo-terminal').dataset.state).toBe('open');
    const input = document.querySelector('.joo-term__input');
    input.focus();
    input.dispatchEvent(new KeyboardEvent('keydown', { key: '`', bubbles: true }));
    expect(document.querySelector('joo-terminal').dataset.state).toBe('open');
    input.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
    expect(document.querySelector('joo-terminal').dataset.state).toBe('closed');
    expect(panel().hidden).toBe(true);
    expect(document.activeElement).toBe(tab());
  });

  it('keeps working when the file system cannot be loaded', async () => {
    globalThis.fetch = vi.fn().mockResolvedValue({ ok: false, status: 500 });
    render(markup());
    tab().click();
    await flush();
    expect(panel().textContent).toContain('fs: failed to load');
    expect(document.querySelector('.joo-term__input')).not.toBeNull();
  });

  it('opens by default when asked', async () => {
    render(markup(' data-open-default="1"'));
    await flush();
    expect(document.querySelector('joo-terminal').dataset.state).toBe('open');
  });

  it('ignores extra command modules from other origins', async () => {
    state.extra = ['https://evil.example/cmd.js'];
    render(markup());
    tab().click();
    await flush();
    expect(document.querySelector('.joo-term__input')).not.toBeNull();
  });
});
