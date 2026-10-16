// Theme scaffolder: copies a reference theme into themes/<slug> and renames it.
// Usage: node tools/new-theme.mjs --name=<slug> [--title="Acme"] [--from=starter] [--dry-run]
import {
  copyFileSync,
  existsSync,
  lstatSync,
  mkdirSync,
  readFileSync,
  readdirSync,
  symlinkSync,
  writeFileSync,
} from 'node:fs';
import { dirname, join, relative } from 'node:path';
import { ROOT } from './lib/paths.mjs';

const SLUG = /^[a-z][a-z0-9-]{1,30}$/;
const SKIP = new Set(['core', 'node_modules', 'build', 'demo', '.DS_Store']);
const TEXT = /\.(php|json|css|html|md|po|pot|js|mjs|ts|txt|yml|yaml)$/;

export const studly = (slug) =>
  slug
    .split('-')
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join('');
export const titleOf = (slug) =>
  slug
    .split('-')
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(' ');

/** Applies the renames to a text. `from` is the source slug (starter), `to` the new slug. */
export function rewrite(text, { from, to, title }) {
  const fromStudly = studly(from);
  const toStudly = studly(to);
  return text
    .replaceAll(`JOOservices\\${fromStudly}`, `JOOservices\\${toStudly}`)
    .replaceAll(`${fromStudly}Test`, `${toStudly}Test`)
    .replaceAll(`@jooservices/theme-${from}`, `@jooservices/theme-${to}`)
    .replaceAll(`JOO_TEST_THEME=${from}`, `JOO_TEST_THEME=${to}`)
    .replaceAll(`/workspace/themes/${from}`, `/workspace/themes/${to}`)
    .replaceAll(titleOf(from), title)
    .replaceAll(from, to);
}

/** Adds the theme's text domain to phpcs.xml.dist and its inc/ folder to deptrac.yaml (idempotent). */
export function registerTooling(slug, { phpcs, deptrac }) {
  const domain = `\t\t\t\t<element value="${slug}"/>\n`;
  const anchor = '\t\t\t\t<element value="jooservices-resume"/>\n';
  const nextPhpcs = phpcs.includes(`<element value="${slug}"/>`) ? phpcs : phpcs.replace(anchor, domain + anchor);
  const path = `    - ./themes/${slug}/inc\n`;
  const nextDeptrac = deptrac.includes(path) ? deptrac : deptrac.replace('  analyser:', path + '  analyser:');
  return { phpcs: nextPhpcs, deptrac: nextDeptrac };
}

/**
 * Lists the files to create.
 *
 * @param {{ root?: string, name: string, title?: string, from?: string }} options Scaffold options.
 * @returns {Array<{ from: string, to: string, text: string | null }>} Files to write.
 */
export function plan({ root = ROOT, name, title, from = 'starter' }) {
  if (!SLUG.test(name)) throw new Error(`invalid slug "${name}" (use ${SLUG})`);
  const source = join(root, 'themes', from);
  const target = join(root, 'themes', name);
  if (!existsSync(source)) throw new Error(`source theme not found: themes/${from}`);
  if (existsSync(target)) throw new Error(`themes/${name} already exists; refusing to overwrite`);
  const ctx = { from, to: name, title: title ?? titleOf(name) };
  const out = [];
  const walk = (dir) => {
    for (const entry of readdirSync(dir)) {
      if (SKIP.has(entry)) continue;
      const path = join(dir, entry);
      if (lstatSync(path).isDirectory()) walk(path);
      else {
        const rel = relative(source, path);
        const renamed = rel
          .split('/')
          .map((part) => part.replaceAll(from, name))
          .join('/');
        out.push({
          from: path,
          to: join(target, renamed),
          text: TEXT.test(entry) ? rewrite(readFileSync(path, 'utf8'), ctx) : null,
        });
      }
    }
  };
  walk(source);
  return out;
}

if (import.meta.url === `file://${process.argv[1]}`) {
  const args = Object.fromEntries(
    process.argv.slice(2).map((a) => {
      const [k, v] = a.replace(/^--/, '').split('=');
      return [k, v ?? true];
    })
  );
  try {
    const files = plan({
      name: String(args.name ?? ''),
      title: args.title ? String(args.title) : undefined,
      from: String(args.from ?? 'starter'),
    });
    if (args['dry-run']) {
      for (const f of files) console.log(`create ${relative(ROOT, f.to)}`);
      console.log(`${files.length} files (dry run, nothing written)`);
      process.exit(0);
    }
    for (const f of files) {
      mkdirSync(dirname(f.to), { recursive: true });
      if (f.text === null) copyFileSync(f.from, f.to);
      else writeFileSync(f.to, f.text);
    }
    const target = join(ROOT, 'themes', String(args.name));
    mkdirSync(join(target, 'demo'), { recursive: true });
    writeFileSync(join(target, 'demo', '.gitkeep'), '');
    symlinkSync('../../packages/theme-core', join(target, 'core'));
    const name = String(args.name);
    const phpcsPath = join(ROOT, 'phpcs.xml.dist');
    const deptracPath = join(ROOT, 'deptrac.yaml');
    const tooling = registerTooling(name, {
      phpcs: readFileSync(phpcsPath, 'utf8'),
      deptrac: readFileSync(deptracPath, 'utf8'),
    });
    writeFileSync(phpcsPath, tooling.phpcs);
    writeFileSync(deptracPath, tooling.deptrac);
    const registry = join(ROOT, 'tests/matrix/themes.json');
    const list = existsSync(registry) ? JSON.parse(readFileSync(registry, 'utf8')) : { themes: [] };
    if (!list.themes.includes(args.name)) list.themes.push(String(args.name));
    list.themes.sort();
    writeFileSync(registry, `{\n  "themes": [${list.themes.map((t) => JSON.stringify(t)).join(', ')}]\n}\n`);
    console.log(`Created themes/${args.name} (${files.length} files).`);
    console.log(`Next: make demo THEME=${args.name}   then   make health THEME=${args.name}`);
  } catch (error) {
    console.error(`new-theme: ${error.message}`);
    process.exit(1);
  }
}
