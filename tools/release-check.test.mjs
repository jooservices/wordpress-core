// @vitest-environment node
import { describe, expect, it } from 'vitest';
import { pngDimensions } from './release-check.mjs';

describe('release preview checks', () => {
  it('reads PNG dimensions and rejects invalid or truncated data', () => {
    const png = Buffer.alloc(24);
    Buffer.from([137, 80, 78, 71, 13, 10, 26, 10]).copy(png);
    png.writeUInt32BE(590, 16);
    png.writeUInt32BE(300, 20);
    expect(pngDimensions(png)).toEqual({ width: 590, height: 300 });
    expect(pngDimensions(Buffer.from('no image'))).toBeNull();
    expect(pngDimensions(Buffer.alloc(24))).toBeNull();
  });
});
