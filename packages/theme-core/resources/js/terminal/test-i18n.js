import { readFileSync } from 'node:fs';
import { resetConfig } from 'joo-runtime';
import { fromRoot } from '../../../../../tools/lib/paths.mjs';

const php = readFileSync(fromRoot('packages', 'theme-core', 'src', 'I18n', 'JsStrings.php'), 'utf8');
const entries = [...php.matchAll(/'([^']+)'\s*=>\s*__\(\s*('(?:[^'\\]|\\.)*'|"(?:[^"\\]|\\.)*")/gu)];
const strings = Object.fromEntries(
  entries.map(([, key, literal]) => {
    const value = literal.slice(1, -1);
    return [key, literal[0] === "'" ? value.replace(/\\(['\\])/gu, '$1') : value.replace(/\\(["\\])/gu, '$1')];
  })
);

/** Load the same English dictionary that TerminalModule exposes through the runtime config. */
export function setupTerminalI18n() {
  let config = document.getElementById('joo-config');
  if (!config) {
    config = document.createElement('script');
    config.id = 'joo-config';
    document.body.append(config);
  }
  config.setAttribute('type', 'application/json');
  config.textContent = JSON.stringify({ i18n: strings });
  resetConfig();
}

setupTerminalI18n();
