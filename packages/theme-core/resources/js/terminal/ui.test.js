import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import data from './__fixtures__/demo-fs.json';
import { createShell, mountTerminal, safeUrl } from '../terminal.js';

let navigate;
let closed;
let api;

const panel = () => document.querySelector('.panel');
const input = () => document.querySelector('.joo-term__input');
const logText = () => document.querySelector('.joo-term__log').textContent;
const type = (value) => {
  input().value = value;
  document.querySelector('.joo-term__form').dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
};
const key = (init) => input().dispatchEvent(new KeyboardEvent('keydown', { bubbles: true, cancelable: true, ...init }));

const start = (fs = data, shortcuts) => {
  document.head.replaceChildren();
  document.body.replaceChildren(Object.assign(document.createElement('div'), { className: 'panel' }));
  navigate = vi.fn();
  closed = 0;
  api = mountTerminal(panel(), { shell: createShell(fs), onClose: () => (closed += 1), navigate, shortcuts });
};

beforeEach(() => start());
afterEach(() => {
  api?.destroy?.();
  vi.useRealTimers();
});

describe('terminal UI', () => {
  it('uses generic shortcuts by default and theme-provided shortcuts when present', () => {
    expect([...document.querySelectorAll('.joo-term__chip')].map((chip) => chip.textContent)).toEqual([
      'ls',
      'tree',
      'help',
      'find . -type f',
    ]);
    start(data, ['ls', 'grep -ri docker .']);
    expect([...document.querySelectorAll('.joo-term__chip')].map((chip) => chip.textContent)).toEqual([
      'ls',
      'grep -ri docker .',
    ]);
  });

  it('shows a banner, a prompt and the structure (log region, labelled input)', () => {
    expect(logText()).toContain('jooservices.com');
    expect(document.querySelector('.joo-term__prompt').textContent).toBe('viet@jooservices:~$');
    const log = document.querySelector('.joo-term__log');
    expect(log.getAttribute('role')).toBe('log');
    expect(log.getAttribute('aria-live')).toBe('off');
    expect(document.querySelector('.joo-term__status').getAttribute('aria-live')).toBe('polite');
    expect(document.querySelector('.joo-term__status').getAttribute('aria-atomic')).toBe('true');
    expect(document.querySelector('label[for="joo-term-input"]')).not.toBeNull();
    expect(document.querySelector('.joo-term__title').textContent).toBe('viet@jooservices:~');
  });

  it('runs commands from the form, echoes them and updates the prompt', () => {
    type('cd ai');
    expect(document.querySelector('.joo-term__prompt').textContent).toBe('viet@jooservices:~/ai$');
    type('ls');
    expect(logText()).toContain('prompt-caching.md');
    expect(input().value).toBe('');
    type('nonsense');
    expect(logText()).toContain('command not found');
  });

  it('runs a command when a clickable name or a chip is clicked', () => {
    api.run('ls');
    document.querySelector('.joo-term__cols .joo-term__cmd').click();
    expect(logText()).toContain('viet@jooservices:~$ cd /ai');
    document.querySelector('.joo-term__chip').click();
    expect(document.querySelectorAll('.joo-term__line').length).toBeGreaterThan(3);
  });

  it('completes with Tab and lists several candidates', () => {
    input().value = 'cat ai';
    key({ key: 'Tab' });
    expect(input().value).toBe('cat ai/');
    input().value = 'ls r';
    key({ key: 'Tab' });
    expect(logText()).toContain('README.md');
    expect(logText()).toContain('research-and-development/');
  });

  it('navigates the history with the arrow keys and clears with Ctrl+L', () => {
    type('pwd');
    type('date');
    key({ key: 'ArrowUp' });
    expect(input().value).toBe('date');
    key({ key: 'ArrowUp' });
    expect(input().value).toBe('pwd');
    key({ key: 'ArrowDown' });
    expect(input().value).toBe('date');
    key({ key: 'l', ctrlKey: true });
    expect(logText()).toBe('');
  });

  it('cancels the line with Ctrl+C and closes with Escape', () => {
    input().value = 'half typed';
    key({ key: 'c', ctrlKey: true });
    expect(input().value).toBe('');
    expect(logText()).toContain('half typed^C');
    key({ key: 'Escape' });
    expect(closed).toBe(1);
    document.querySelector('.joo-term__close').click();
    expect(closed).toBe(2);
  });

  it('applies clear and close effects', () => {
    type('echo hi');
    type('clear');
    expect(logText()).toBe('');
    type('exit');
    expect(closed).toBe(1);
  });

  it('announces concise output through a polite status without making the full log live', () => {
    vi.useFakeTimers();
    type('help');
    const status = document.querySelector('.joo-term__status');
    expect(status.textContent).toBe('');
    vi.advanceTimersByTime(200);
    expect(status.textContent).toMatch(/^Command help\./);
    expect(status.textContent.length).toBeLessThanOrEqual(240);
    expect(document.querySelector('.joo-term__log').getAttribute('aria-live')).toBe('off');
    vi.useRealTimers();
  });

  it('renders hostile content as text only', () => {
    const evil = {
      ...data,
      nodes: [
        ...data.nodes,
        {
          p: '/ai/evil.md',
          t: 'f',
          id: 99,
          title: '<img src=x onerror=alert(1)>',
          ex: '<script>alert(1)</script>',
          d: '2026-01-01',
          m: 1,
          tg: [],
          url: 'https://jooservices.test/evil/',
        },
      ],
    };
    start(evil);
    type('cat ai/evil.md');
    expect(document.querySelector('.joo-term__log img')).toBeNull();
    expect(document.querySelector('.joo-term__log script')).toBeNull();
    expect(logText()).toContain('<img src=x onerror=alert(1)>');
    type('ls ai');
    expect(document.querySelector('.joo-term__log img')).toBeNull();
  });

  it('navigates only to same-origin http(s) links from the file system', () => {
    expect(safeUrl(`${window.location.origin}/x/`)).toBe(`${window.location.origin}/x/`);
    expect(safeUrl('javascript:alert(1)')).toBeNull();
    expect(safeUrl('https://evil.example/')).toBeNull();
    expect(safeUrl('data:text/html,hi')).toBeNull();
    const same = {
      ...data,
      nodes: [
        ...data.nodes,
        { p: '/pages/here.md', t: 'f', page: 'x', title: 'Here', text: 'x', url: `${window.location.origin}/here/` },
        { p: '/pages/bad.md', t: 'f', page: 'x', title: 'Bad', text: 'x', url: 'javascript:alert(1)' },
        { p: '/pages/far.md', t: 'f', page: 'x', title: 'Far', text: 'x', url: 'https://evil.example/' },
      ],
    };
    start(same);
    type('open pages/here.md');
    expect(navigate).toHaveBeenCalledWith(`${window.location.origin}/here/`);
    navigate.mockClear();
    type('open pages/bad.md');
    type('open pages/far.md');
    expect(navigate).not.toHaveBeenCalled();
    expect(logText()).toContain('Blocked: not a link on this site.');
  });

  it('dispatches theme effects and follows the hreflang alternate for lang', () => {
    const seen = [];
    window.addEventListener('joo:theme', (event) => seen.push(event.detail.value), { once: true });
    type('theme light');
    expect(seen).toEqual(['light']);
    type('lang vi');
    expect(logText()).toContain('This page has no translation');
    document.head.append(
      Object.assign(document.createElement('link'), {
        rel: 'alternate',
        hreflang: 'vi',
        href: `${window.location.origin}/vi/bai/`,
      })
    );
    type('lang vi');
    expect(navigate).toHaveBeenCalledWith(`${window.location.origin}/vi/bai/`);
  });

  it('focuses the input', () => {
    api.focus();
    expect(document.activeElement).toBe(input());
  });
});
