import { describe, expect, it } from 'vitest';
import { match, similarity, slugOf } from './view.js';
import './view.js';

const index = [
  { s: 'wordpress-mcp-v1', t: 'WordPress MCP', u: 'https://x.test/wordpress-mcp-v1/' },
  { s: 'prompt-caching', t: 'Prompt caching', u: 'https://x.test/prompt-caching/' },
];

describe('404 suggestions', () => {
  it('reads the slug from a path', () => {
    expect(slugOf('/ai/Prompt-Caching.md?x=1#y')).toBe('prompt-caching');
    expect(slugOf('/')).toBe('');
  });

  it('finds exact matches and near matches without any network', () => {
    expect(match('/ai/prompt-caching', index).exact.t).toBe('Prompt caching');
    const near = match('/blog/wordpress-mpc-v1', index);
    expect(near.exact).toBeNull();
    expect(near.near[0].s).toBe('wordpress-mcp-v1');
    expect(match('/zzzzzz', index)).toEqual({ exact: null, near: [] });
    expect(similarity('a', 'b')).toBe(0);
  });

  it('the retry form shows the result as DOM nodes', () => {
    const render = (html) =>
      document.body.replaceChildren(...new DOMParser().parseFromString(html, 'text/html').body.childNodes);
    render(
      `<joo-404><form class="joo-404__retry" hidden><input><p class="joo-404__result"></p></form><script type="application/json" class="joo-404__index">${JSON.stringify(index)}</script></joo-404>`
    );
    const form = document.querySelector('.joo-404__retry');
    expect(form.hidden).toBe(false);
    form.querySelector('input').value = '/ai/prompt-caching';
    form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
    expect(form.querySelector('.joo-404__result a').href).toBe('https://x.test/prompt-caching/');
    expect(form.querySelector('.joo-404__result').textContent).toContain('HTTP/2 200 OK');
    form.querySelector('input').value = '/blog/wordpress-mpc-v1';
    form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
    expect(form.querySelector('.joo-404__result').textContent).toContain('HTTP/2 404');
    form.querySelector('input').value = '/nothing-like-this-zz';
    form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
    expect(form.querySelector('.joo-404__result').textContent).toBe('HTTP/2 404');
  });
});
