// @vitest-environment node
import { afterEach, describe, expect, it } from 'vitest';
import { execFileSync } from 'node:child_process';
import { mkdirSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';
import { gzipSync } from 'node:zlib';
import { ROOT } from './lib/paths.mjs';
import { gzipSize } from './lib/gzip-size.mjs';

describe('build-assets gzip measurements', () => {
  let tempRoot;

  afterEach(() => {
    if (tempRoot) rmSync(tempRoot, { recursive: true, force: true });
    tempRoot = undefined;
  });

  it('records each standalone bundle using the exact gzip byte count', () => {
    const scratch = join(ROOT, '.tmp');
    mkdirSync(scratch, { recursive: true });
    tempRoot = mkdtempSync(join(scratch, 'build-assets-'));
    mkdirSync(join(tempRoot, 'resources/js/terminal'), { recursive: true });
    writeFileSync(join(tempRoot, 'resources/js/example.js'), 'export const example = "fixture";\n');
    writeFileSync(join(tempRoot, 'resources/js/terminal/engine.js'), 'export const engine = "fixture";\n');

    execFileSync(
      process.execPath,
      [join(ROOT, 'tools/build-assets.mjs'), '--root', tempRoot, '--src', 'resources', '--out', 'resources/build'],
      { cwd: ROOT, stdio: 'pipe' }
    );

    const manifest = JSON.parse(readFileSync(join(tempRoot, 'resources/build/assets.json'), 'utf8'));
    for (const logical of ['example', 'terminal-engine']) {
      const asset = manifest.assets[logical];
      const output = readFileSync(join(tempRoot, 'resources/build', asset.file));
      const expected = gzipSync(output).byteLength;
      expect(gzipSize(output)).toBe(expected);
      expect(asset.gzip).toBe(expected);
    }
  });
});
