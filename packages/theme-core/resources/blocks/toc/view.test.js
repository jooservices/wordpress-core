import { beforeEach, describe, expect, it, vi } from 'vitest';
import './view.js';

const render = (html) =>
  document.body.replaceChildren(...new DOMParser().parseFromString(html, 'text/html').body.childNodes);

let observer;

beforeEach(() => {
  globalThis.IntersectionObserver = class {
    constructor(cb) {
      this.cb = cb;
      observer = this;
    }
    observe() {}
    disconnect() {}
  };
  window.matchMedia = () => ({ matches: false });
  render(`
    <h2 id="one">One</h2><h2 id="two">Two</h2>
    <joo-toc><nav><a href="#one">One</a><a href="#two">Two</a><a href="#missing">Missing</a></nav></joo-toc>`);
  document.getElementById('one').scrollIntoView = vi.fn();
});

describe('joo-toc', () => {
  it('marks the heading in view', () => {
    const links = document.querySelectorAll('joo-toc a');
    observer.cb([{ isIntersecting: true, target: document.getElementById('two') }]);
    expect(links[1].getAttribute('aria-current')).toBe('location');
    expect(links[0].hasAttribute('aria-current')).toBe(false);
    observer.cb([{ isIntersecting: false, target: document.getElementById('one') }]);
    expect(links[1].getAttribute('aria-current')).toBe('location');
  });

  it('scrolls, updates the hash and the current link on click', () => {
    const link = document.querySelector('joo-toc a');
    const event = new MouseEvent('click', { bubbles: true, cancelable: true });
    link.dispatchEvent(event);
    expect(event.defaultPrevented).toBe(true);
    expect(document.getElementById('one').scrollIntoView).toHaveBeenCalledWith({ behavior: 'smooth', block: 'start' });
    expect(window.location.hash).toBe('#one');
    expect(link.getAttribute('aria-current')).toBe('location');
  });

  it('leaves links to missing headings alone', () => {
    const link = document.querySelectorAll('joo-toc a')[2];
    const event = new MouseEvent('click', { bubbles: true, cancelable: true });
    link.dispatchEvent(event);
    expect(event.defaultPrevented).toBe(false);
  });
});
