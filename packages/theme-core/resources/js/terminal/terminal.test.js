import { describe, expect, it } from 'vitest';
import './test-i18n.js';
import data from './__fixtures__/demo-fs.json';
import { createShell } from '../terminal.js';
import { buildFs, list, pathOf, resolve, stat, target } from './fs.js';
import { createRegistry, registerCommand } from './registry.js';
import { parseFlags, similarity, tokenize } from './shell.js';

const shell = () => createShell(data);
/** Plain text of a result. */
const plain = (result) =>
  result.blocks
    .map((b) =>
      b.kind === 'cols'
        ? b.items.map((s) => s.text).join('  ')
        : b.lines.map((l) => l.map((s) => s.text).join('')).join('\n')
    )
    .join('\n');
const run = (s, cmd) => plain(s.run(cmd));

describe('tokenizer', () => {
  it('splits on whitespace and honours quotes and escapes', () => {
    expect(tokenize('ls -l  ai')).toEqual(['ls', '-l', 'ai']);
    expect(tokenize(`grep "docker compose" 'a b' c\\ d`)).toEqual(['grep', 'docker compose', 'a b', 'c d']);
    expect(tokenize('echo ""')).toEqual(['echo', '']);
    expect(tokenize('   ')).toEqual([]);
    expect(tokenize('say "he said \\"hi\\""')).toEqual(['say', 'he said "hi"']);
  });

  it('parses flags and leaves negative numbers as operands', () => {
    expect(parseFlags(['-lat', 'x', '-5'])).toEqual({ flags: { l: true, a: true, t: true }, args: ['x', '-5'] });
  });

  it('scores similar names higher', () => {
    expect(similarity('cata', 'cat')).toBeGreaterThan(0.4);
    expect(similarity('pwd', 'pwd')).toBe(1);
    expect(similarity('a', 'b')).toBe(0);
    expect(similarity('lss', 'ls')).toBeGreaterThan(0.4);
  });
});

describe('file system', () => {
  const fs = buildFs(data);
  it('builds directories, files and links', () => {
    expect(fs.count.posts).toBe(8);
    expect(fs.root.k.ai.t).toBe('d');
    expect(fs.root.k.blog.k.news.k['wordpress-mcp-v1.md'].t).toBe('l');
    expect(pathOf(fs.root.k.ai.k['prompt-caching.md'])).toBe('/ai/prompt-caching.md');
  });

  it('resolves ., .., ~, absolute and relative paths and follows links', () => {
    const ai = fs.root.k.ai;
    expect(resolve(fs.root, '..', ai)).toBe(fs.root);
    expect(resolve(fs.root, '~', ai)).toBe(fs.root);
    expect(resolve(fs.root, '/ai/./prompt-caching.md', fs.root).n).toBe('prompt-caching.md');
    expect(resolve(fs.root, '../blog/news', ai).n).toBe('news');
    expect(resolve(fs.root, '/nope', fs.root)).toBeNull();
    expect(resolve(fs.root, 'prompt-caching.md/x', ai)).toBeNull();
    const link = fs.root.k.tags.k.mcp.k['wordpress-mcp-v1.md'];
    expect(target(fs.root, link).parent.n).toBe('ai');
  });

  it('guards against link loops', () => {
    const loop = buildFs({
      nodes: [
        { p: '/a', t: 'l', to: '/b' },
        { p: '/b', t: 'l', to: '/a' },
      ],
    });
    expect(resolve(loop.root, '/a', loop.root)).toBeNull();
  });

  it('lists case-insensitively and hides dotfiles unless asked', () => {
    expect(list(fs.root).map((n) => n.n)).toEqual([
      'ai',
      'authors',
      'blog',
      'pages',
      'README.md',
      'research-and-development',
      'tags',
    ]);
    expect(list(fs.root, true).map((n) => n.n)).toContain('.config');
  });

  it('computes a fake size from the reading time', () => {
    expect(stat(fs.root.k.ai.k['wordpress-mcp-v1.md']).size).toBe(8 * 1180);
    expect(stat(fs.root.k.ai).size).toBe(4096);
    expect(stat(fs.root.k.ai).mode).toBe('drwxr-xr-x');
  });

  it('ignores malformed nodes', () => {
    const bad = buildFs({ nodes: [{ p: 'relative', t: 'f' }, null, { t: 'f' }, { p: '/ok.md', t: 'f', text: 'x' }] });
    expect(Object.keys(bad.root.k)).toEqual(['ok.md']);
    expect(buildFs(undefined).root.k).toEqual({});
  });
});

