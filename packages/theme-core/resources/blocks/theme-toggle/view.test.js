import { beforeEach, describe, expect, it } from 'vitest';
import { apply, current, stored } from './view.js';
import './view.js';

const render = (html) =>
  document.body.replaceChildren(...new DOMParser().parseFromString(html, 'text/html').body.childNodes);

let mediaListener;

beforeEach(() => {
  localStorage.clear();
  document.documentElement.dataset.theme = 'dark';
  document.head.replaceChildren(
    Object.assign(document.createElement('meta'), { name: 'color-scheme', content: 'dark' })
  );
  window.matchMedia = () => ({
    matches: false,
    addEventListener: (_type, fn) => (mediaListener = fn),
    removeEventListener() {},
  });
  render('<joo-theme-toggle><button type="button" aria-pressed="true" hidden></button></joo-theme-toggle>');
});

describe('theme toggle', () => {
  it('shows the button and reflects the current scheme', () => {
    const button = document.querySelector('button');
    expect(button.hidden).toBe(false);
    expect(button.getAttribute('aria-pressed')).toBe('true');
  });

  it('toggles, persists and syncs the color-scheme meta', () => {
    const button = document.querySelector('button');
    button.click();
    expect(current()).toBe('light');
    expect(stored()).toBe('light');
    expect(button.getAttribute('aria-pressed')).toBe('false');
    expect(document.querySelector('meta[name="color-scheme"]').getAttribute('content')).toBe('light');
    button.click();
    expect(current()).toBe('dark');
    expect(stored()).toBe('dark');
  });

  it('follows the system until the user chooses', () => {
    mediaListener({ matches: true });
    expect(current()).toBe('light');
    localStorage.setItem('joo-theme', 'dark');
    mediaListener({ matches: true });
    expect(current()).toBe('light');
  });

  it('ignores invalid stored values and survives a broken storage', () => {
    localStorage.setItem('joo-theme', 'blue');
    expect(stored()).toBeNull();
    apply('light');
    expect(current()).toBe('light');
  });

  it('applies a scheme requested through the joo:theme event (terminal)', () => {
    window.dispatchEvent(new CustomEvent('joo:theme', { detail: { value: 'light' } }));
    expect(current()).toBe('light');
    expect(stored()).toBe('light');
    window.dispatchEvent(new CustomEvent('joo:theme', { detail: { value: 'purple' } }));
    expect(current()).toBe('light');
  });
});
