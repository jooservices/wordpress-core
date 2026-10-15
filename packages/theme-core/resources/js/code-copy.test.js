import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const render = (html) =>
  document.body.replaceChildren(...new DOMParser().parseFromString(html, 'text/html').body.childNodes);

const box = `<div class="joo-code"><div class="joo-code__bar"><button class="joo-code__copy" hidden>Copy</button><span class="joo-code__status"></span></div><pre><code>echo 1;</code></pre></div>`;

beforeEach(() => {
  vi.resetModules();
  vi.useFakeTimers();
  render(box);
});

afterEach(() => vi.useRealTimers());

describe('code copy', () => {
  it('reveals the buttons and copies with the clipboard API', async () => {
    const writeText = vi.fn().mockResolvedValue();
    Object.defineProperty(navigator, 'clipboard', { configurable: true, value: { writeText } });
    await import('./code-copy.js');
    const button = document.querySelector('.joo-code__copy');
    expect(button.hidden).toBe(false);
    button.click();
    await vi.advanceTimersByTimeAsync(0);
    expect(writeText).toHaveBeenCalledWith('echo 1;');
    expect(button.textContent).toBe('Copied');
    expect(document.querySelector('.joo-code__status').textContent).toBe('Code copied');
    await vi.advanceTimersByTimeAsync(1600);
    expect(button.textContent).toBe('Copy');
    expect(document.querySelector('.joo-code__status').textContent).toBe('');
  });

  it('falls back to execCommand when the clipboard API fails', async () => {
    Object.defineProperty(navigator, 'clipboard', {
      configurable: true,
      value: { writeText: vi.fn().mockRejectedValue(new Error('denied')) },
    });
    document.execCommand = vi.fn().mockReturnValue(true);
    const { copy } = await import('./code-copy.js');
    expect(await copy('x')).toBe(true);
    expect(document.execCommand).toHaveBeenCalledWith('copy');
    expect(document.querySelector('textarea')).toBeNull();
  });

  it('reports a failure when nothing works', async () => {
    Object.defineProperty(navigator, 'clipboard', { configurable: true, value: undefined });
    document.execCommand = vi.fn().mockReturnValue(false);
    await import('./code-copy.js');
    const button = document.querySelector('.joo-code__copy');
    button.click();
    await vi.advanceTimersByTimeAsync(0);
    expect(button.textContent).toBe('Press Ctrl+C');
    expect(document.querySelector('.joo-code__status').textContent).toBe('Copy failed');
  });

  it('ignores clicks outside copy buttons', async () => {
    await import('./code-copy.js');
    document.querySelector('pre').click();
    expect(document.querySelector('.joo-code__status').textContent).toBe('');
  });
});
