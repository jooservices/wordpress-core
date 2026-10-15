import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
  bus,
  config,
  define,
  feature,
  idle,
  inView,
  load,
  prefersReducedMotion,
  ready,
  resetConfig,
  restUrl,
  saveData,
  store,
  t,
} from './runtime.js';
import { mockIntersectionObserver, mockMatchMedia, mount } from '../../../test-kit/js/dom.js';

function setConfig(json) {
  document.getElementById('joo-config')?.remove();
  const script = document.createElement('script');
  script.type = 'application/json';
  script.id = 'joo-config';
  script.textContent = typeof json === 'string' ? json : JSON.stringify(json);
  document.body.append(script);
  resetConfig();
}

beforeEach(() => {
  document.body.replaceChildren();
  resetConfig();
});
afterEach(() => vi.restoreAllMocks());

describe('config', () => {
  it('falls back to defaults when the element is missing', () => {
    expect(config().rest).toBe('/wp-json/joo/v1/');
    expect(feature('terminal')).toBe(false);
  });

  it('reads and freezes the printed configuration', () => {
    setConfig({ features: { terminal: true }, i18n: { 'toc.title': 'Mục lục' }, lang: 'vi' });
    expect(feature('terminal')).toBe(true);
    expect(t('toc.title', 'On this page')).toBe('Mục lục');
    expect(t('missing', 'fallback')).toBe('fallback');
    expect(config().lang).toBe('vi');
    expect(Object.isFrozen(config())).toBe(true);
  });

  it('survives invalid JSON', () => {
    setConfig('{not json');
    expect(config().lang).toBe('en');
  });

  it('builds REST URLs', () => {
    setConfig({ rest: 'https://site.test/wp-json/joo/v1/' });
    expect(restUrl('/fragment/tags')).toBe('https://site.test/wp-json/joo/v1/fragment/tags');
  });

  it('caches the configuration', () => {
    setConfig({ lang: 'vi' });
    const first = config();
    document.getElementById('joo-config').textContent = '{"lang":"fr"}';
    expect(config()).toBe(first);
  });
});

describe('ready, define, idle', () => {
  it('runs immediately when the DOM is parsed', () => {
    const fn = vi.fn();
    ready(fn);
    expect(fn).toHaveBeenCalledOnce();
  });

  it('waits for DOMContentLoaded while the document is loading', () => {
    const fn = vi.fn();
    Object.defineProperty(document, 'readyState', { value: 'loading', configurable: true });
    ready(fn);
    expect(fn).not.toHaveBeenCalled();
    document.dispatchEvent(new Event('DOMContentLoaded'));
    expect(fn).toHaveBeenCalledOnce();
    delete document.readyState;
  });

  it('defines an element only once', () => {
    class A extends HTMLElement {}
    class B extends HTMLElement {}
    define('joo-test-once', A);
    define('joo-test-once', B);
    expect(customElements.get('joo-test-once')).toBe(A);
  });

  it('idle uses requestIdleCallback when present and setTimeout otherwise', () => {
    const fn = vi.fn();
    window.requestIdleCallback = (cb) => cb();
    idle(fn);
    expect(fn).toHaveBeenCalledOnce();
    delete window.requestIdleCallback;
    vi.useFakeTimers();
    const later = vi.fn();
    idle(later);
    vi.advanceTimersByTime(5);
    expect(later).toHaveBeenCalledOnce();
    vi.useRealTimers();
  });
});

describe('inView', () => {
  it('calls back once when the element intersects and stops watching', () => {
    const io = mockIntersectionObserver();
    const el = mount('<div id="a"></div>').firstElementChild;
    const cb = vi.fn();
    inView(el, cb, { rootMargin: '10px' });
    io.trigger(el, false);
    expect(cb).not.toHaveBeenCalled();
    io.trigger(el, true);
    io.trigger(el, true);
    expect(cb).toHaveBeenCalledOnce();
  });

  it('keeps watching when once is false and can be cancelled', () => {
    const io = mockIntersectionObserver();
    const el = mount('<div></div>').firstElementChild;
    const cb = vi.fn();
    const stop = inView(el, cb, { rootMargin: '20px', once: false });
    io.trigger(el, true);
    io.trigger(el, true);
    expect(cb).toHaveBeenCalledTimes(2);
    stop();
    io.trigger(el, true);
    expect(cb).toHaveBeenCalledTimes(2);
  });

  it('shares one observer per root margin', () => {
    const io = mockIntersectionObserver();
    const a = mount('<div></div>').firstElementChild;
    const b = document.createElement('div');
    inView(a, () => {}, { rootMargin: '33px' });
    inView(b, () => {}, { rootMargin: '33px' });
    expect(io.instances.filter((i) => i.options?.rootMargin === '33px')).toHaveLength(1);
  });

  it('calls back immediately without IntersectionObserver', () => {
    const original = window.IntersectionObserver;
    delete window.IntersectionObserver;
    const cb = vi.fn();
    inView(document.createElement('div'), cb);
    expect(cb).toHaveBeenCalledWith(null);
    window.IntersectionObserver = original;
  });
});

describe('load, bus, store, media', () => {
  it('rejects unknown modules and caches imports', async () => {
    await expect(load('unknown')).rejects.toThrow('Unknown joo module');
    const url = 'data:text/javascript,export default 7;';
    setConfig({ assets: { seven: url } });
    const first = load('seven');
    expect(load('seven')).toBe(first);
    expect((await first).default).toBe(7);
  });

  it('delivers bus events and supports unsubscribe', () => {
    const fn = vi.fn();
    const off = bus.on('x', fn);
    bus.emit('x', { a: 1 });
    off();
    bus.emit('x', { a: 2 });
    expect(fn).toHaveBeenCalledOnce();
    expect(fn).toHaveBeenCalledWith({ a: 1 });
  });

  it('stores values and falls back to memory when storage throws', () => {
    const s = store('k1');
    s.set('v');
    expect(s.get()).toBe('v');
    s.remove();
    expect(s.get()).toBeNull();
    const original = Storage.prototype.getItem;
    Storage.prototype.getItem = () => {
      throw new Error('denied');
    };
    const set = vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
      throw new Error('denied');
    });
    const memory = store('k2');
    memory.set('kept');
    expect(memory.get()).toBe('kept');
    memory.remove();
    expect(memory.get()).toBeNull();
    Storage.prototype.getItem = original;
    set.mockRestore();
  });

  it('reads reduced motion and save-data hints', () => {
    mockMatchMedia({ '(prefers-reduced-motion: reduce)': true });
    expect(prefersReducedMotion()).toBe(true);
    expect(saveData()).toBe(false);
    Object.defineProperty(navigator, 'connection', { value: { saveData: true }, configurable: true });
    expect(saveData()).toBe(true);
  });
});
