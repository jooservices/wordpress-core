// Terminal entry (loaded on first open): engine, commands, UI and the factory the dock uses.
import { buildFs } from './terminal/fs.js';
import { createRegistry, registerCommand } from './terminal/registry.js';
import { Shell } from './terminal/shell.js';
import { mountTerminal, safeUrl } from './terminal/ui.js';

/**
 * Creates a shell for the FS JSON.
 * @param {object} data Response of /joo/v1/terminal/fs.
 * @param {object[]} [extraCommands] Extra commands (themes).
 * @returns {Shell} The shell.
 */
export function createShell(data, extraCommands = []) {
  return new Shell({ fs: buildFs(data), commands: createRegistry(extraCommands) });
}

export { Shell, buildFs, createRegistry, mountTerminal, registerCommand, safeUrl };
