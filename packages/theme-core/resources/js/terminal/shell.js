// Shell engine: tokenizer, history, completion and execution. Pure data in, structured results out: it never touches
// the DOM, the network or location (the dock applies results). Output blocks:
//   { kind: 'lines', lines: [[span, ...], ...] }   span: { text, style?: 'dim'|'err'|'ok'|'mark'|'ps', cmd?: string }
//   { kind: 'cols', items: [span, ...] }
import { display, list, pathOf, resolve } from './fs.js';

/** Plain text span. */
export const text = (value, style) => ({ text: String(value), ...(style ? { style } : {}) });
/** Clickable span that runs a command. */
export const link = (label, cmd, style) => ({ text: String(label), cmd, ...(style ? { style } : {}) });
/** Single-line output block. */
export const line = (...spans) => ({ kind: 'lines', lines: [spans] });
/** Multi-line output block. */
export const lines = (rows) => ({ kind: 'lines', lines: rows });

/**
 * Splits a command line into tokens (whitespace, single and double quotes, backslash escapes).
 * @param {string} input Command line.
 * @returns {string[]} Tokens.
 */
export function tokenize(input) {
  const tokens = [];
  let current = '';
  let quote = null;
  let started = false;
  for (let i = 0; i < input.length; i += 1) {
    const ch = input[i];
    if (quote) {
      if (ch === quote) quote = null;
      else if (ch === '\\' && quote === '"' && i + 1 < input.length) current += input[++i];
      else current += ch;
    } else if (ch === '"' || ch === "'") {
      quote = ch;
      started = true;
    } else if (ch === '\\' && i + 1 < input.length) {
      current += input[++i];
      started = true;
    } else if (/\s/.test(ch)) {
      if (current || started) tokens.push(current);
      current = '';
      started = false;
    } else {
      current += ch;
      started = true;
    }
  }
  if (current || started) tokens.push(current);
  return tokens;
}

/** Dice coefficient of two strings on bigrams (for "did you mean"). */
export function similarity(a, b) {
  const grams = (s) => {
    const map = new Map();
    for (let i = 0; i < s.length - 1; i += 1) map.set(s.slice(i, i + 2), (map.get(s.slice(i, i + 2)) ?? 0) + 1);
    return map;
  };
  if (a === b) return 1;
  if (a.length < 2 || b.length < 2) return 0;
  const x = grams(a);
  const y = grams(b);
  let overlap = 0;
  for (const [gram, count] of x) overlap += Math.min(count, y.get(gram) ?? 0);
  return (2 * overlap) / (a.length - 1 + (b.length - 1));
}

/** Splits flags (`-lat`) from operands; negative numbers stay operands. */
export function parseFlags(args) {
  const flags = {};
  const rest = [];
  for (const arg of args) {
    if (arg.length > 1 && arg[0] === '-' && Number.isNaN(Number(arg))) for (const c of arg.slice(1)) flags[c] = true;
    else rest.push(arg);
  }
  return { flags, args: rest };
}

export class Shell {
  /**
   * @param {{fs: {root: object, site: object, count: object}, commands: Map<string, object>, i18n?: object, maxHistory?: number}} options Options.
   */
  constructor({ fs, commands, i18n = {}, maxHistory = 200 }) {
    this.fs = fs;
    this.commands = commands;
    this.i18n = i18n;
    this.cwd = fs.root;
    this.prevCwd = fs.root;
    this.history = [];
    this.maxHistory = maxHistory;
    this.cursor = 0;
  }

  /** Prompt, for example "visitor@host:~/ai$". */
  prompt() {
    return `${this.fs.site.user}@${this.fs.site.host}:${display(this.cwd)}$`;
  }

  /** Looks a path up from the current directory. */
  lookup(path) {
    return resolve(this.fs.root, path, this.cwd);
  }

  /** Executes a command line. */
  run(input) {
    const raw = String(input ?? '').trim();
    const result = { blocks: [], exitCode: 0 };
    if (!raw) return result;
    this.history.push(raw);
    if (this.history.length > this.maxHistory) this.history.shift();
    this.cursor = this.history.length;
    if (/(&&|\|\||;|\||>|<)/.test(raw.replace(/(["'])(?:\\.|(?!\1).)*\1/g, ''))) {
      result.blocks.push(
        line(text('bash: syntax error: unsupported operator (pipes, redirects and && are not available)', 'err'))
      );
      result.exitCode = 2;
      return result;
    }
    const tokens = tokenize(raw);
    let name = tokens.shift();
    if (name === 'll') {
      name = 'ls';
      tokens.unshift('-l');
    }
    const command = this.commands.get(name);
    if (!command) {
      const best = [...this.commands.keys()]
        .map((key) => ({ key, score: similarity(name, key) }))
        .sort((a, b) => b.score - a.score)[0];
      result.exitCode = 127;
      result.blocks.push(
        line(
          text(
            `bash: ${name}: command not found` +
              (best && best.score > 0.4 ? `. Did you mean '${best.key}'?` : ". Try 'help'."),
            'err'
          )
        )
      );
      return result;
    }
    try {
      command.run({ shell: this, args: tokens, result });
    } catch (error) {
      result.exitCode = 1;
      result.blocks.push(line(text(`error: ${error instanceof Error ? error.message : String(error)}`, 'err')));
    }
    return result;
  }

  /** History navigation: -1 older, +1 newer. Returns the line to show. */
  navigateHistory(direction) {
    this.cursor = Math.max(0, Math.min(this.history.length, this.cursor + direction));
    return this.history[this.cursor] ?? '';
  }

  /** Tab completion. Returns the new line and the candidates (when several). */
  complete(input) {
    const parts = input.split(/\s+/);
    const last = parts[parts.length - 1];
    let candidates;
    let prefix = '';
    let base = last;
    if (parts.length === 1) {
      candidates = [...this.commands.keys()].filter((key) => key.startsWith(last)).sort();
    } else {
      const slash = last.lastIndexOf('/');
      const dirPart = slash > -1 ? last.slice(0, slash + 1) : '';
      base = last.slice(slash + 1);
      const dir = this.lookup(dirPart || '.');
      if (!dir || dir.t !== 'd') return { line: input, candidates: [] };
      prefix = dirPart;
      candidates = list(dir, base[0] === '.')
        .filter((n) => n.n.toLowerCase().startsWith(base.toLowerCase()))
        .map((n) => n.n + (n.t === 'd' ? '/' : ''));
    }
    if (!candidates.length) return { line: input, candidates: [] };
    let common = candidates[0];
    for (const c of candidates) while (!c.toLowerCase().startsWith(common.toLowerCase())) common = common.slice(0, -1);
    const head = parts.slice(0, -1).join(' ');
    const unique = candidates.length === 1;
    const completed = (head ? head + ' ' : '') + prefix + common + (unique && !common.endsWith('/') ? ' ' : '');
    return { line: completed, candidates: unique ? [] : candidates };
  }

  /** Absolute path of a node. */
  pathOf(node) {
    return pathOf(node);
  }
}
