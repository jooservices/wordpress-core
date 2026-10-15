import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { mockIntersectionObserver, flush } from '../../../test-kit/js/dom.js';
import { resetConfig, resetObservers } from './runtime.js';
import { safeFragment } from './lazy.js';
import './embed.js';

const json = (html, init = {}) =>
  new Response(JSON.stringify({ html }), { status: 200, headers: { 'content-type': 'application/json' }, ...init });

function mountLazy({ name = 'top-topics', fallback = '<p>inline copy</p>', button = false } = {}) {
  document.body.replaceChildren();
  const el = document.createElement('joo-lazy');
  el.setAttribute('name', name);
  el.setAttribute('data-src', 'https://site.test/wp-json/joo/v1/fragment/' + name);
  el.setAttribute('aria-busy', 'true');
  el.append(
    Object.assign(document.createElement('div'), { className: 'joo-lazy__placeholder', textContent: 'Loading…' })
  );
  if (button) {
    const b = document.createElement('button');
    b.setAttribute('data-joo-load', '');
    b.textContent = 'Show';
    el.firstElementChild.append(b);
  }
  const noscript = document.createElement('noscript');
  noscript.textContent = fallback;
  el.append(noscript);
  document.body.append(el);
  return el;
}

beforeEach(() => {
  resetConfig();
  resetObservers();
  document.body.replaceChildren();
  window.location.hash = '';
});
afterEach(() => {
  vi.restoreAllMocks();
  vi.useRealTimers();
});

describe('safeFragment', () => {
  it('removes scripts, inline handlers and javascript: URLs', () => {
    const fragment = safeFragment(
      '<p onclick="x()">ok <a href="javascript:alert(1)">bad</a><script>alert(1)</script><img src=x onerror="y()"></p>'
    );
    const html = Array.from(fragment.childNodes)
      .map((n) => n.outerHTML)
      .join('');
    expect(html).not.toContain('<script');
    expect(html).not.toContain('onclick');
    expect(html).not.toContain('onerror');
    expect(html).not.toContain('javascript:');
    expect(html).toContain('ok');
  });
});

