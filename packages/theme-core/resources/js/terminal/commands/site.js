// open, curl, theme, lang: commands that talk to the page (through result effects) but never to the network.
import { pathOf, target } from '../fs.js';
import { line, lines, link, parseFlags, text } from '../shell.js';
import { fail, message } from './util.js';

/** URL of a node on the site (undefined when it has none). */
function urlOf(shell, node) {
  const real = target(shell.fs.root, node);
  if (real === shell.fs.root) return shell.fs.site.home;
  return real?.post?.url ?? real?.url ?? real?.meta?.url;
}

const openCommand = {
  name: 'open',
  usage: 'open [path]',
  summary: message('terminal.command.open.summary', 'go to that view on the site (post, category, tag, page)'),
  completion: 'path',
  readonly: true,
  run({ shell, args, result }) {
    const arg = args[0];
    const node = arg ? (shell.lookup(arg) ?? shell.lookup(arg + '.md')) : shell.cwd;
    if (!node) {
      const typed =
        '/' +
        String(arg)
          .replace(/^~?\/+/, '')
          .replace(/\.md$/, '');
      result.exitCode = 1;
      result.blocks.push(
        line(
          text(
            message('terminal.command.open.not_found', 'xdg-open: {path}: No such file or directory → 404', {
              path: arg,
            }),
            'err'
          )
        )
      );
      result.navigate = {
        url: shell.fs.site.home.replace(/\/$/, '') + typed.split('/').map(encodeURIComponent).join('/'),
      };
      return;
    }
    const url = urlOf(shell, node);
    if (!url) {
      result.exitCode = 1;
      result.blocks.push(
        line(text(message('terminal.command.open.no_viewer', 'no viewer for {name}; use cat', { name: node.n }), 'dim'))
      );
      return;
    }
    result.blocks.push(
      line(text(message('terminal.command.open.opening', 'opening {path} …', { path: pathOf(node) }), 'dim'))
    );
    result.navigate = { url };
  },
};

export const commands = [
  openCommand,
  {
    ...openCommand,
    name: 'xdg-open',
    usage: 'xdg-open [path]',
    summary: message('terminal.command.open.alias', 'alias of open'),
  },
  { ...openCommand, name: 'go', usage: 'go [path]', summary: message('terminal.command.open.alias', 'alias of open') },
  {
    name: 'curl',
    usage: 'curl [-I] <path>',
    summary: message('terminal.command.curl.summary', 'fake HTTP response for a path (no network)'),
    completion: 'path',
    readonly: true,
    run({ shell, args, result }) {
      const { args: operands } = parseFlags(args);
      if (!operands[0])
        return fail(result, message('terminal.command.curl.usage', "curl: try 'curl --help' for more information"), 2);
      let path = operands[0].replace(/^https?:\/\/[^/]+/, '');
      if (path[0] !== '/') path = '/' + path;
      const node = shell.lookup(path) ?? shell.lookup(path + '.md');
      const ok = Boolean(node);
      const headers = [
        `HTTP/2 ${ok ? '200' : '404'}`,
        'content-type: text/html; charset=utf-8',
        `cache-control: ${ok ? 'public, max-age=300' : 'no-store'}`,
        `x-robots-tag: ${ok ? 'index,follow' : 'noindex'}`,
      ];
      const rows = [
        [text(message('terminal.command.curl.simulated', '(simulated, no network)'), 'dim')],
        ...headers.map((h, i) => [text(h, i === 0 ? (ok ? 'ok' : 'err') : undefined)]),
      ];
      if (!ok)
        rows.push([link(message('terminal.command.open.link', '→ open {path}', { path }), 'open ' + path, 'cta')]);
      result.blocks.push(lines(rows));
      return undefined;
    },
  },
  {
    name: 'theme',
    usage: 'theme dark|light',
    summary: message('terminal.command.theme.summary', 'change the site theme'),
    completion: 'enum:dark,light',
    readonly: true,
    run({ args, result }) {
      if (args[0] !== 'dark' && args[0] !== 'light')
        return fail(result, message('terminal.command.theme.usage', 'usage: theme dark|light'), 2);
      result.effects = [{ type: 'theme', value: args[0] }];
      result.blocks.push(line(text(message('terminal.command.theme.changed', 'theme → {theme}', { theme: args[0] }))));
      return undefined;
    },
  },
  {
    name: 'lang',
    usage: 'lang <code>',
    summary: message('terminal.command.lang.summary', 'change the site language (for example lang en, lang vi)'),
    completion: 'none',
    readonly: true,
    run({ args, result }) {
      if (!/^[a-z]{2}(-[A-Z]{2})?$/.test(args[0] ?? ''))
        return fail(result, message('terminal.command.lang.usage', 'usage: lang <code> (for example en or vi)'), 2);
      result.effects = [{ type: 'lang', value: args[0] }];
      result.blocks.push(
        line(text(message('terminal.command.lang.changed', 'lang → {language}', { language: args[0] })))
      );
      return undefined;
    },
  },
];
