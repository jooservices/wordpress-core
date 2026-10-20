// Command registry. Every command: { name, usage, summary, completion: 'path'|'none'|'enum:a,b', readonly, hidden?, run({shell, args, result}) }.
// (Commands live in a few grouped modules instead of one file each; the metadata keeps `man` and `help` in sync.)
import { commands as files } from './commands/files.js';
import { commands as site } from './commands/site.js';
import { commands as system } from './commands/system.js';
import { registerCommand } from './command-api.js';

export { registerCommand } from './command-api.js';

/** Creates the default registry (a Map ordered by registration). */
export function createRegistry(extra = []) {
  const map = new Map();
  for (const command of [...files, ...site, ...system, ...extra]) registerCommand(map, command);
  return map;
}
