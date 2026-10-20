/** Adds a command to a registry after validating its public contract. */
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
