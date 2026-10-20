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
    summary: message('terminal.command.help.summary', 'list the commands'),
    completion: 'none',
    readonly: true,
    run({ shell, result }) {
      const rows = [
        [
          text(
            message(
              'terminal.command.help.intro',
              'Read-only shell. Categories are directories, posts are files. Nothing is sent anywhere.'
            ),
            'dim'
          ),
        ],
      ];
      for (const c of shell.commands.values())
        if (!c.hidden) rows.push([text('  ' + c.usage.padEnd(34, ' ') + c.summary)]);
      rows.push([
        text(
          message('terminal.command.help.navigation', 'Tab completes · ↑/↓ history · click names to run them'),
          'dim'
        ),
      ]);
      result.blocks.push(lines(rows));
    },
  },
  {
    name: 'man',
    usage: 'man <command>',
    summary: message('terminal.command.man.summary', 'manual entry of a command'),
    completion: 'none',
    readonly: true,
    run({ shell, args, result }) {
      if (!args[0])
        return fail(result, message('terminal.command.man.missing_name', 'What manual page do you want?'), 1);
      const c = shell.commands.get(args[0]);
      if (!c)
        return fail(
          result,
          message('terminal.command.man.not_found', 'No manual entry for {command}', { command: args[0] }),
          16
        );
      result.blocks.push(
        lines([[text(c.name.toUpperCase() + '(1)', 'ok')], [text('  ' + c.usage)], [text('  ' + c.summary)]])
      );
      return undefined;
    },
  },
  simple('whoami', message('terminal.command.whoami.summary', 'who writes here'), (s) => s.fs.site.user),
  simple('hostname', message('terminal.command.hostname.summary', 'show the host name'), (s) => s.fs.site.host),
  simple(
    'id',
    message('terminal.command.id.summary', 'show the user'),
    (s) => `uid=1000(${s.fs.site.user}) gid=1000(joo) groups=1000(joo),27(readers)`
  ),
  {
    name: 'date',
    usage: 'date',
    summary: message('terminal.command.date.summary', 'current date and time'),
    completion: 'none',
    readonly: true,
    run: ({ result }) => result.blocks.push(line(text(new Date().toString()))),
  },
  {
    name: 'uname',
    usage: 'uname [-a]',
    summary: message('terminal.command.uname.summary', 'system name'),
    completion: 'none',
    readonly: true,
    run({ shell, args, result }) {
      /* eslint-disable joo/no-untranslated-string -- uname prints fixed system identifiers, not prose */
      result.blocks.push(
        line(
          text(
            args.includes('-a')
              ? `Linux ${shell.fs.site.host} 6.8.0-joo #1 SMP PREEMPT_DYNAMIC x86_64 GNU/Linux`
              : 'Linux'
          )
        )
      );
      /* eslint-enable joo/no-untranslated-string */
    },
  },
  {
    name: 'echo',
    usage: 'echo <text>',
    summary: message('terminal.command.echo.summary', 'print text'),
    completion: 'none',
    readonly: true,
    run: ({ args, result }) => result.blocks.push(line(text(args.join(' ')))),
  },
  {
    name: 'clear',
    usage: 'clear',
    summary: message('terminal.command.clear.summary', 'clear the screen (Ctrl+L)'),
    completion: 'none',
    readonly: true,
    run: ({ result }) => {
      result.effects = [{ type: 'clear' }];
    },
  },
  {
    name: 'history',
    usage: 'history',
    summary: message('terminal.command.history.summary', 'commands typed in this tab'),
    completion: 'none',
    readonly: true,
    run: ({ shell, result }) =>
      result.blocks.push(lines(shell.history.map((h, i) => [text(String(i + 1).padStart(5, ' ') + '  ' + h)]))),
  },
  {
    name: 'exit',
    usage: 'exit',
    summary: message('terminal.command.exit.summary', 'close the terminal'),
    completion: 'none',
    readonly: true,
    run: ({ result }) => {
      result.effects = [{ type: 'close' }];
    },
  },
  {
    name: 'logout',
    usage: 'logout',
    summary: message('terminal.command.logout.summary', 'alias of exit'),
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
    summary: message('terminal.command.which.summary', 'where a command lives'),
    completion: 'none',
    readonly: true,
    run({ shell, args, result }) {
      // eslint-disable-next-line joo/no-untranslated-string -- a file path, not prose
      if (args[0] && shell.commands.has(args[0])) result.blocks.push(line(text('/usr/bin/' + args[0])));
      else result.exitCode = 1;
    },
  },
  {
    name: 'neofetch',
    usage: 'neofetch',
    summary: message('terminal.command.neofetch.summary', 'about this site'),
    completion: 'none',
    readonly: true,
    run({ shell, result }) {
      const { site, count } = shell.fs;
      result.blocks.push(
        lines([
          [text(`${site.user}@${site.host}`, 'ok')],
          [text('--------------')],
          [text(message('terminal.command.neofetch.os', 'OS: WordPress · block theme'))],
          [text(message('terminal.command.neofetch.site', 'Site: {site}', { site: site.name }))],
          [
            text(
              message('terminal.command.neofetch.counts', 'Posts: {posts} · Directories: {directories}', {
                posts: count.posts,
                directories: count.dirs,
              })
            ),
          ],
          [text(message('terminal.command.neofetch.shell', 'Shell: joo-sh (read-only)'))],
        ])
      );
    },
  },
  {
    name: 'ping',
    usage: 'ping <host>',
    summary: message('terminal.command.ping.summary', 'always offline by design'),
    completion: 'none',
    readonly: true,
    run: ({ result }) =>
      fail(
        result,
        message(
          'terminal.command.ping.offline',
          'ping: connect: Network is unreachable (this shell is offline by design)'
        ),
        2
      ),
  },
  {
    name: 'sudo',
    usage: 'sudo <command>',
    summary: message('terminal.command.sudo.summary', 'not available'),
    completion: 'none',
    readonly: true,
    run({ shell, result }) {
      fail(
        result,
        message(
          'terminal.command.sudo.denied',
          '{user} is not in the sudoers file. This incident will be reported to /dev/null.',
          { user: shell.fs.site.user }
        ),
        1
      );
    },
  },
  ...['rm', 'rmdir', 'mv', 'cp', 'touch', 'mkdir', 'chmod', 'chown', 'ln', 'tee'].map((name) => ({
    name,
    usage: `${name} <path>`,
    summary: message('terminal.command.readonly.summary', 'refused: the file system is read-only'),
    completion: 'path',
    readonly: true,
    hidden: true,
    run({ args, result }) {
      fail(
        result,
        message('terminal.command.readonly.modify', "{command}: cannot modify '{path}': Read-only file system", {
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
    summary: message('terminal.command.readonly.summary', 'refused: the file system is read-only'),
    completion: 'path',
    readonly: true,
    hidden: true,
    run: ({ result }) =>
      result.blocks.push(
        line(text(message('terminal.command.readonly.editor', 'The file system is read-only. Try cat.'), 'dim'))
      ),
  })),
];
