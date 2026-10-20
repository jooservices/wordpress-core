// Command registry. Every command: { name, usage, summary, completion: 'path'|'none'|'enum:a,b', readonly, hidden?, run({shell, args, result}) }.
// `usage` is English-only by design: it is shell syntax (command names, flags, <placeholders>) shown by `help` and `man`, like a real man page; `summary` is translated.
// (Commands live in a few grouped modules instead of one file each; the metadata keeps `man` and `help` in sync.)
import { commands as files } from './commands/files.js';
import { commands as site } from './commands/site.js';
import { commands as system } from './commands/system.js';

/** Creates the default registry (a Map ordered by registration). */
export function createRegistry(extra = []) {
  const map = new Map();
  for (const command of [...files, ...site, ...system, ...extra]) registerCommand(map, command);
  return map;
}

/**
 * Adds a command (themes use this through the `joo:terminal-commands` hook of the dock). Existing names are replaced.
 * @returns {Map<string, object>} The registry.
 */
export function registerCommand(map, command) {
  if (
    !command ||
    typeof command.name !== 'string' ||
    !/^[a-z][a-z0-9-]*$/.test(command.name) ||
    typeof command.run !== 'function'
  ) {
    throw new TypeError('A command needs a lowercase name and a run() function');
  }
  map.set(command.name, { usage: command.name, summary: '', completion: 'none', readonly: true, ...command });
  return map;
}
