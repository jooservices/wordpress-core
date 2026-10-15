import { beforeEach, describe, expect, it } from 'vitest';
import './view.js';

const render = (html) =>
  document.body.replaceChildren(...new DOMParser().parseFromString(html, 'text/html').body.childNodes);

const tree = (depth = 0) => `
<joo-tree data-expanded-depth="${depth}"><nav><ul>
  <li class="joo-tree__item" data-id="1" data-depth="1"><div class="joo-tree__row"><button class="joo-tree__toggle" aria-expanded="true" hidden></button><a href="/a/">A</a></div>
    <div class="joo-tree__children"><ul><li class="joo-tree__item" data-id="2" data-depth="2"><div class="joo-tree__row"><a href="/a/b/">B</a></div></li></ul></div></li>
  <li class="joo-tree__item" data-id="3" data-depth="1" data-open="1"><div class="joo-tree__row"><button class="joo-tree__toggle" aria-expanded="true" hidden></button><a href="/c/">C</a></div>
    <div class="joo-tree__children"><ul><li class="joo-tree__item" data-id="4" data-depth="2"><div class="joo-tree__row"><a href="/c/d/" aria-current="page">D</a></div></li></ul></div></li>
</ul></nav></joo-tree>`;

const children = (id) => document.querySelector(`[data-id="${id}"] > .joo-tree__children`);
const toggle = (id) => document.querySelector(`[data-id="${id}"] > .joo-tree__row > .joo-tree__toggle`);

beforeEach(() => {
  sessionStorage.clear();
});

describe('joo-tree', () => {
  it('collapses branches except the one holding the current page', () => {
    render(tree());
    expect(children(1).hidden).toBe(true);
    expect(toggle(1).getAttribute('aria-expanded')).toBe('false');
    expect(toggle(1).hidden).toBe(false);
    expect(children(3).hidden).toBe(false);
    expect(toggle(3).getAttribute('aria-expanded')).toBe('true');
  });

  it('keeps levels up to expanded-depth open', () => {
    render(tree(1));
    expect(children(1).hidden).toBe(false);
  });

  it('toggles with the button and remembers the choice', () => {
    render(tree());
    toggle(1).click();
    expect(children(1).hidden).toBe(false);
    expect(JSON.parse(sessionStorage.getItem('joo-tree'))).toContain('1');
    toggle(1).click();
    expect(children(1).hidden).toBe(true);
    expect(JSON.parse(sessionStorage.getItem('joo-tree'))).toContain('-1');
  });

  it('restores the remembered state on the next page, but never hides the current branch', () => {
    sessionStorage.setItem('joo-tree', JSON.stringify(['1', '-3']));
    render(tree());
    expect(children(1).hidden).toBe(false);
    expect(children(3).hidden).toBe(false);
  });

  it('survives broken storage content', () => {
    sessionStorage.setItem('joo-tree', '{broken');
    render(tree());
    expect(children(1).hidden).toBe(true);
  });
});
