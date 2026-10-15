// Minimal deterministic PNG encoder (RGB, no dependencies): gradient plus a grid.
import { deflateSync } from 'node:zlib';

const crcTable = (() => {
  const table = new Uint32Array(256);
  for (let n = 0; n < 256; n += 1) {
    let c = n;
    for (let k = 0; k < 8; k += 1) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1;
    table[n] = c >>> 0;
  }
  return table;
})();

const crc32 = (buffer) => {
  let c = 0xffffffff;
  for (const byte of buffer) c = crcTable[(c ^ byte) & 0xff] ^ (c >>> 8);
  return (c ^ 0xffffffff) >>> 0;
};

const chunk = (type, data) => {
  const length = Buffer.alloc(4);
  length.writeUInt32BE(data.length);
  const body = Buffer.concat([Buffer.from(type, 'ascii'), data]);
  const crc = Buffer.alloc(4);
  crc.writeUInt32BE(crc32(body));
  return Buffer.concat([length, body, crc]);
};

/**
 * @param {number} width Width.
 * @param {number} height Height.
 * @param {[number, number, number]} from Gradient start colour.
 * @param {[number, number, number]} to Gradient end colour.
 * @returns {Buffer} PNG bytes.
 */
export function gradientPng(width, height, from, to) {
  const row = width * 3 + 1;
  const raw = Buffer.alloc(row * height);
  const step = Math.max(40, Math.round(width / 16));
  for (let y = 0; y < height; y += 1) {
    raw[y * row] = 0;
    for (let x = 0; x < width; x += 1) {
      const t = (x / width + y / height) / 2;
      const grid = x % step === 0 || y % step === 0 ? 0.18 : 0;
      for (let c = 0; c < 3; c += 1) {
        const value = from[c] + (to[c] - from[c]) * t;
        raw[y * row + 1 + x * 3 + c] = Math.min(255, Math.round(value + 255 * grid));
      }
    }
  }
  const header = Buffer.alloc(13);
  header.writeUInt32BE(width, 0);
  header.writeUInt32BE(height, 4);
  header[8] = 8;
  header[9] = 2;
  return Buffer.concat([
    Buffer.from([137, 80, 78, 71, 13, 10, 26, 10]),
    chunk('IHDR', header),
    chunk('IDAT', deflateSync(raw, { level: 9 })),
    chunk('IEND', Buffer.alloc(0)),
  ]);
}
