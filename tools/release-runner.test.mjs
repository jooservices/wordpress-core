// @vitest-environment node
import { execFileSync } from 'node:child_process';
import { mkdtempSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { afterEach, describe, expect, it } from 'vitest';
import { createGitContext } from './release-runner.mjs';

const directories = [];

afterEach(() => {
  for (const directory of directories.splice(0)) rmSync(directory, { recursive: true, force: true });
});

describe('release runner Git context', () => {
  it('captures clean status, commit, and issue-keyed subjects from a checkout', () => {
    const directory = mkdtempSync(join(tmpdir(), 'jooservices-release-runner-'));
    directories.push(directory);
    execFileSync('git', ['init', '-q'], { cwd: directory });
    execFileSync('git', ['config', 'user.name', 'JOOservices Tests'], { cwd: directory });
    execFileSync('git', ['config', 'user.email', 'tests@example.invalid'], { cwd: directory });
    writeFileSync(join(directory, 'README.md'), 'initial\n');
    execFileSync('git', ['add', 'README.md'], { cwd: directory });
    execFileSync('git', ['commit', '-qm', 'initial commit'], { cwd: directory });
    writeFileSync(join(directory, 'README.md'), 'release\n');
    execFileSync('git', ['add', 'README.md'], { cwd: directory });
    execFileSync('git', ['commit', '-qm', 'JOOS-103: release package'], { cwd: directory });

    const context = createGitContext(directory);
    expect(context.status).toBe('');
    expect(context.commit).toMatch(/^[a-f0-9]+$/);
    expect(context.subjects).toContain('JOOS-103: release package');
    expect(context.tags).toEqual([]);

    writeFileSync(join(directory, 'dirty.txt'), 'pending');
    expect(createGitContext(directory).status).toContain('?? dirty.txt');
  });
});
