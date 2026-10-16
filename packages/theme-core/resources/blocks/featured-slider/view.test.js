import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import './view.js';

const render = (html) =>
  document.body.replaceChildren(...new DOMParser().parseFromString(html, 'text/html').body.childNodes);

const markup = (autoplay = '1') => `
<joo-slider data-autoplay="${autoplay}" data-interval="3000">
  <div class="joo-slider__track" tabindex="0">
    <article class="joo-slide">A</article><article class="joo-slide">B</article><article class="joo-slide">C</article>
  </div>
  <div class="joo-slider__controls" hidden>
    <button class="joo-slider__prev"></button>
    <span><button class="joo-slider__dot"></button><button class="joo-slider__dot"></button><button class="joo-slider__dot"></button></span>
    <button class="joo-slider__pause" hidden></button>
    <button class="joo-slider__next"></button>
  </div>
</joo-slider>`;

let observers;

beforeEach(() => {
  observers = [];
  globalThis.IntersectionObserver = class {
    constructor(cb) {
      this.cb = cb;
      observers.push(this);
    }
    observe() {}
    disconnect() {}
  };
  window.matchMedia = () => ({ matches: false, addEventListener() {}, removeEventListener() {} });
  vi.useFakeTimers();
});

afterEach(() => {
  vi.useRealTimers();
  document.body.replaceChildren();
});

const mount = (autoplay) => {
  render(markup(autoplay));
  const el = document.querySelector('joo-slider');
  el.track.scrollTo = vi.fn();
  return el;
};

describe('joo-slider', () => {
  it('shows the controls and marks the first dot', () => {
    const el = mount();
    expect(el.querySelector('.joo-slider__controls').hidden).toBe(false);
    expect(el.dots[0].getAttribute('aria-current')).toBe('true');
    expect(el.dots[1].getAttribute('aria-current')).toBe('false');
  });

  it('does nothing with a single slide', () => {
    render(
      '<joo-slider><div class="joo-slider__track"><article></article></div><div class="joo-slider__controls" hidden></div></joo-slider>'
    );
    expect(document.querySelector('.joo-slider__controls').hidden).toBe(true);
  });

  it('navigates with buttons, wrapping around', () => {
    const el = mount('0');
    el.querySelector('.joo-slider__next').click();
    expect(el.index).toBe(1);
    el.querySelector('.joo-slider__prev').click();
    el.querySelector('.joo-slider__prev').click();
    expect(el.index).toBe(2);
    expect(el.track.scrollTo).toHaveBeenCalled();
    el.dots[1].click();
    expect(el.index).toBe(1);
  });

  it('supports the keyboard on the track', () => {
    const el = mount('0');
    const press = (key) =>
      el.track.dispatchEvent(new KeyboardEvent('keydown', { key, bubbles: true, cancelable: true }));
    press('ArrowRight');
    expect(el.index).toBe(1);
    press('End');
    expect(el.index).toBe(2);
    press('Home');
    expect(el.index).toBe(0);
    press('ArrowLeft');
    expect(el.index).toBe(2);
    const event = new KeyboardEvent('keydown', { key: 'a', cancelable: true });
    el.track.dispatchEvent(event);
    expect(event.defaultPrevented).toBe(false);
  });

  it('follows the slide that scrolls into view', () => {
    const el = mount('0');
    observers[0].cb([{ isIntersecting: true, intersectionRatio: 0.9, target: el.slides[2] }]);
    expect(el.index).toBe(2);
    observers[0].cb([{ isIntersecting: true, intersectionRatio: 0.3, target: el.slides[1] }]);
    expect(el.index).toBe(2);
  });

  it('autoplays, pauses on hover and focus, resumes afterwards and offers a pause button', () => {
    const el = mount('1');
    expect(el.pauseButton.hidden).toBe(false);
    expect(el.track.getAttribute('aria-live')).toBe('off');
    vi.advanceTimersByTime(3000);
    expect(el.index).toBe(1);
    el.dispatchEvent(new Event('pointerenter'));
    vi.advanceTimersByTime(9000);
    expect(el.index).toBe(1);
    expect(el.track.getAttribute('aria-live')).toBe('polite');
    el.dispatchEvent(new Event('pointerleave'));
    vi.advanceTimersByTime(3000);
    expect(el.index).toBe(2);
    el.dispatchEvent(new Event('focusin'));
    vi.advanceTimersByTime(6000);
    expect(el.index).toBe(2);
  });

  it('keeps autoplay stopped when the user paused it', () => {
    const el = mount('1');
    el.pauseButton.click();
    vi.advanceTimersByTime(10000);
    expect(el.index).toBe(0);
    el.dispatchEvent(new Event('pointerleave'));
    vi.advanceTimersByTime(10000);
    expect(el.index).toBe(0);
    el.pauseButton.click();
    vi.advanceTimersByTime(3000);
    expect(el.index).toBe(1);
  });

  it('pauses while the tab is hidden', () => {
    const el = mount('1');
    Object.defineProperty(document, 'hidden', { configurable: true, get: () => true });
    document.dispatchEvent(new Event('visibilitychange'));
    vi.advanceTimersByTime(9000);
    expect(el.index).toBe(0);
    Object.defineProperty(document, 'hidden', { configurable: true, get: () => false });
    document.dispatchEvent(new Event('visibilitychange'));
    vi.advanceTimersByTime(3000);
    expect(el.index).toBe(1);
  });

  it('never autoplays with reduced motion and uses instant scrolling', () => {
    window.matchMedia = () => ({ matches: true, addEventListener() {}, removeEventListener() {} });
    const el = mount('1');
    expect(el.autoplay).toBe(false);
    expect(el.pauseButton.hidden).toBe(true);
    vi.advanceTimersByTime(20000);
    expect(el.index).toBe(0);
    el.go(1);
    expect(el.track.scrollTo).toHaveBeenCalledWith(expect.objectContaining({ behavior: 'auto' }));
  });

  it('cleans up on disconnect', () => {
    const el = mount('1');
    el.remove();
    vi.advanceTimersByTime(20000);
    expect(el.index).toBe(0);
  });

  it('swaps the arrow keys and the scroll offset in right to left layouts', () => {
    const el = mount();
    const key = (name) => {
      const event = new KeyboardEvent('keydown', { key: name, bubbles: true, cancelable: true });
      el.track.dispatchEvent(event);
    };
    el.isRtl = () => true;
    el.track.style.direction = 'rtl';
    key('ArrowLeft');
    expect(el.index).toBe(1);
    key('ArrowRight');
    expect(el.index).toBe(0);
    expect(el.track.scrollTo).toHaveBeenLastCalledWith(expect.objectContaining({ left: expect.any(Number) }));
  });

  it('keeps the left to right arrow keys by default', () => {
    const el = mount();
    el.track.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowRight', bubbles: true, cancelable: true }));
    expect(el.index).toBe(1);
  });
});
