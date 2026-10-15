// Stages a plugin (runtime files only, composer autoloader for the ones that need it) and zips it for a server.
// Usage: node tools/build-plugin.mjs --plugin jooservices-content|wordpress-chatgpt|all [--no-zip]
// The staged copy in artifacts/plugins/<slug> is what the Docker demo mounts, so the demo runs the same files as the zip.
import { execFileSync } from 'node:child_process';
import { cpSync, existsSync, mkdirSync, readdirSync, readFileSync, rmSync, statSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { fromRoot } from './lib/paths.mjs';
import { walk } from './runtime-bundle.mjs';

const config = JSON.parse(readFileSync(fromRoot('tools/plugins.config.json'), 'utf8')).plugins;

/** Reads `Header: value` pairs of a plugin main file. */
export function pluginHeader(php) {
  return Object.fromEntries(
    [...php.matchAll(/^\s*\*\s*([A-Za-z ]+):\s*(.+)$/gm)].map((m) => [m[1].trim(), m[2].trim()])
  );
}

/** Whether a relative path is left out of the package. */
export function excluded(file, patterns) {
  return patterns.some((p) =>
    p.endsWith('/') ? file === p.slice(0, -1) || file.startsWith(p) : file === p || file.endsWith(`/${p}`)
  );
}

/** Files of a source directory that ship. */
export function shippedFiles(dir, patterns) {
  return walk(dir).filter((f) => !excluded(f, patterns));
}

export function build(slug, { zip = true } = {}) {
  const plugin = config[slug];
  if (!plugin) throw new Error(`unknown plugin "${slug}" (known: ${Object.keys(config).join(', ')})`);
  const source = resolve(
    plugin.sourceEnv && process.env[plugin.sourceEnv] ? process.env[plugin.sourceEnv] : fromRoot(plugin.source)
  );
  if (!existsSync(join(source, plugin.mainFile)))
    throw new Error(
      `${slug}: ${join(source, plugin.mainFile)} not found (set ${plugin.sourceEnv ?? 'the source'} to the plugin folder)`
    );
  const header = pluginHeader(readFileSync(join(source, plugin.mainFile), 'utf8'));
  const version = header.Version;
  if (!/^\d+\.\d+\.\d+/.test(version ?? '')) throw new Error(`${slug}: no semver Version header`);
  const stage = fromRoot('artifacts/plugins', slug);
  // Empty the folder but keep it: a running container may have it bind-mounted.
  mkdirSync(stage, { recursive: true });
  for (const entry of readdirSync(stage)) rmSync(join(stage, entry), { recursive: true, force: true });
  for (const file of shippedFiles(source, plugin.exclude)) {
    mkdirSync(join(stage, file, '..'), { recursive: true });
    cpSync(join(source, file), join(stage, file));
  }
  if (plugin.composer) {
    // The container has composer and PHP 8.3; the staged folder is a writable mount (/workspace/artifacts).
    execFileSync(
      fromRoot('docker/scripts/compose.sh'),
      [
        'exec',
        '-T',
        '-u',
        'www-data',
        'wordpress',
        'composer',
        'install',
        '--no-dev',
        '--no-interaction',
        '--optimize-autoloader',
        '--working-dir',
        `/workspace/artifacts/plugins/${slug}`,
      ],
      { stdio: 'inherit', input: '' }
    );
  }
  rmSync(join(stage, 'composer.lock'), { force: true });
  const problems = [];
  for (const file of walk(stage)) {
    if (/\.php$/.test(file) && !file.startsWith('vendor/')) {
      try {
        execFileSync('php', ['-l', join(stage, file)], { stdio: 'ignore' });
      } catch {
        problems.push(`php -l failed: ${file}`);
      }
    }
  }
  if (plugin.composer && !existsSync(join(stage, 'vendor/autoload.php')))
    problems.push('vendor/autoload.php missing after composer install');
  if (problems.length) throw new Error(`${slug}: ${problems.join('; ')}`);
  let zipPath = '';
  if (zip) {
    mkdirSync(fromRoot('artifacts/package'), { recursive: true });
    zipPath = fromRoot('artifacts/package', `${slug}-${version}.zip`);
    rmSync(zipPath, { force: true });
    execFileSync('zip', ['-qr', '-X', zipPath, slug], { cwd: fromRoot('artifacts/plugins') });
  }
  return { slug, version, stage, zipPath, files: walk(stage).length, size: zipPath ? statSync(zipPath).size : 0 };
}

if (import.meta.url === `file://${process.argv[1]}`) {
  const args = process.argv.slice(2);
  const which = args.includes('--plugin') ? args[args.indexOf('--plugin') + 1] : 'all';
  const slugs = which === 'all' ? Object.keys(config) : [which];
  try {
    for (const slug of slugs) {
      const r = build(slug, { zip: !args.includes('--no-zip') });
      console.log(
        `built ${slug} ${r.version}: ${r.files} files${r.zipPath ? `, ${(r.size / 1024).toFixed(0)} KB zip at ${r.zipPath.replace(`${fromRoot()}/`, '')}` : ''}`
      );
    }
  } catch (error) {
    console.error(`build-plugin: ${error.message}`);
    process.exit(1);
  }
}
