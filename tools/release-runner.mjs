// Run release generation in the shared PHP/Node container with host Git context.
import { execFileSync } from 'node:child_process';
import { fromRoot, ROOT } from './lib/paths.mjs';
import { readReleaseMetadata } from './release-check.mjs';

function git(args, cwd = ROOT) {
  return execFileSync('git', args, { cwd, encoding: 'utf8' }).trim();
}

export function createGitContext(cwd = ROOT) {
  const themeTags = git(['tag', '--sort=-creatordate', '--list', 'theme-*'], cwd).split('\n').filter(Boolean);
  const logArgs = ['log', '--format=%s', '-n', '80'];
  if (themeTags[0]) logArgs.splice(1, 0, `${themeTags[0]}..HEAD`);
  return {
    status: git(['status', '--porcelain', '--untracked-files=all'], cwd),
    commit: git(['rev-parse', '--short', 'HEAD'], cwd),
    subjects: git(logArgs, cwd).split('\n').filter(Boolean),
    tags: themeTags,
  };
}

function releaseTagNames(slug) {
  const { theme, plugin } = readReleaseMetadata(ROOT, slug);
  return [
    { name: `theme-${theme.version}`, message: `JOOservices theme ${theme.version}` },
    { name: `plugin-${plugin.version}`, message: `JOOservices Resume ${plugin.version}` },
  ];
}

function createTags(tags) {
  const existing = tags.map(({ name }) => name).filter((name) => git(['tag', '--list', name]));
  if (existing.length) throw new Error(`release tags already exist: ${existing.join(', ')}`);
  for (const tag of tags) git(['tag', '-a', tag.name, '-m', tag.message]);
}

if (import.meta.url === `file://${process.argv[1]}`) {
  const args = process.argv.slice(2);
  const slug = args.includes('--theme') ? args[args.indexOf('--theme') + 1] : 'jooservices';
  const wantsTags = args.includes('--tag');
  const tags = wantsTags ? releaseTagNames(slug) : [];
  const existing = tags.map(({ name }) => name).filter((name) => git(['tag', '--list', name]));
  if (existing.length) {
    console.error(`release tags already exist: ${existing.join(', ')}`);
    process.exit(1);
  }

  const gitContext = Buffer.from(JSON.stringify(createGitContext())).toString('base64');
  const runtimeArgs = [
    fromRoot('docker/scripts/runtime.sh'),
    'php-node',
    'tools/release.mjs',
    '--git-context',
    gitContext,
    ...args,
  ];
  try {
    execFileSync('bash', runtimeArgs, { cwd: ROOT, stdio: 'inherit' });
    if (wantsTags) createTags(tags);
  } catch (error) {
    console.error(`release runner failed: ${error.message}`);
    process.exit(1);
  }
}
