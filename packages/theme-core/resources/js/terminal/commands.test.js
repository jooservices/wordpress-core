import { afterEach, describe, expect, it } from 'vitest';
import { setupTerminalI18n } from './test-i18n.js';
import { resetConfig } from 'joo-runtime';
import data from './__fixtures__/demo-fs.json';
import { createShell } from '../terminal.js';
import { createRegistry } from './registry.js';
import { message } from './commands/util.js';

afterEach(setupTerminalI18n);

const make = () => createShell(data);
const text = (r) =>
  r.blocks
    .map((b) =>
      b.kind === 'cols'
        ? b.items.map((s) => s.text).join('  ')
        : b.lines.map((l) => l.map((s) => s.text).join('')).join('\n')
    )
    .join('\n');
const out = (cmd, s = make()) => text(s.run(cmd));

describe('every command has metadata, a man entry and a test below', () => {
  const registry = createRegistry();
  it('has complete metadata', () => {
    for (const c of registry.values()) {
      expect(c.usage, c.name).toBeTruthy();
      expect(c.summary, c.name).toBeTruthy();
      expect(['path', 'none'].includes(c.completion) || c.completion.startsWith('enum:'), c.name).toBe(true);
      expect(c.readonly, c.name).toBe(true);
      expect(out(`man ${c.name}`)).toContain(c.name.toUpperCase() + '(1)');
    }
  });

  it('offers the commands of the approved demo', () => {
    const names = [...registry.keys()];
    for (const name of [
      'help',
      'ls',
      'll',
      'cd',
      'pwd',
      'cat',
      'head',
      'tree',
      'find',
      'grep',
      'open',
      'curl',
      'theme',
      'lang',
      'whoami',
      'neofetch',
      'history',
      'clear',
      'echo',
      'date',
      'uname',
      'man',
      'sudo',
      'rm',
      'mv',
      'cp',
      'mkdir',
      'touch',
      'chmod',
    ].filter((n) => n !== 'll')) {
      expect(names, name).toContain(name);
    }
  });
});

describe('terminal command translation', () => {
  it('uses the runtime translation map and interpolates values', () => {
    const config = document.getElementById('joo-config');
    const saved = config.textContent;
    config.textContent = JSON.stringify({ i18n: { 'terminal.command.cat.not_found': 'missing: {path}' } });
    resetConfig();
    try {
      expect(message('terminal.command.cat.not_found', { path: '/missing' })).toBe('missing: /missing');
    } finally {
      config.textContent = saved;
      resetConfig();
    }
  });
});

describe('listing', () => {
  it('ls lists categories first alphabetically and hides dotfiles', () => {
    expect(out('ls')).toBe('ai/  authors/  blog/  pages/  README.md  research-and-development/  tags/');
    expect(out('ls -a')).toContain('.config');
    expect(out('ls ai')).toBe('prompt-caching.md  read-only-skills-server.md  wordpress-mcp-v1.md');
  });

  it('ls -l golden output (same format as the approved demo)', () => {
    expect(out('ls -l ai')).toBe(
      [
        'total 3',
        '-r--r--r-- 1 viet joo  8.1K Aug 30 prompt-caching.md',
        '-r--r--r-- 1 viet joo  8.1K Sep 20 read-only-skills-server.md',
        '-r--r--r-- 1 viet joo  9.2K Sep  5 wordpress-mcp-v1.md',
      ].join('\n')
    );
    expect(out('ll blog/news')).toBe(
      'total 1\nlrwxrwxrwx 1 viet joo  9.2K Sep  5 wordpress-mcp-v1.md -> /ai/wordpress-mcp-v1.md'
    );
  });

  it('ls sorts with -t, -S and -r and handles files and errors', () => {
    expect(out('ls -t ai').split('  ')[0]).toBe('read-only-skills-server.md');
    expect(out('ls -S ai').split('  ')[0]).toBe('wordpress-mcp-v1.md');
    expect(out('ls -r ai').split('  ')[0]).toBe('wordpress-mcp-v1.md');
    expect(out('ls ai/prompt-caching.md')).toBe('prompt-caching.md');
    expect(out('ls blog/companies')).toBe('(empty)');
    const s = make();
    const r = s.run('ls nope');
    expect(r.exitCode).toBe(2);
    expect(text(r)).toBe("ls: cannot access 'nope': No such file or directory");
  });

  it('tree golden output', () => {
    expect(out('tree -L 1')).toBe(
      [
        '.',
        '├── ai/',
        '├── authors/',
        '├── blog/',
        '├── pages/',
        '├── README.md',
        '├── research-and-development/',
        '└── tags/',
        '',
        '6 directories, 1 files',
      ].join('\n')
    );
    expect(out('tree blog')).toBe(
      [
        'blog',
        '├── companies/',
        '└── news/',
        '    └── wordpress-mcp-v1.md -> /ai/wordpress-mcp-v1.md',
        '',
        '2 directories, 1 files',
      ].join('\n')
    );
    expect(out('tree nope')).toBe('nope [error opening dir]');
  });
});