describe('shell behaviour', () => {
  it('navigates and prints the prompt', () => {
    const s = shell();
    expect(s.prompt()).toBe('viet@jooservices:~$');
    s.run('cd ai');
    expect(s.prompt()).toBe('viet@jooservices:~/ai$');
    expect(run(s, 'pwd')).toBe('/ai');
    s.run('cd ..');
    s.run('cd -');
    expect(run(s, 'pwd')).toBe('/ai');
    expect(run(s, 'cd nope')).toBe('bash: cd: nope: No such file or directory');
    expect(run(s, 'cd prompt-caching.md')).toBe('bash: cd: prompt-caching.md: Not a directory');
  });

  it('reports unknown commands with a suggestion and the right exit code', () => {
    const s = shell();
    const r = s.run('lss');
    expect(r.exitCode).toBe(127);
    expect(plain(r)).toBe("bash: lss: command not found. Did you mean 'ls'?");
    expect(plain(s.run('qqqqq'))).toBe("bash: qqqqq: command not found. Try 'help'.");
  });

  it('rejects unsupported operators outside quotes only', () => {
    const s = shell();
    expect(s.run('ls | grep x').exitCode).toBe(2);
    expect(plain(s.run('ls > out'))).toMatch(/unsupported operator/);
    expect(s.run('echo "a | b"').exitCode).toBe(0);
    expect(run(s, 'echo "a | b"')).toBe('a | b');
  });

  it('keeps a history with up/down navigation and a cap', () => {
    const s = shell();
    s.run('ls');
    s.run('pwd');
    s.run('date');
    expect(s.navigateHistory(-1)).toBe('date');
    expect(s.navigateHistory(-1)).toBe('pwd');
    expect(s.navigateHistory(-5)).toBe('ls');
    expect(s.navigateHistory(1)).toBe('pwd');
    expect(s.navigateHistory(9)).toBe('');
    expect(run(s, 'history')).toContain('    1  ls');
    const small = createShell(data);
    small.maxHistory = 2;
    ['ls', 'pwd', 'date'].forEach((c) => small.run(c));
    expect(small.history).toEqual(['pwd', 'date']);
  });

  it('completes commands and paths', () => {
    const s = shell();
    expect(s.complete('hel')).toEqual({ line: 'help ', candidates: [] });
    expect(s.complete('c').candidates).toEqual(expect.arrayContaining(['cat', 'cd', 'clear', 'curl']));
    expect(s.complete('cat ai')).toEqual({ line: 'cat ai/', candidates: [] });
    s.run('cd ai');
    expect(s.complete('cat w')).toEqual({ line: 'cat wordpress-mcp-v1.md ', candidates: [] });
    expect(s.complete('cat p').line).toBe('cat prompt-caching.md ');
    expect(s.complete('cat r')).toEqual({ line: 'cat read-only-skills-server.md ', candidates: [] });
    expect(s.complete('cat zzz')).toEqual({ line: 'cat zzz', candidates: [] });
    expect(s.complete('cat nodir/x')).toEqual({ line: 'cat nodir/x', candidates: [] });
    const root = shell();
    expect(root.complete('ls r').candidates).toEqual(['README.md', 'research-and-development/']);
    expect(root.complete('ls .c')).toEqual({ line: 'ls .config ', candidates: [] });
  });

  it('is fully offline: results are data, never HTML strings', () => {
    const s = shell();
    const r = s.run('ls');
    expect(JSON.stringify(r)).not.toMatch(/<[a-z]/i);
    expect(r.blocks[0].kind).toBe('cols');
  });

  it('survives a throwing command', () => {
    const map = createRegistry();
    registerCommand(map, {
      name: 'boom',
      run: () => {
        throw new Error('nope');
      },
    });
    const s = new (shell().constructor)({ fs: buildFs(data), commands: map });
    const r = s.run('boom');
    expect(r.exitCode).toBe(1);
    expect(plain(r)).toBe('error: nope');
  });

  it('refuses invalid command definitions', () => {
    expect(() => registerCommand(new Map(), { name: 'Bad Name', run() {} })).toThrow(TypeError);
    expect(() => registerCommand(new Map(), { name: 'ok' })).toThrow(TypeError);
  });
});
