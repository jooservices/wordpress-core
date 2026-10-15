// WordPress.org style theme requirements that can be checked without the Theme Check plugin:
// header fields, license, screenshot, escaping hints, no forbidden calls, text domain.
// Usage: node tools/theme-check.mjs --theme jooservices
import { existsSync, readFileSync, readdirSync, statSync } from 'node:fs';
import { join } from 'node:path';
import { fromRoot } from './lib/paths.mjs';

const REQUIRED_HEADERS = [
  'Theme Name',
  'Author',
  'Description',
  'Version',
  'Requires at least',
  'Tested up to',
  'Requires PHP',
  'License',
  'License URI',
  'Text Domain',
];
/** @type {Array<[RegExp, string]>} */
const FORBIDDEN = [
  [/\beval\s*\(/, 'eval()'],
  [/\bbase64_decode\s*\(/, 'base64_decode()'],
  [/\bfile_get_contents\s*\(\s*['"]https?:/, 'remote file_get_contents()'],
  [/<script[^>]+src=["']https?:\/\/(?!127\.0\.0\.1)/i, 'remote script'],
  [/<link[^>]+href=["']https?:\/\/fonts\.(googleapis|gstatic)/i, 'remote font'],
];

/** Problems of one theme directory. */
export function check(dir) {
  const out = [];
  const css = existsSync(join(dir, 'style.css')) ? readFileSync(join(dir, 'style.css'), 'utf8') : '';
  const header = Object.fromEntries(
    [...css.matchAll(/^([A-Za-z ]+):\s*(.+)$/gm)].map((m) => [m[1].trim(), m[2].trim()])
  );
  for (const key of REQUIRED_HEADERS) if (!header[key]) out.push(`style.css lacks "${key}"`);
  if (header.License && !/GPL/i.test(header.License)) out.push(`license "${header.License}" is not GPL compatible`);
  const slug = dir.split('/').pop();
  if (header['Text Domain'] && header['Text Domain'] !== slug)
    out.push(`Text Domain "${header['Text Domain']}" differs from the folder "${slug}"`);
  if (!existsSync(join(dir, 'screenshot.png'))) out.push('screenshot.png is missing');
  else if (statSync(join(dir, 'screenshot.png')).size > 1024 * 1024) out.push('screenshot.png is over 1 MB');
  for (const required of ['theme.json', 'templates/index.html'])
    if (!existsSync(join(dir, required))) out.push(`${required} is missing`);
  const files = [];
  const walk = (d) => {
    for (const entry of readdirSync(d, { withFileTypes: true })) {
      if (['node_modules', 'tests', 'core', 'build', 'design', 'docs', 'demo'].includes(entry.name)) continue;
      const full = join(d, entry.name);
      if (entry.isDirectory()) walk(full);
      else if (/\.(php|html|css|js)$/.test(entry.name) && !/\.min\./.test(entry.name)) files.push(full);
    }
  };
  if (existsSync(dir)) walk(dir);
  for (const file of files) {
    const text = readFileSync(file, 'utf8');
    for (const [pattern, label] of FORBIDDEN)
      if (pattern.test(text)) out.push(`${file.slice(dir.length + 1)} uses ${label}`);
  }
  return out;
}

if (import.meta.url === `file://${process.argv[1]}`) {
  const args = process.argv.slice(2);
  const slug = args.includes('--theme') ? args[args.indexOf('--theme') + 1] : 'jooservices';
  const found = check(fromRoot('themes', slug));
  if (found.length) {
    console.error(`theme check failed:\n  ${found.join('\n  ')}`);
    process.exit(1);
  }
  console.log(`ok   ${slug} passes the theme requirements`);
}
