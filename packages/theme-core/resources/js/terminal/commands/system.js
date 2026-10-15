// help, man, whoami, hostname, id, date, uname, echo, clear, history, exit, which, neofetch, ping and the read-only refusals.
import { line, lines, parseFlags, text } from '../shell.js';
import { fail } from './util.js';

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
    summary: 'list the commands',
    completion: 'none',
    readonly: true,
    run({ shell, result }) {
      const rows = [
        [text('Read-only shell. Categories are directories, posts are files. Nothing is sent anywhere.', 'dim')],
      ];
      for (const c of shell.commands.values())
        if (!c.hidden) rows.push([text('  ' + c.usage.padEnd(34, ' ') + c.summary)]);
      rows.push([text('Tab completes · ↑/↓ history · click names to run them', 'dim')]);
      result.blocks.push(lines(rows));
    },
  },
  {
    name: 'man',
    usage: 'man <command>',
    summary: 'manual entry of a command',
    completion: 'none',
    readonly: true,
    run({ shell, args, result }) {
      if (!args[0]) return fail(result, 'What manual page do you want?', 1);
      const c = shell.commands.get(args[0]);
      if (!c) return fail(result, `No manual entry for ${args[0]}`, 16);
      result.blocks.push(
        lines([[text(c.name.toUpperCase() + '(1)', 'ok')], [text('  ' + c.usage)], [text('  ' + c.summary)]])
      );
      return undefined;
    },
  },
  simple('whoami', 'who writes here', (s) => s.fs.site.user),
  simple('hostname', 'show the host name', (s) => s.fs.site.host),
  simple('id', 'show the user', (s) => `uid=1000(${s.fs.site.user}) gid=1000(joo) groups=1000(joo),27(readers)`),
  {
    name: 'date',
    usage: 'date',
    summary: 'current date and time',
    completion: 'none',
    readonly: true,
    run: ({ result }) => result.blocks.push(line(text(new Date().toString()))),
  },
  {
    name: 'uname',
    usage: 'uname [-a]',
    summary: 'system name',
    completion: 'none',
    readonly: true,
    run({ shell, args, result }) {
      result.blocks.push(
        line(
          text(
            args.includes('-a')
              ? `Linux ${shell.fs.site.host} 6.8.0-joo #1 SMP PREEMPT_DYNAMIC x86_64 GNU/Linux`
              : 'Linux'
          )
        )
      );
    },
  },
  {
    name: 'echo',
    usage: 'echo <text>',
    summary: 'print text',
    completion: 'none',
    readonly: true,
    run: ({ args, result }) => result.blocks.push(line(text(args.join(' ')))),
  },
  {
    name: 'clear',
    usage: 'clear',
    summary: 'clear the screen (Ctrl+L)',
    completion: 'none',
    readonly: true,
    run: ({ result }) => {
      result.effects = [{ type: 'clear' }];
    },
  },
  {
    name: 'history',
    usage: 'history',
    summary: 'commands typed in this tab',
    completion: 'none',
    readonly: true,
    run: ({ shell, result }) =>
      result.blocks.push(lines(shell.history.map((h, i) => [text(String(i + 1).padStart(5, ' ') + '  ' + h)]))),
  },
  {
    name: 'exit',
    usage: 'exit',
    summary: 'close the terminal',
    completion: 'none',
    readonly: true,
    run: ({ result }) => {
      result.effects = [{ type: 'close' }];
    },
  },
  {
    name: 'logout',
    usage: 'logout',
    summary: 'alias of exit',
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
    summary: 'where a command lives',
    completion: 'none',
    readonly: true,
    run({ shell, args, result }) {
      if (args[0] && shell.commands.has(args[0])) result.blocks.push(line(text('/usr/bin/' + args[0])));
      else result.exitCode = 1;
    },
  },
  {
    name: 'neofetch',
    usage: 'neofetch',
    summary: 'about this site',
    completion: 'none',
    readonly: true,
    run({ shell, result }) {
      const { site, count } = shell.fs;
      result.blocks.push(
        lines([
          [text(`${site.user}@${site.host}`, 'ok')],
          [text('--------------')],
          [text('OS: WordPress · block theme')],
          [text(`Site: ${site.name}`)],
          [text(`Posts: ${count.posts} · Directories: ${count.dirs}`)],
          [text('Shell: joo-sh (read-only)')],
        ])
      );
    },
  },
  {
    name: 'ping',
    usage: 'ping <host>',
    summary: 'always offline by design',
    completion: 'none',
    readonly: true,
    run: ({ result }) => fail(result, 'ping: connect: Network is unreachable (this shell is offline by design)', 2),
  },
  {
    name: 'sudo',
    usage: 'sudo <command>',
    summary: 'not available',
    completion: 'none',
    readonly: true,
    run({ shell, result }) {
      fail(result, `${shell.fs.site.user} is not in the sudoers file. This incident will be reported to /dev/null.`, 1);
    },
  },
  ...['rm', 'rmdir', 'mv', 'cp', 'touch', 'mkdir', 'chmod', 'chown', 'ln', 'tee'].map((name) => ({
    name,
    usage: `${name} <path>`,
    summary: 'refused: the file system is read-only',
    completion: 'path',
    readonly: true,
    hidden: true,
    run({ args, result }) {
      fail(result, `${name}: cannot modify '${parseFlags(args).args[0] ?? ''}': Read-only file system`, 1);
    },
  })),
  ...['vim', 'vi', 'nano'].map((name) => ({
    name,
    usage: `${name} <file>`,
    summary: 'refused: the file system is read-only',
    completion: 'path',
    readonly: true,
    hidden: true,
    run: ({ result }) => result.blocks.push(line(text('The file system is read-only. Try cat.', 'dim'))),
  })),
];