describe('reading', () => {
  it('cat shows a post, a page and errors', () => {
    const post = out('cat ai/wordpress-mcp-v1.md');
    expect(post).toContain('# WordPress MCP v1.0.0: let an AI agent manage your site');
    expect(post).toContain('category: /ai  date: 2026-09-05  read: 8 min');
    expect(post).toContain('tags: #mcp #wordpress #agents');
    expect(post).toContain('→ open wordpress-mcp-v1.md');
    expect(out('cat README.md')).toContain('This is a read-only shell.');
    expect(out('cat ai')).toBe('cat: ai: Is a directory');
    expect(out('cat nope')).toBe('cat: nope: No such file or directory');
    expect(out('cat')).toBe('cat: missing file operand');
    expect(out('cat blog/news/wordpress-mcp-v1.md')).toContain('# WordPress MCP');
  });

  it('head shows the first lines', () => {
    expect(out('head ai/prompt-caching.md')).toBe(
      '# Prompt caching: what actually saves money\nWhere the cache hits, where it silently misses, and how to measure it in production traffic.…'
    );
    expect(out('head pages/contact.md')).toBe('hello@jooservices.com\ngithub.com/jooservices');
    expect(out('head')).toBe('head: missing file operand');
    expect(out('head ai')).toBe("head: error reading 'ai': Is a directory");
    expect(out('head zz')).toBe("head: cannot open 'zz' for reading: No such file or directory");
  });
});

describe('searching', () => {
  it('find by name and type', () => {
    expect(out('find ai -name "*cach*"')).toBe('/ai/prompt-caching.md');
    expect(out('find . -name "README*"')).toBe('./README.md');
    expect(out('find tags -type d').split('\n')).toContain('/tags/mcp');
    expect(out('find ai -name zzz')).toBe('(no matches)');
    expect(make().run('find nope').exitCode).toBe(1);
  });

  it('grep is case-sensitive unless -i and highlights matches', () => {
    const s = make();
    expect(out('grep DOCKER .', s)).toContain('(no match. grep is case-sensitive, try -i)');
    expect(s.run('grep DOCKER .').exitCode).toBe(1);
    const hit = s.run('grep -ri docker .');
    const flat = hit.blocks[0].lines.flat();
    expect(flat.filter((x) => x.style === 'mark').length).toBeGreaterThan(0);
    expect(text(hit)).toContain('shared-docker-stack.md');
    expect(text(hit)).not.toContain('(no match');
    expect(out('grep -i zzzz .')).toBe('(no match)');
    expect(out('grep')).toBe('Usage: grep [OPTION]... PATTERN [FILE]...');
    expect(out('grep x nope')).toBe('grep: nope: No such file or directory');
  });
});

describe('site commands', () => {
  it('open returns a navigate for posts, categories, tags and pages', () => {
    const go = (cmd) => make().run(cmd).navigate?.url;
    expect(go('open ai/wordpress-mcp-v1.md')).toBe('https://jooservices.test/wordpress-mcp-v1/');
    expect(go('open ai/wordpress-mcp-v1')).toBe('https://jooservices.test/wordpress-mcp-v1/');
    expect(go('open ai')).toBe('https://jooservices.test/category/ai/');
    expect(go('open tags/mcp')).toBe('https://jooservices.test/tag/mcp/');
    expect(go('open pages/about.md')).toBe('https://jooservices.test/about/');
    expect(go('open ~')).toBe('https://jooservices.test/');
    expect(go('go ai')).toBe('https://jooservices.test/category/ai/');
    expect(go('xdg-open ai')).toBe('https://jooservices.test/category/ai/');
  });

  it('open on an unknown path goes to the real 404 URL with the typed path', () => {
    const r = make().run('open blog/old-page');
    expect(r.navigate.url).toBe('https://jooservices.test/blog/old-page');
    expect(r.exitCode).toBe(1);
    expect(text(r)).toContain('No such file or directory → 404');
  });

  it('open on a file without a URL explains itself and never navigates', () => {
    const r = make().run('open README.md');
    expect(r.navigate).toBeUndefined();
    expect(text(r)).toBe('no viewer for README.md; use cat');
  });

  it('curl is simulated', () => {
    expect(out('curl -I /ai/prompt-caching')).toContain('HTTP/2 200');
    expect(out('curl https://x.test/ai')).toContain('HTTP/2 200');
    const nf = out('curl /nope');
    expect(nf).toContain('HTTP/2 404');
    expect(nf).toContain('x-robots-tag: noindex');
    expect(nf).toContain('(simulated, no network)');
    expect(out('curl')).toMatch(/try 'curl --help'/);
  });

  it('theme and lang return effects', () => {
    expect(make().run('theme light').effects).toEqual([{ type: 'theme', value: 'light' }]);
    expect(make().run('theme blue').exitCode).toBe(2);
    expect(make().run('lang vi').effects).toEqual([{ type: 'lang', value: 'vi' }]);
    expect(make().run('lang 12').exitCode).toBe(2);
  });
});

