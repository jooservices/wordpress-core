// ls, cd, pwd, cat, head, tree, find, grep.
import { list, pathOf, target, walk } from '../fs.js';
import { line, lines, link, parseFlags, text } from '../shell.js';
import { escapeRegExp, fail, globToRegExp, longLine, nameSpan } from './util.js';

const missing = (result, cmd, arg) => fail(result, `${cmd}: cannot access '${arg}': No such file or directory`, 2);

/** Text of a post, page or file as output lines. */
export function fileLines(shell, node) {
  const file = target(shell.fs.root, node);
  if (file.post) {
    const p = file.post;
    const rows = [
      [text('# ' + p.title, 'ok')],
      [
        text('category: ', 'dim'),
        text(pathOf(file.parent) + '  '),
        text('date: ', 'dim'),
        text(p.d + '  '),
        text('read: ', 'dim'),
        text(p.m + ' min'),
      ],
      [text('tags: ', 'dim'), ...p.tg.flatMap((tag) => [link('#' + tag, 'cd /tags/' + tag, 'symlink'), text(' ')])],
      [],
      [text(p.ex)],
      [],
      [link('→ open ' + file.n, 'open ' + pathOf(file), 'cta')],
    ];
    return rows;
  }
  const rows = String(file.text ?? '')
    .split('\n')
    .map((l) => [text(l)]);
  if (file.page) rows.push([], [link('→ open ' + file.n, 'open ' + pathOf(file), 'cta')]);
  return rows;
}

