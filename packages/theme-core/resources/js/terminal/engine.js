// Pure shell engine entry: filesystem, command execution and the command registration API.
import { buildFs } from './fs.js';
import { registerCommand } from './registry.js';
import { Shell } from './shell.js';

export { buildFs, registerCommand, Shell };
