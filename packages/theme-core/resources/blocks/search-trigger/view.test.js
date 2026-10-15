import { beforeEach, describe, expect, it, vi } from 'vitest';
import { shouldOpen } from './view.js';
import './view.js';

const render = (html) =>
  document.body.replaceChildren(...new DOMParser().parseFromString(html, 'text/html').body.childNodes);

beforeEach(() => {
  HTMLDialogElement.prototype.showModal = vi.fn(function () {
    this.open = true;
  });
  HTMLDialogElement.prototype.close = vi.fn(function () {
    this.open = false;
  });
  render(
    `<joo-search><a class="joo-search__link" href="/?s=">Search</a><dialog><form><input type="search"><button class="joo-search__close" type="button"></button></form></dialog></joo-search>`
  );
});

describe('joo-search', () => {
  it('opens the dialog from the link instead of navigating', () => {
    const event = new MouseEvent('click', { bubbles: true, cancelable: true });
    document.querySelector('.joo-search__link').dispatchEvent(event);
    expect(event.defaultPrevented).toBe(true);
    expect(HTMLDialogElement.prototype.showModal).toHaveBeenCalled();
  });

  it('opens with the / shortcut but not while typing', () => {
    const press = (target, init = {}) => {
      const event = new KeyboardEvent('keydown', { key: '/', bubbles: true, cancelable: true, ...init });
      target.dispatchEvent(event);
      return event;
    };
    expect(press(document.body).defaultPrevented).toBe(true);
    expect(shouldOpen({ key: '/', target: document.createElement('input') })).toBe(false);
    expect(shouldOpen({ key: '/', target: document.createElement('textarea') })).toBe(false);
    expect(shouldOpen({ key: '/', ctrlKey: true, target: document.body })).toBe(false);
    expect(shouldOpen({ key: 'a', target: document.body })).toBe(false);
    const editable = document.createElement('div');
    Object.defineProperty(editable, 'isContentEditable', { value: true });
    expect(shouldOpen({ key: '/', target: editable })).toBe(false);
  });

  it('closes from the close button and the backdrop', () => {
    document.querySelector('.joo-search__close').click();
    expect(HTMLDialogElement.prototype.close).toHaveBeenCalledTimes(1);
    const dialog = document.querySelector('dialog');
    dialog.dispatchEvent(new MouseEvent('click', { bubbles: true }));
    expect(HTMLDialogElement.prototype.close).toHaveBeenCalledTimes(2);
  });
});
