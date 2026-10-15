// Release helper for a theme (and the core): checks that versions agree and the changelog has an entry,
// optionally creates a LOCAL annotated tag. It never pushes.
// Usage: node tools/release.mjs --theme jooservices [--tag]
import { execFileSync } from 'node:child_process';
import { existsSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import { fromRoot } from './lib/paths.mjs';

/** Versions found in the files of a theme. */
export function readVersions(themeDir) {
  const style = readFileSync(join(themeDir, 'style.css'), 'utf8');
  const changelogFile = join(themeDir, 'CHANGELOG.md');
  const changelog = existsSync(changelogFile) ? readFileSync(changelogFile, 'utf8') : '';
  const manifest = JSON.parse(readFileSync(join(themeDir, 'joo-theme.json'), 'utf8'));
  return {
    style: /^Version:\s*(\S+)/m.exec(style)?.[1] ?? '',
    changelog: /^## (\d+\.\d+\.\d+\S*)/m.exec(changelog)?.[1] ?? '',
    testedUpTo: /^Tested up to:\s*(\S+)/m.exec(style)?.[1] ?? '',
    coreRange: String(manifest.core ?? ''),
  };
}

/** Problems that block a release. */
export function problems(versions, { latestWordPress = '' } = {}) {
  const out = [];
  if (!/^\d+\.\d+\.\d+$/.test(versions.style)) out.push(`style.css Version "${versions.style}" is not semver`);
  if (versions.changelog !== versions.style)
    out.push(`CHANGELOG.md top entry "${versions.changelog}" differs from style.css "${versions.style}"`);
  if (!versions.coreRange) out.push('joo-theme.json has no core range');
  if (
    latestWordPress &&
    versions.testedUpTo &&
    versions.testedUpTo.split('.').slice(0, 2).join('.') !== latestWordPress.split('.').slice(0, 2).join('.')
  ) {
    out.push(`Tested up to ${versions.testedUpTo} but the gates ran on WordPress ${latestWordPress}`);
  }
  return out;
}

if (import.meta.url === `file://${process.argv[1]}`) {
  const args = process.argv.slice(2);
  const slug = args.includes('--theme') ? args[args.indexOf('--theme') + 1] : 'jooservices';
  const versions = readVersions(fromRoot('themes', slug));
  const wp = args.includes('--wp') ? args[args.indexOf('--wp') + 1] : '';
  const found = problems(versions, { latestWordPress: wp });
  if (
    execFileSync('git', ['status', '--porcelain', '--', `themes/${slug}`, 'packages/theme-core'], {
      encoding: 'utf8',
    }).trim()
  ) {
    found.push('uncommitted changes in the theme or the core');
  }
  if (found.length) {
    console.error(`release blocked:\n  ${found.join('\n  ')}`);
    process.exit(1);
  }
  const tag = `${slug}-${versions.style}`;
  console.log(`ok ${tag} (core ${versions.coreRange}, tested up to ${versions.testedUpTo})`);
  if (args.includes('--tag')) {
    execFileSync('git', ['tag', '-a', tag, '-m', `${slug} ${versions.style}`], { stdio: 'inherit' });
    console.log(`created local tag ${tag}; push it yourself when you are ready`);
  }
}