export const commands = [
  {
    name: 'ls',
    usage: 'ls [-lastr] [path]',
    summary: 'list directory (categories) and posts',
    completion: 'path',
    readonly: true,
    run({ shell, args, result }) {
      const { flags, args: operands } = parseFlags(args);
      const path = operands[0];
      const node = path ? shell.lookup(path) : shell.cwd;
      if (!node) return missing(result, 'ls', path);
      if (node.t !== 'd') {
        result.blocks.push(lines([flags.l ? longLine(shell, node) : [nameSpan(shell, node)]]));
        return undefined;
      }
      const items = list(node, flags.a);
      if (flags.t) items.sort((a, b) => (b.post?.d ?? '').localeCompare(a.post?.d ?? ''));
      if (flags.S) items.sort((a, b) => (b.post?.m ?? 0) - (a.post?.m ?? 0));
      if (flags.r) items.reverse();
      if (!items.length) {
        result.blocks.push(line(text('(empty)', 'dim')));
        return undefined;
      }
      if (flags.l)
        result.blocks.push(lines([[text(`total ${items.length}`, 'dim')], ...items.map((n) => longLine(shell, n))]));
      else result.blocks.push({ kind: 'cols', items: items.map((n) => nameSpan(shell, n)) });
      return undefined;
    },
  },
  {
    name: 'cd',
    usage: 'cd [path|-|~]',
    summary: 'change directory (cd .., cd ~, cd -)',
    completion: 'path',
    readonly: true,
    run({ shell, args, result }) {
      const arg = args[0];
      if (arg === '-') {
        const back = shell.prevCwd ?? shell.fs.root;
        shell.prevCwd = shell.cwd;
        shell.cwd = back;
        result.blocks.push(line(text(pathOf(back), 'dim')));
        return;
      }
      const node = arg ? shell.lookup(arg) : shell.fs.root;
      if (!node) return fail(result, `bash: cd: ${arg}: No such file or directory`);
      if (node.t !== 'd') return fail(result, `bash: cd: ${arg}: Not a directory`);
      shell.prevCwd = shell.cwd;
      shell.cwd = node;
    },
  },
  {
    name: 'pwd',
    usage: 'pwd',
    summary: 'print working directory',
    completion: 'none',
    readonly: true,
    run({ shell, result }) {
      result.blocks.push(line(text(pathOf(shell.cwd))));
    },
  },
  {
    name: 'cat',
    usage: 'cat <file...>',
    summary: 'show a post summary or page',
    completion: 'path',
    readonly: true,
    run({ shell, args, result }) {
      if (!args.length) return fail(result, 'cat: missing file operand');
      for (const arg of args) {
        const node = shell.lookup(arg);
        if (!node) fail(result, `cat: ${arg}: No such file or directory`);
        else if (node.t === 'd') fail(result, `cat: ${arg}: Is a directory`);
        else result.blocks.push(lines(fileLines(shell, node)));
      }
      return undefined;
    },
  },
  {
    name: 'head',
    usage: 'head <file>',
    summary: 'first lines of a file',
    completion: 'path',
    readonly: true,
    run({ shell, args, result }) {
      const { args: operands } = parseFlags(args);
      if (!operands[0]) return fail(result, 'head: missing file operand');
      const node = shell.lookup(operands[0]);
      if (!node) return fail(result, `head: cannot open '${operands[0]}' for reading: No such file or directory`);
      if (node.t === 'd') return fail(result, `head: error reading '${operands[0]}': Is a directory`);
      const file = target(shell.fs.root, node);
      if (file.post)
        result.blocks.push(lines([[text('# ' + file.post.title, 'ok')], [text(file.post.ex.slice(0, 120) + '…')]]));
      else
        result.blocks.push(
          lines(
            String(file.text ?? '')
              .split('\n')
              .slice(0, 3)
              .map((l) => [text(l)])
          )
        );
      return undefined;
    },
  },
  {
    name: 'tree',
    usage: 'tree [-L n] [path]',
    summary: 'directory tree',
    completion: 'path',
    readonly: true,
    run({ shell, args, result }) {
      let depthLimit = 9;
      const operands = [];
      for (let i = 0; i < args.length; i += 1) {
        if (args[i] === '-L' && args[i + 1]) depthLimit = Math.max(1, Number(args[++i]) || 9);
        else if (args[i][0] !== '-') operands.push(args[i]);
      }
      const node = operands[0] ? shell.lookup(operands[0]) : shell.cwd;
      if (!node) return fail(result, `${operands[0]} [error opening dir]`, 2);
      let dirs = 0;
      let files = 0;
      const rows = [[link(operands[0] ?? '.', node === shell.fs.root ? 'cd ~' : 'cd ' + pathOf(node), 'dir')]];
      const walkDir = (dir, prefix, depth) => {
        if (depth >= depthLimit) return;
        const kids = list(dir);
        kids.forEach((kid, index) => {
          const last = index === kids.length - 1;
          const row = [text(prefix + (last ? '└── ' : '├── '), 'dim'), nameSpan(shell, kid)];
          if (kid.t === 'l') row.push(text(' -> ' + kid.target, 'dim'));
          rows.push(row);
          if (kid.t === 'd') {
            dirs += 1;
            walkDir(kid, prefix + (last ? '    ' : '│   '), depth + 1);
          } else files += 1;
        });
      };
      if (node.t === 'd') walkDir(node, '', 0);
      rows.push([], [text(`${dirs} directories, ${files} files`, 'dim')]);
      result.blocks.push(lines(rows));
      return undefined;
    },
  },
  {
    name: 'find',
    usage: 'find [path] -name <glob> [-type f|d]',
    summary: 'find files and directories by name',
    completion: 'path',
    readonly: true,
    run({ shell, args, result }) {
      let path = '.';
      let name = null;
      let type = null;
      for (let i = 0; i < args.length; i += 1) {
        if (args[i] === '-name' || args[i] === '-iname') name = globToRegExp(args[++i] ?? '*');
        else if (args[i] === '-type') type = args[++i];
        else if (args[i][0] !== '-') path = args[i];
      }
      const start = shell.lookup(path);
      if (!start) return fail(result, `find: '${path}': No such file or directory`, 1);
      const rows = [];
      const base = pathOf(shell.cwd);
      walk(start, (n) => {
        if (n === start && start.t === 'd' && (name || type)) return;
        if (name && !name.test(n.n)) return;
        if (type === 'd' && n.t !== 'd') return;
        if (type === 'f' && n.t === 'd') return;
        const full = n.t === 'l' ? pathOf(n.parent) + '/' + n.n : pathOf(n);
        const shown =
          path === '.' || path === './'
            ? '.' + (full === base ? '' : full.slice(base === '/' ? 0 : base.length))
            : full;
        rows.push([link(shown, (n.t === 'd' ? 'cd ' : 'cat ') + full, n.t === 'd' ? 'dir' : 'file')]);
      });
      result.blocks.push(rows.length ? lines(rows) : line(text('(no matches)', 'dim')));
      return undefined;
    },
  },
  {
    name: 'grep',
    usage: 'grep [-rin] <text> [path]',
    summary: 'search titles, excerpts and tags',
    completion: 'path',
    readonly: true,
    run({ shell, args, result }) {
      const { flags, args: operands } = parseFlags(args);
      if (!operands.length) return fail(result, 'Usage: grep [OPTION]... PATTERN [FILE]...', 2);
      const pattern = operands[0];
      const start = operands[1] ? shell.lookup(operands[1]) : shell.cwd;
      if (!start) return fail(result, `grep: ${operands[1]}: No such file or directory`, 2);
      const re = new RegExp(escapeRegExp(pattern), flags.i ? 'i' : '');
      const rows = [];
      const seen = new Set();
      walk(start, (n) => {
        const file = target(shell.fs.root, n);
        if (!file || file.t === 'd' || seen.has(pathOf(file))) return;
        const haystack = file.post ? [file.post.title, file.post.ex, file.post.tg.join(' ')] : [file.text ?? ''];
        const hit = haystack.find((t) => re.test(t));
        if (!hit) return;
        seen.add(pathOf(file));
        const shown = hit.length > 90 ? hit.slice(0, 90) + '…' : hit;
        const spans = [link(pathOf(file).replace(/^\//, ''), 'cat ' + pathOf(file), 'file'), text(': ', 'dim')];
        const rest = shown;
        const global = new RegExp(escapeRegExp(pattern), flags.i ? 'gi' : 'g');
        let last = 0;
        for (const m of rest.matchAll(global)) {
          if (m.index > last) spans.push(text(rest.slice(last, m.index)));
          spans.push(text(m[0], 'mark'));
          last = m.index + m[0].length;
        }
        if (last < rest.length) spans.push(text(rest.slice(last)));
        rows.push(spans);
      });
      if (!rows.length) {
        result.exitCode = 1;
        rows.push([text(flags.i ? '(no match)' : '(no match. grep is case-sensitive, try -i)', 'dim')]);
      }
      result.blocks.push(lines(rows));
      return undefined;
    },
  },
];
