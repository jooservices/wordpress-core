import { describe, expect, it } from 'vitest';
import { progress } from './view.js';

describe('reading progress', () => {
  it('is 0 before the article starts and 100 once it ends', () => {
    expect(progress({ top: 300, height: 3000 }, 800)).toBe(0);
    expect(progress({ top: 0, height: 3000 }, 800)).toBe(0);
    expect(progress({ top: -1100, height: 3000 }, 800)).toBe(50);
    expect(progress({ top: -2200, height: 3000 }, 800)).toBe(100);
    expect(progress({ top: -5000, height: 3000 }, 800)).toBe(100);
  });

  it('handles articles shorter than the viewport', () => {
    expect(progress({ top: 50, height: 400 }, 800)).toBe(0);
    expect(progress({ top: -10, height: 400 }, 800)).toBe(100);
  });
});