describe('joo-lazy', () => {
  it('does not fetch until the element is near the viewport', () => {
    const io = mockIntersectionObserver();
    const fetchMock = vi.spyOn(globalThis, 'fetch').mockResolvedValue(json('<p>loaded</p>'));
    const el = mountLazy();
    expect(fetchMock).not.toHaveBeenCalled();
    io.trigger(el, false);
    expect(fetchMock).not.toHaveBeenCalled();
    io.trigger(el, true);
    expect(fetchMock).toHaveBeenCalledOnce();
    expect(io.instances.some((i) => i.options?.rootMargin === '400px 0px')).toBe(true);
  });

  it('inserts the fragment, clears aria-busy and fires an event', async () => {
    const io = mockIntersectionObserver();
    vi.spyOn(globalThis, 'fetch').mockResolvedValue(json('<ul><li>one</li></ul>'));
    const el = mountLazy();
    const loaded = vi.fn();
    el.addEventListener('joo:lazy-loaded', loaded);
    io.trigger(el, true);
    await flush();
    await flush();
    expect(el.querySelector('li')?.textContent).toBe('one');
    expect(el.hasAttribute('aria-busy')).toBe(false);
    expect(loaded).toHaveBeenCalledOnce();
  });

  it('loads immediately when the Show button is clicked', async () => {
    mockIntersectionObserver();
    const fetchMock = vi.spyOn(globalThis, 'fetch').mockResolvedValue(json('<p>c</p>'));
    const el = mountLazy({ name: 'comments', button: true });
    el.querySelector('[data-joo-load]').click();
    await flush();
    expect(fetchMock).toHaveBeenCalledOnce();
  });

  it('loads comments at once when the URL points to a comment', async () => {
    mockIntersectionObserver();
    window.location.hash = '#comment-12';
    const fetchMock = vi.spyOn(globalThis, 'fetch').mockResolvedValue(json('<p id="comment-12">c</p>'));
    mountLazy({ name: 'comments' });
    await flush();
    expect(fetchMock).toHaveBeenCalledOnce();
  });

  it('retries with backoff and then shows the inline fallback', async () => {
    vi.useFakeTimers();
    const io = mockIntersectionObserver();
    const fetchMock = vi.spyOn(globalThis, 'fetch').mockRejectedValue(new Error('offline'));
    const el = mountLazy({ fallback: '<p id="fb">inline copy</p>' });
    io.trigger(el, true);
    await vi.advanceTimersByTimeAsync(0);
    await vi.advanceTimersByTimeAsync(600);
    await vi.advanceTimersByTimeAsync(1100);
    expect(fetchMock).toHaveBeenCalledTimes(3);
    expect(el.querySelector('#fb')?.textContent).toBe('inline copy');
  });

  it('shows a retry button when there is no fallback and loads again on click', async () => {
    vi.useFakeTimers();
    const io = mockIntersectionObserver();
    const fetchMock = vi.spyOn(globalThis, 'fetch').mockRejectedValue(new Error('offline'));
    const el = mountLazy({ fallback: '' });
    io.trigger(el, true);
    await vi.advanceTimersByTimeAsync(2000);
    const retry = el.querySelector('.joo-lazy__retry');
    expect(retry).not.toBeNull();
    fetchMock.mockResolvedValue(json('<p>now</p>'));
    retry.click();
    await vi.advanceTimersByTimeAsync(0);
    expect(el.textContent).toContain('now');
  });

  it('rejects non-JSON and malformed payloads', async () => {
    vi.useFakeTimers();
    const io = mockIntersectionObserver();
    vi.spyOn(globalThis, 'fetch').mockResolvedValue(
      new Response('<html>', { status: 200, headers: { 'content-type': 'text/html' } })
    );
    const el = mountLazy({ fallback: '<p id="fb">fb</p>' });
    io.trigger(el, true);
    await vi.advanceTimersByTimeAsync(3000);
    expect(el.querySelector('#fb')).not.toBeNull();
  });

  it('treats HTTP errors as failures', async () => {
    vi.useFakeTimers();
    const io = mockIntersectionObserver();
    vi.spyOn(globalThis, 'fetch').mockResolvedValue(
      new Response('{}', { status: 500, headers: { 'content-type': 'application/json' } })
    );
    const el = mountLazy({ fallback: '<p id="fb">fb</p>' });
    io.trigger(el, true);
    await vi.advanceTimersByTimeAsync(3000);
    expect(el.querySelector('#fb')).not.toBeNull();
  });

  it('aborts the request when removed from the page', async () => {
    const io = mockIntersectionObserver();
    let signal;
    vi.spyOn(globalThis, 'fetch').mockImplementation((url, init) => {
      signal = init.signal;
      return new Promise(() => {});
    });
    const el = mountLazy();
    io.trigger(el, true);
    el.remove();
    expect(signal.aborted).toBe(true);
  });
});

describe('joo-embed', () => {
  function mountEmbed(provider, id, start = 0) {
    document.body.replaceChildren();
    const el = document.createElement('joo-embed');
    el.setAttribute('provider', provider);
    el.setAttribute('video', id);
    el.setAttribute('start', String(start));
    el.setAttribute('title', 'My video');
    const link = document.createElement('a');
    link.href = 'https://www.youtube.com/watch?v=' + id;
    link.textContent = 'Play video: My video';
    el.append(link);
    document.body.append(el);
    return el;
  }

  it('creates a youtube-nocookie iframe on click and focuses it', () => {
    const el = mountEmbed('youtube', 'dQw4w9WgXcQ', 42);
    el.querySelector('a').click();
    const iframe = el.querySelector('iframe');
    expect(iframe.src).toBe('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?autoplay=1&rel=0&start=42');
    expect(iframe.title).toBe('My video');
    expect(document.activeElement).toBe(iframe);
  });

  it('uses the Vimeo player with do-not-track', () => {
    const el = mountEmbed('vimeo', '123456789');
    el.querySelector('a').click();
    expect(el.querySelector('iframe').src).toBe('https://player.vimeo.com/video/123456789?autoplay=1&dnt=1');
  });

  it('starts on the Space key and ignores unknown providers', () => {
    const el = mountEmbed('youtube', 'dQw4w9WgXcQ');
    el.querySelector('a').dispatchEvent(new KeyboardEvent('keydown', { key: ' ', bubbles: true, cancelable: true }));
    expect(el.querySelector('iframe')).not.toBeNull();
    const other = mountEmbed('mystery', 'x');
    other.querySelector('a').click();
    expect(other.querySelector('iframe')).toBeNull();
  });

  it('does nothing without a link', () => {
    document.body.replaceChildren();
    const el = document.createElement('joo-embed');
    el.setAttribute('provider', 'youtube');
    document.body.append(el);
    expect(el.children).toHaveLength(0);
  });
});
