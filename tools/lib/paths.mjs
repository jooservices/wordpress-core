// Repository path helpers shared by the Node tools.
import { fileURLToPath } from 'node:url';
import { dirname, join, resolve } from 'node:path';

/** Absolute path of the repository root. */
export const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '..', '..');

/**
 * Resolve a path relative to the repository root.
 * @param {...string} parts Path segments.
 * @returns {string} Absolute path.
 */
export function fromRoot(...parts) {
  return join(ROOT, ...parts);
}
