// help, man, whoami, hostname, id, date, uname, echo, clear, history, exit, which, neofetch, ping and the read-only refusals.
import { line, lines, parseFlags, text } from '../shell.js';
import { fail, message } from './util.js';

const simple = (name, summary, output) => ({
  name,
  usage: name,
  summary,
  completion: 'none',
  readonly: true,
  run: ({ shell, result }) => result.blocks.push(line(text(output(shell)))),
});

export const commands = [
  {
    name: 'help',
    usage: 'help',
    summary: message('terminal.command.help.summary'),
    completion: 'none',
    readonly: true,
    run({ shell, result }) {
      const rows = [[text(message('terminal.command.help.intro'), 'dim')]];
      for (const c of shell.commands.values())
        if (!c.hidden) rows.push([text('  ' + c.usage.padEnd(34, ' ') + c.summary)]);
      rows.push([text(message('terminal.command.help.navigation'), 'dim')]);
      result.blocks.push(lines(rows));
    },
  },
  {
    name: 'man',
    usage: 'man <command>',
    summary: message('terminal.command.man.summary'),
    completion: 'none',
    readonly: true,
    run({ shell, args, result }) {
      if (!args[0]) return fail(result, message('terminal.command.man.missing_name'), 1);
      const c = shell.commands.get(args[0]);
      if (!c) return fail(result, message('terminal.command.man.not_found', { command: args[0] }), 16);
      result.blocks.push(
        lines([[text(c.name.toUpperCase() + '(1)', 'ok')], [text('  ' + c.usage)], [text('  ' + c.summary)]])
      );
      return undefined;
    },
  },
  simple('whoami', message('terminal.command.whoami.summary'), (s) => s.fs.site.user),
  simple('hostname', message('terminal.command.hostname.summary'), (s) => s.fs.site.host),
  simple(
    'id',
    message('terminal.command.id.summary'),
    (s) => `uid=1000(${s.fs.site.user}) gid=1000(joo) groups=1000(joo),27(readers)`
  ),
  {
    name: 'date',
    usage: 'date',
    summary: message('terminal.command.date.summary'),
    completion: 'none',
    readonly: true,
    run: ({ result }) => result.blocks.push(line(text(new Date().toString()))),
  },
  {
    name: 'uname',
    usage: 'uname [-a]',
    summary: message('terminal.command.uname.summary'),
    completion: 'none',
    readonly: true,
    run({ shell, args, result }) {
      // English by design: it imitates the output of the real `uname` command (docs/modules/terminal.md).
      /* eslint-disable joo/no-literal-ui-string */
      result.blocks.push(
        line(
          text(
            args.includes('-a')
              ? `Linux ${shell.fs.site.host} 6.8.0-joo #1 SMP PREEMPT_DYNAMIC x86_64 GNU/Linux`
              : 'Linux'
          )
        )
      );
      /* eslint-enable joo/no-literal-ui-string */
    },
  },
  {
    name: 'echo',
    usage: 'echo <text>',
    summary: message('terminal.command.echo.summary'),
    completion: 'none',
    readonly: true,
    run: ({ args, result }) => result.blocks.push(line(text(args.join(' ')))),
  },
  {
    name: 'clear',
    usage: 'clear',
    summary: message('terminal.command.clear.summary'),
    completion: 'none',
    readonly: true,
    run: ({ result }) => {
      result.effects = [{ type: 'clear' }];
    },
  },
  {
    name: 'history',
    usage: 'history',
    summary: message('terminal.command.history.summary'),
    completion: 'none',
    readonly: true,
    run: ({ shell, result }) =>
      result.blocks.push(lines(shell.history.map((h, i) => [text(String(i + 1).padStart(5, ' ') + '  ' + h)]))),
  },
  {
    name: 'exit',
    usage: 'exit',
    summary: message('terminal.command.exit.summary'),
    completion: 'none',
    readonly: true,
    run: ({ result }) => {
      result.effects = [{ type: 'close' }];
    },
  },
  {
    name: 'logout',
    usage: 'logout',
    summary: message('terminal.command.logout.summary'),
    completion: 'none',
    readonly: true,
    hidden: true,
    run: ({ result }) => {
      result.effects = [{ type: 'close' }];
    },
  },
  {
    name: 'which',
    usage: 'which <command>',
    summary: message('terminal.command.which.summary'),
    completion: 'none',
    readonly: true,
    run({ shell, args, result }) {
      // eslint-disable-next-line joo/no-literal-ui-string -- a file system path, not prose
      if (args[0] && shell.commands.has(args[0])) result.blocks.push(line(text('/usr/bin/' + args[0])));
      else result.exitCode = 1;
    },
  },
  {
    name: 'neofetch',
    usage: 'neofetch',
    summary: message('terminal.command.neofetch.summary'),
    completion: 'none',
    readonly: true,
    run({ shell, result }) {
      const { site, count } = shell.fs;
      result.blocks.push(
        lines([
          [text(`${site.user}@${site.host}`, 'ok')],
          [text('--------------')],
          [text(message('terminal.command.neofetch.os'))],
          [text(message('terminal.command.neofetch.site', { site: site.name }))],
          [
            text(
              message('terminal.command.neofetch.counts', {
                posts: count.posts,
                directories: count.dirs,
              })
            ),
          ],
          [text(message('terminal.command.neofetch.shell'))],
        ])
      );
    },
  },
  {
    name: 'ping',
    usage: 'ping <host>',
    summary: message('terminal.command.ping.summary'),
    completion: 'none',
    readonly: true,
    run: ({ result }) => fail(result, message('terminal.command.ping.offline'), 2),
  },
  {
    name: 'sudo',
    usage: 'sudo <command>',
    summary: message('terminal.command.sudo.summary'),
    completion: 'none',
    readonly: true,
    run({ shell, result }) {
      fail(result, message('terminal.command.sudo.denied', { user: shell.fs.site.user }), 1);
    },
  },
  ...['rm', 'rmdir', 'mv', 'cp', 'touch', 'mkdir', 'chmod', 'chown', 'ln', 'tee'].map((name) => ({
    name,
    usage: `${name} <path>`,
    summary: message('terminal.command.readonly.summary'),
    completion: 'path',
    readonly: true,
    hidden: true,
    run({ args, result }) {
      fail(
        result,
        message('terminal.command.readonly.modify', {
          command: name,
          path: parseFlags(args).args[0] ?? '',
        }),
        1
      );
    },
  })),
  ...['vim', 'vi', 'nano'].map((name) => ({
    name,
    usage: `${name} <file>`,
    summary: message('terminal.command.readonly.summary'),
    completion: 'path',
    readonly: true,
    hidden: true,
    run: ({ result }) => result.blocks.push(line(text(message('terminal.command.readonly.editor'), 'dim'))),
  })),
];
