// Shared helpers of the command modules (pure).
import { list, pathOf, stat, target } from '../fs.js';
import { line, link, text } from '../shell.js';
import { t } from 'joo-runtime';

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

/** Error line "cmd: arg: message". */
export const fail = (result, message, code = 1) => {
  result.exitCode = code;
  result.blocks.push(line(text(message, 'err')));
};

/** Translates command copy from the dictionary required by the Terminal module and replaces named values. */
export function message(key, values = {}) {
  return Object.entries(values).reduce(
    (output, [name, value]) => output.replaceAll(`{${name}}`, String(value)),
    t(key)
  );
}

/** Clickable name of a node: directories run `cd`, files run `cat`. */
export function nameSpan(shell, node, label) {
  const real = target(shell.fs.root, node);
  const name = label ?? node.n;
  if (node.t === 'd') return link(name + '/', 'cd ' + pathOf(node), 'dir');
  const style = node.t === 'l' ? 'symlink' : 'file';
  return link(name, 'cat ' + pathOf(real ?? node), style);
}

/** "5.2K" style size. */
export function humanSize(node) {
  if (node.t === 'd') return '4.0K';
  const bytes = stat(node).size;
  return bytes >= 1024 ? (bytes / 1024).toFixed(1) + 'K' : String(bytes);
}

/** "Sep  5" from the post date. */
export function shortDate(node) {
  const date = (node.post?.d ?? '2026-01-01').split('-');
  const day = String(Number(date[2]));
  return `${MONTHS[Number(date[1]) - 1] ?? 'Jan'} ${day.padStart(2, ' ')}`;
}

/** One line of `ls -l`. */
export function longLine(shell, node) {
  const { mode } = stat(node);
  const count = node.t === 'd' ? String(Object.keys(node.k).length + 2) : '1';
  const real = target(shell.fs.root, node) ?? node;
  const spans = [
    // eslint-disable-next-line joo/no-literal-ui-string -- `ls -l` columns (mode, owner, group), not prose
    text(`${mode} ${count} ${shell.fs.site.user} joo ${humanSize(real).padStart(5, ' ')} ${shortDate(real)} `),
    nameSpan(shell, node),
  ];
  if (node.t === 'l') spans.push(text(' -> ' + node.target, 'dim'));
  return spans;
}

/** Case-insensitive glob (`*`, `?`) to a RegExp. */
export const globToRegExp = (glob) =>
  new RegExp(
    '^' +
      glob
        .replace(/[.+^${}()|[\]\\]/g, '\\$&')
        .replace(/\*/g, '.*')
        .replace(/\?/g, '.') +
      '$',
    'i'
  );

/** Escapes a string for use inside a RegExp. */
export const escapeRegExp = (value) => value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

export { list };
