import { describe, expect, it, vi } from 'vitest';
import { flush, mockIntersectionObserver, mockMatchMedia, mount } from './dom.js';

describe('test-kit dom helpers', () => {
  it('mounts markup', () => {
    const el = mount('<p id="x">hi</p>');
    expect(el.querySelector('#x')?.textContent).toBe('hi');
  });

  it('controls IntersectionObserver callbacks', () => {
    const io = mockIntersectionObserver();
    const callback = vi.fn();
    const target = document.createElement('div');
    new IntersectionObserver(callback).observe(target);
    io.trigger(target, true);
    expect(callback).toHaveBeenCalledOnce();
    expect(callback.mock.calls[0][0][0].isIntersecting).toBe(true);
  });

  it('mocks matchMedia', () => {
    mockMatchMedia({ '(prefers-reduced-motion: reduce)': true });
    expect(window.matchMedia('(prefers-reduced-motion: reduce)').matches).toBe(true);
    expect(window.matchMedia('(min-width: 1px)').matches).toBe(false);
  });

  it('flushes timers', async () => {
    let done = false;
    setTimeout(() => (done = true), 0);
    await flush();
    expect(done).toBe(true);
  });
});