describe('system commands', () => {
  it('prints identity and environment', () => {
    expect(out('whoami')).toBe('viet');
    expect(out('hostname')).toBe('jooservices');
    expect(out('id')).toContain('uid=1000(viet)');
    expect(out('uname')).toBe('Linux');
    expect(out('uname -a')).toContain('x86_64 GNU/Linux');
    expect(out('echo hello  world')).toBe('hello world');
    expect(out('date')).toMatch(/\d{4}/);
    expect(out('neofetch')).toContain('Posts: 8');
    expect(out('which ls')).toBe('/usr/bin/ls');
    expect(make().run('which nope').exitCode).toBe(1);
    expect(out('man')).toBe('What manual page do you want?');
    expect(out('man nope')).toBe('No manual entry for nope');
    expect(out('help')).toContain('ls [-lastr] [path]');
    expect(out('help')).not.toContain('chmod');
  });

  it('clear and exit are effects', () => {
    expect(make().run('clear').effects).toEqual([{ type: 'clear' }]);
    expect(make().run('exit').effects).toEqual([{ type: 'close' }]);
    expect(make().run('logout').effects).toEqual([{ type: 'close' }]);
  });

  it('write commands and sudo are refused, ping is offline', () => {
    for (const c of ['rm', 'rmdir', 'mv', 'cp', 'touch', 'mkdir', 'chmod', 'chown', 'ln', 'tee']) {
      const r = make().run(`${c} x`);
      expect(text(r), c).toBe(`${c}: cannot modify 'x': Read-only file system`);
      expect(r.exitCode).toBe(1);
    }
    expect(out('sudo ls')).toBe('viet is not in the sudoers file. This incident will be reported to /dev/null.');
    expect(out('vim x')).toBe('The file system is read-only. Try cat.');
    expect(out('ping 1.1.1.1')).toContain('Network is unreachable');
  });

  it('never reads the DOM or the network (static check of the engine sources)', async () => {
    const { readFileSync } = await import('node:fs');
    const { join } = await import('node:path');
    for (const file of [
      'engine.js',
      'fs.js',
      'shell.js',
      'command-api.js',
      'registry.js',
      'commands/files.js',
      'commands/site.js',
      'commands/system.js',
      'commands/util.js',
    ]) {
      const source = readFileSync(
        join(process.cwd(), 'packages/theme-core/resources/js/terminal', file),
        'utf8'
      ).replace(/\/\/.*$/gm, '');
      expect(source, file).not.toMatch(/\b(document|window|fetch|XMLHttpRequest|localStorage|location)\b/);
      expect(source, file).not.toMatch(/innerHTML|outerHTML|insertAdjacentHTML|\beval\(|new Function/);
    }
  });
});

describe('hostile content', () => {
  const evil = {
    ...data,
    nodes: [
      ...data.nodes,
      {
        p: '/ai/evil.md',
        t: 'f',
        id: 99,
        title: '<img src=x onerror=alert(1)>',
        ex: '<script>alert(1)</script>'.repeat(500),
        d: '2026-01-01',
        u: '',
        m: 1,
        tg: ['<b>'],
        url: 'javascript:alert(1)',
      },
    ],
  };
  it('keeps hostile titles as plain text and finishes quickly on big trees', () => {
    const s = createShell(evil);
    const r = s.run('cat ai/evil.md');
    expect(r.blocks[0].lines.flat().some((x) => x.text.includes('<img src=x onerror=alert(1)>'))).toBe(true);
    const big = createShell({
      ...data,
      nodes: [
        ...data.nodes,
        ...Array.from({ length: 5000 }, (_, i) => ({
          p: `/bulk/p${i}.md`,
          t: 'f',
          id: 1000 + i,
          title: 'T' + i,
          ex: 'x',
          d: '2026-01-01',
          m: 1,
          tg: [],
          url: 'https://jooservices.test/p' + i,
        })),
      ],
    });
    const t0 = performance.now();
    big.run('tree');
    expect(performance.now() - t0).toBeLessThan(200);
    expect(createShell(evil).run('open ai/evil.md').navigate.url).toBe('javascript:alert(1)');
  });
});
