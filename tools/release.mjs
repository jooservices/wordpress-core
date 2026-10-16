// Build reproducible ThemeForest deliverables for the JOOservices theme and Resume plugin.
// Usage: node tools/release.mjs [--theme jooservices] [--tag]
import { execFileSync } from 'node:child_process';
import { copyFileSync, existsSync, mkdirSync, readFileSync, rmSync, statSync, writeFileSync } from 'node:fs';
import { basename, join, relative } from 'node:path';
import { fromRoot, ROOT } from './lib/paths.mjs';
import { compareArtifacts, archiveEntries, normalizeTimestamps, sha256, sortedFiles } from './lib/reproducible.mjs';
import { checkRelease, readReleaseMetadata } from './release-check.mjs';

function runNodeTool(file, args = []) {
  execFileSync(process.execPath, [fromRoot(file), ...args], { cwd: ROOT, stdio: 'inherit' });
}

function git(args, { allowFailure = false } = {}) {
  try {
    return execFileSync('git', args, { cwd: ROOT, encoding: 'utf8' }).trim();
  } catch (error) {
    if (allowFailure) return '';
    throw error;
  }
}

export function readVersions(themeDir, pluginMain, pluginClass, pluginReadme) {
  const style = readFileSync(join(themeDir, 'style.css'), 'utf8');
  const changelog = readFileSync(join(themeDir, 'CHANGELOG.md'), 'utf8');
  const manifest = JSON.parse(readFileSync(join(themeDir, 'joo-theme.json'), 'utf8'));
  const pluginHeader = /^\s*\*\s*Version:\s*(\S+)/m.exec(pluginMain)?.[1] ?? '';
  return {
    theme: /^Version:\s*(\S+)/m.exec(style)?.[1] ?? '',
    themeChangelog: /^## (\d+\.\d+\.\d+)\b/m.exec(changelog)?.[1] ?? '',
    plugin: pluginHeader,
    pluginConstant: /public const VERSION\s*=\s*'([^']+)'/.exec(pluginClass)?.[1] ?? '',
    pluginStableTag: /^Stable tag:\s*(\S+)/m.exec(pluginReadme)?.[1] ?? '',
    coreRange: String(manifest.core ?? ''),
  };
}

export function problems(versions) {
  const out = [];
  for (const [name, value] of Object.entries({ theme: versions.theme, plugin: versions.plugin })) {
    if (!/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)$/.test(value))
      out.push(`${name} version "${value}" is not strict semver`);
  }
  if (versions.themeChangelog !== versions.theme) out.push('theme changelog version differs from style.css');
  if (versions.pluginConstant !== versions.plugin) out.push('plugin version constant differs from plugin header');
  if (versions.pluginStableTag !== versions.plugin) out.push('plugin Stable tag differs from plugin header');
  if (!versions.coreRange) out.push('theme manifest has no core compatibility range');
  return out;
}

/** Generates release notes from JOOS issue-keyed commits, with a changelog fallback. */
export function releaseNotes(subjects, fallback) {
  const changes = subjects.filter((subject) => /^JOOS-\d+:\s+\S/.test(subject));
  if (changes.length === 0) return fallback.trim();
  return `# Release notes\n\n${changes.map((subject) => `- ${subject}`).join('\n')}\n`;
}

/** Creates a stable checksum file for all files under a package directory. */
export function checksumManifest(root) {
  return (
    sortedFiles(root)
      .map((file) => `${sha256(readFileSync(file))}  ${relative(root, file).replaceAll('\\', '/')}`)
      .join('\n') + '\n'
  );
}

function writeZip(directory, output, { includeRoot = true } = {}) {
  normalizeTimestamps(directory);
  execFileSync('zip', ['-q', '-X', output, ...archiveEntries(directory, { includeRoot })], {
    cwd: includeRoot ? join(directory, '..') : directory,
  });
}

/** Replaces owner and employer details in the local demo export with fictional data. */
export function sanitizeDemoXml(sourceXml, companies) {
  let xml = sourceXml;
  companies.forEach((company, index) => {
    if (company.url) xml = xml.split(company.url).join('https://example.invalid');
    const slug = company.name
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-|-$/g, '');
    xml = xml.replace(/<wp:post_name><!\[CDATA\[([^\]]*)\]\]><\/wp:post_name>/g, (entry, value) =>
      value.toLowerCase() === slug ? `<wp:post_name><![CDATA[example-company-${index + 1}]]></wp:post_name>` : entry
    );
    const escapedName = company.name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    xml = xml.replace(new RegExp(escapedName, 'gi'), `Example Company ${index + 1}`);
  });
  xml = xml
    .replaceAll(
      '<wp:author_login><![CDATA[viet]]></wp:author_login>',
      '<wp:author_login><![CDATA[taylor]]></wp:author_login>'
    )
    .replaceAll(
      '<wp:author_email><![CDATA[viet@example.test]]></wp:author_email>',
      '<wp:author_email><![CDATA[taylor@example.test]]></wp:author_email>'
    )
    .replaceAll(
      '<wp:author_display_name><![CDATA[Viet VU]]></wp:author_display_name>',
      '<wp:author_display_name><![CDATA[Taylor Example]]></wp:author_display_name>'
    )
    .replaceAll(
      '<wp:author_first_name><![CDATA[Viet]]></wp:author_first_name>',
      '<wp:author_first_name><![CDATA[Taylor]]></wp:author_first_name>'
    )
    .replaceAll(
      '<wp:author_last_name><![CDATA[VU]]></wp:author_last_name>',
      '<wp:author_last_name><![CDATA[Example]]></wp:author_last_name>'
    )
    .replaceAll('<dc:creator><![CDATA[viet]]></dc:creator>', '<dc:creator><![CDATA[taylor]]></dc:creator>')
    .replace(/jooservices\.com/gi, 'example.test')
    .replaceAll('https://github.com/jooservices', 'https://example.invalid')
    .replaceAll('https://www.linkedin.com/in/jooservices', 'https://example.invalid');
  return xml;
}

export function createDemoContent(source, target) {
  const generatorData = JSON.parse(readFileSync(fromRoot('tools/wxr-generator/data/topics.json'), 'utf8'));
  mkdirSync(join(target, 'media'), { recursive: true });
  const xml = sanitizeDemoXml(readFileSync(join(source, 'content.xml'), 'utf8'), generatorData.companies);
  writeFileSync(join(target, 'content.xml'), xml);

  for (const file of sortedFiles(join(source, 'media'))) copyFileSync(file, join(target, 'media', basename(file)));
  const settings = JSON.parse(readFileSync(join(source, 'settings.json'), 'utf8'));
  settings.profile.user = 'taylor';
  settings.profile.meta.joo_title = 'Software Engineer';
  settings.profile.meta.joo_short_bio = 'A fictional technical writer exploring software and systems.';
  settings.profile.meta.joo_socials = [];
  settings.options.blogname = 'JOOservices Demo';
  settings.options.blogdescription = 'Synthetic technical posts for the JOOservices theme';
  writeFileSync(join(target, 'menus.json'), readFileSync(join(source, 'menus.json')));
  writeFileSync(join(target, 'settings.json'), `${JSON.stringify(settings, null, 2)}\n`);
  const sourceManifest = JSON.parse(readFileSync(join(source, 'manifest.json'), 'utf8'));
  const sourceMenus = JSON.parse(readFileSync(join(source, 'menus.json'), 'utf8'));
  const manifest = {
    ...sourceManifest,
    package: 'jooservices-demo-content',
    packageVersion: 1,
    tier: 'B',
    license: 'CC-BY-4.0',
    locale: 'en_US',
    languages: ['en', 'vi'],
    generatedMedia: true,
  };
  writeFileSync(join(target, 'manifest.json'), `${JSON.stringify(manifest, null, 2)}\n`);
  writeFileSync(
    join(target, 'language.json'),
    `${JSON.stringify({ locale: 'en_US', content: ['en', 'vi'] }, null, 2)}\n`
  );
  writeFileSync(join(target, 'widgets.json'), `${JSON.stringify({ sidebars: [], widgets: [] }, null, 2)}\n`);
  writeFileSync(
    join(target, 'patterns.json'),
    `${JSON.stringify({ source: 'JOOservices theme', registeredWithTheme: true, patterns: [] }, null, 2)}\n`
  );
  writeFileSync(
    join(target, 'theme-options.json'),
    `${JSON.stringify({ theme_mods: { nav_menu_locations: ['primary', 'footer'] } }, null, 2)}\n`
  );
  const posts = {
    post: {
      publish: sourceManifest.counts.posts.publish + sourceManifest.counts.posts.protected,
      draft: sourceManifest.counts.posts.draft,
      private: sourceManifest.counts.posts.private,
      inherit: 0,
    },
    page: { publish: sourceManifest.counts.pages },
    joo_company: { publish: sourceManifest.counts.companies },
    attachment: { inherit: sourceManifest.counts.attachments },
    wp_navigation: { publish: 1 },
    nav_menu_item: {
      publish: Object.values(sourceMenus).reduce((total, entries) => total + countMenuEntries(entries), 0),
    },
  };
  const structure = {
    postTypes: posts,
    terms: { category: sourceManifest.counts.categories, post_tag: sourceManifest.counts.tags },
    comments: sourceManifest.counts.comments,
    users: { author: 1, editor: 1 },
    menus: sourceMenus,
    options: settings.options,
    themeOptions: {
      nav_menu_locations: { primary: 'joo-primary', footer: 'joo-footer' },
      profile_user: settings.profile.user,
    },
    language: { locale: 'en_US', content: ['en', 'vi'] },
  };
  writeFileSync(join(target, 'structure.json'), `${JSON.stringify(structure, null, 2)}\n`);
  writeFileSync(
    join(target, 'LICENSES.md'),
    [
      '# Demo content licences',
      '',
      'This is the licence-clean Tier B buyer demo prepared for the JOOservices ThemeForest download.',
      '',
      '- Original sample text in `content.xml` is generated locally from the JOOservices demo generator and is licensed CC BY 4.0. Buyers may copy, adapt, publish, and use it commercially with attribution to “JOOservices Demo Content”.',
      '- All PNG files in `media/` are locally generated gradient images and are licensed CC BY 4.0. Buyers may reuse them with the same attribution.',
      '- Names, biographies, company records, email addresses, and links in the package are fictional examples. No owner or employer records are included.',
      '- No third-party article text, photos, icons, video, or externally hosted media is included.',
      '- WordPress blocks and the navigation structure are data for the GPL-compatible JOOservices product; see the product licence supplied with the theme and plugin.',
      '',
      'Licence reference: Creative Commons Attribution 4.0 International (CC BY 4.0). Include the attribution statement above when reusing sample text or generated images.',
      '',
    ].join('\n')
  );
  writeFileSync(
    join(target, 'ATTRIBUTION.md'),
    [
      '# Demo content attribution',
      '',
      'The following files contain original material generated for this package by JOOservices. Each is licensed CC BY 4.0; attribution: “JOOservices Demo Content”.',
      '',
      '- `content.xml` — synthetic English and Vietnamese posts, pages, fictional company records, and placeholder profile data.',
      ...(sourceManifest.expected.mediaFiles
        ? Array.from({ length: sourceManifest.expected.mediaFiles }, (_, index) => {
            const width = index % 3 === 2 ? 800 : 1600;
            const height = index % 3 === 2 ? 600 : 900;
            return `- \`media/demo-${String(index + 1).padStart(2, '0')}-${width}x${height}.png\` — locally generated gradient image.`;
          })
        : []),
      '',
      'Video: none. Third-party media: none.',
      '',
    ].join('\n')
  );
  writeFileSync(
    join(target, 'README.md'),
    [
      '# One-click demo import',
      '',
      'This package contains generated sample writing, fictional company records, example.test email addresses, and locally generated gradient images. It contains no live-site export, externally sourced articles, or third-party photographs.',
      '',
      'Recommended: activate JOOservices Resume and JOOservices, then run `wp joo demo import /path/to/jooservices-demo-content.zip --dry-run` followed by `wp joo demo import /path/to/jooservices-demo-content.zip`. The importer checks package files, shows conflicts, reports progress, and records only items it creates. Use `wp joo demo diff /path/to/jooservices-demo-content.zip` to compare the imported structure and `wp joo demo rollback` to remove this import.',
      '',
      'For the WordPress Importer fallback, extract the archive, copy `media/` to a public URL, replace the `__MEDIA_BASE__` placeholder in `content.xml` with that URL, then import the WXR file and enable attachment download. This fallback imports WordPress content only; apply menu and settings data from the JSON files manually.',
      'You can also import `content.xml` with `wp import` or WordPress Playground’s `importWxr` Blueprint step. Playground imports WXR content into an existing site; it does not restore the complete site from this content ZIP. See the [Playground WXR import guide](https://developer.wordpress.org/playground/handbook/guides/import-content-with-blueprints/).',
      '',
      'The package includes WXR, media, menus, settings, theme options, language metadata, widget/pattern declarations, a structural baseline, licence terms, attribution, and checksums. Its compressed archive must remain below 40 MB.',
      '',
    ].join('\n')
  );
  const checksums = Object.fromEntries(
    sortedFiles(target).map((file) => [relative(target, file).replaceAll('\\', '/'), sha256(readFileSync(file))])
  );
  writeFileSync(join(target, 'checksums.json'), `${JSON.stringify(checksums, null, 2)}\n`);
}

function countMenuEntries(entries) {
  return entries.reduce((total, entry) => total + 1 + countMenuEntries(entry.children ?? []), 0);
}

function writeDependencies(directory, themeVersion, pluginVersion) {
  const coreVersion = JSON.parse(readFileSync(fromRoot('packages/theme-core/composer.json'), 'utf8')).version;
  const text = [
    '# Runtime dependency list',
    '',
    `- JOOservices theme ${themeVersion}: WordPress 6.6+, PHP 8.1+; includes JOOservices theme core ${coreVersion} in the installable zip.`,
    `- JOOservices Resume ${pluginVersion}: WordPress 6.6+, PHP 8.1+; no Composer runtime packages.`,
    '- IBM Plex Sans variable font: SIL Open Font License 1.1; source package `@fontsource-variable/ibm-plex-sans` 5.3.0.',
    '- JetBrains Mono variable font: SIL Open Font License 1.1; source package `@fontsource-variable/jetbrains-mono` 5.3.0.',
    '- JavaScript and CSS assets are bundled into the theme. Build tools and development dependencies are not shipped.',
    '- ThemeForest / Envato Market handles marketplace purchase codes and update delivery. No JOOservices licence server or external tracking is included.',
    '',
  ].join('\n');
  writeFileSync(join(directory, 'DEPENDENCIES.md'), text);
}

function writeNotices(directory) {
  const licensing = join(directory, 'Licensing');
  mkdirSync(licensing, { recursive: true });
  copyFileSync(fromRoot('LICENSE'), join(licensing, 'LICENSE.txt'));
  copyFileSync(
    fromRoot('themes/jooservices/assets/fonts/LICENSE-IBM-Plex-Sans.txt'),
    join(licensing, 'LICENSE-IBM-Plex-Sans.txt')
  );
  copyFileSync(
    fromRoot('themes/jooservices/assets/fonts/LICENSE-JetBrains-Mono.txt'),
    join(licensing, 'LICENSE-JetBrains-Mono.txt')
  );
  writeFileSync(
    join(licensing, 'THIRD-PARTY-NOTICES.md'),
    [
      '# Third-party notices',
      '',
      '- The product theme, JOOservices Resume plugin, and bundled JOOservices theme core are licensed GPL-2.0-or-later. See `LICENSE.txt`.',
      '- IBM Plex Sans by IBM is bundled from the Fontsource variable package under SIL Open Font License 1.1. See `LICENSE-IBM-Plex-Sans.txt`.',
      '- JetBrains Mono by JetBrains is bundled from the Fontsource variable package under SIL Open Font License 1.1. See `LICENSE-JetBrains-Mono.txt`.',
      '- ThemeForest preview art is a screenshot of this theme; demo images are generated gradient PNG files. No third-party photo or article assets are included.',
      '',
    ].join('\n')
  );
}

function createThemeForestPackage({
  directory,
  sourceDemo,
  themeZip,
  pluginZip,
  themeVersion,
  pluginVersion,
  commitSubjects,
}) {
  const docs = join(directory, 'Documentation');
  const demo = join(directory, 'Demo-Content');
  const previews = join(directory, 'Preview');
  mkdirSync(docs, { recursive: true });
  mkdirSync(demo, { recursive: true });
  mkdirSync(previews, { recursive: true });

  const themeArea = join(directory, 'Theme');
  const pluginArea = join(directory, 'Plugins');
  mkdirSync(themeArea, { recursive: true });
  mkdirSync(pluginArea, { recursive: true });
  copyFileSync(themeZip, join(themeArea, basename(themeZip)));
  copyFileSync(pluginZip, join(pluginArea, basename(pluginZip)));
  for (const file of ['content-migration.md', 'deployment-runbook.md', 'demo-tour.md']) {
    copyFileSync(fromRoot('themes/jooservices/docs', file), join(docs, file));
  }
  const compatibilityMatrix = readFileSync(fromRoot('themes/jooservices/docs/compatibility-matrix.md'), 'utf8').replace(
    '[plugin results](../../../docs/compatibility.md)',
    '[plugin results](plugin-compatibility.md)'
  );
  writeFileSync(join(docs, 'compatibility-matrix.md'), compatibilityMatrix);
  const pluginCompatibility = readFileSync(fromRoot('docs/compatibility.md'), 'utf8').replace(
    '[product compatibility matrix](../themes/jooservices/docs/compatibility-matrix.md)',
    '[product compatibility matrix](compatibility-matrix.md)'
  );
  writeFileSync(join(docs, 'plugin-compatibility.md'), pluginCompatibility);
  writeFileSync(
    join(docs, 'README.md'),
    [
      '# JOOservices buyer guide',
      '',
      `This download contains JOOservices theme ${themeVersion} and the separate JOOservices Resume plugin ${pluginVersion}.`,
      '',
      '## Install',
      '',
      '1. In WordPress, install the zip under Appearance > Themes > Add New > Upload Theme.',
      '2. Install `Plugins/jooservices-resume-*.zip` under Plugins > Add New > Upload Plugin, then activate it.',
      '3. To import the included synthetic content, follow `../Demo-Content/README.md` and use the enclosed `jooservices-demo-content.zip` with WP-CLI.',
      '4. In Appearance > Editor, choose a dark or light style. The imported package assigns the primary and footer menus.',
      '5. Replace the fictional sample profile and company records with your own information.',
      '',
      '## Requirements and support',
      '',
      'WordPress 6.6 or newer and PHP 8.1 or newer are required. See the included compatibility matrix, plugin compatibility table, and deployment runbook. ThemeForest is the update channel. The theme makes no external licence or tracking request.',
      '',
      '## Customization',
      '',
      'Use the Site Editor for templates, template parts, navigation, and style variations. Use a child theme for PHP or CSS overrides so parent updates do not replace them. The theme supports English and Vietnamese.',
      '',
    ].join('\n')
  );
  writeFileSync(
    join(docs, 'README.html'),
    [
      '<!doctype html>',
      '<html lang="en"><head><meta charset="utf-8"><title>JOOservices buyer guide</title></head><body>',
      `<h1>JOOservices buyer guide</h1><p>This download contains JOOservices theme ${themeVersion} and the separate JOOservices Resume plugin ${pluginVersion}.</p>`,
      '<h2>Install</h2><ol><li>Install the theme zip under Appearance &gt; Themes &gt; Add New &gt; Upload Theme.</li><li>Install and activate the separate Resume zip under Plugins &gt; Add New &gt; Upload Plugin.</li><li>Follow the Demo-Content README and use its enclosed demo ZIP with WP-CLI to load the sample site.</li><li>Choose the dark or light style in Appearance &gt; Editor.</li><li>Replace the fictional sample profile and company records with your own information.</li></ol>',
      '<h2>Requirements and support</h2><p>WordPress 6.6 or newer and PHP 8.1 or newer are required. See the included compatibility matrix and deployment runbook. ThemeForest handles marketplace update delivery. The theme makes no external licence or tracking request.</p>',
      '<h2>Customization</h2><p>Use the Site Editor for templates, template parts, navigation, and style variations. Use a child theme for PHP or CSS overrides. The theme supports English and Vietnamese.</p>',
      '</body></html>',
      '',
    ].join('\n')
  );

  const releaseLog = releaseNotes(commitSubjects, readFileSync(fromRoot('docs/releases/CHANGELOG.md'), 'utf8'));
  writeFileSync(join(directory, 'CHANGELOG.md'), releaseLog);
  writeFileSync(
    join(directory, 'Item-Description.txt'),
    readFileSync(fromRoot('docs/releases/themeforest-description.txt'))
  );
  writeDependencies(directory, themeVersion, pluginVersion);
  writeNotices(directory);

  createDemoContent(sourceDemo, demo);
  copyFileSync(fromRoot('themes/jooservices/screenshot.png'), join(previews, '02_jooservices-screenshot.png'));
  copyFileSync(
    fromRoot('themes/jooservices/release-assets/preview/01_jooservices.png'),
    join(previews, '01_jooservices.png')
  );
  writeFileSync(
    join(previews, 'README.md'),
    'Upload `01_jooservices.png` first as the 590x300 preview image. The original 1200x900 theme screenshot is supplied as an additional image. Confirm current ThemeForest image and thumbnail rules immediately before submission.\n'
  );
}

function createPreviewZip({ directory, output }) {
  const previewRoot = join(directory, 'preview-upload');
  mkdirSync(previewRoot, { recursive: true });
  copyFileSync(
    fromRoot('themes/jooservices/release-assets/preview/01_jooservices.png'),
    join(previewRoot, '01_jooservices.png')
  );
  copyFileSync(fromRoot('themes/jooservices/screenshot.png'), join(previewRoot, '02_jooservices-screenshot.png'));
  writeZip(previewRoot, output, { includeRoot: false });
}

function subjectsSinceRelease(gitContext = null) {
  if (gitContext) return gitContext.subjects;
  const tags = git(['tag', '--sort=-creatordate', '--list', 'theme-*']).split('\n').filter(Boolean);
  const args = ['log', '--format=%s', '-n', '80'];
  if (tags[0]) args.splice(1, 0, `${tags[0]}..HEAD`);
  return git(args).split('\n').filter(Boolean);
}

function releaseTagExists(tag) {
  return Boolean(git(['tag', '--list', tag]));
}

function createLocalTags(themeVersion, pluginVersion) {
  const tags = [
    { name: `theme-${themeVersion}`, message: `JOOservices theme ${themeVersion}` },
    { name: `plugin-${pluginVersion}`, message: `JOOservices Resume ${pluginVersion}` },
  ];
  const existing = tags.filter(({ name }) => releaseTagExists(name)).map(({ name }) => name);
  if (existing.length) throw new Error(`release tags already exist: ${existing.join(', ')}`);
  for (const tag of tags)
    execFileSync('git', ['tag', '-a', tag.name, '-m', tag.message], { cwd: ROOT, stdio: 'inherit' });
  return tags.map(({ name }) => name);
}

function buildCandidate({ buildNumber, slug, themeVersion, pluginVersion, commitSubjects, tempRoot }) {
  const workspace = join(tempRoot, `build-${buildNumber}`);
  rmSync(workspace, { recursive: true, force: true });
  mkdirSync(workspace, { recursive: true });
  runNodeTool('tools/build-theme.mjs', ['--theme', slug]);
  runNodeTool('tools/build-plugin.mjs', ['--plugin', 'jooservices-resume']);

  const sourceDemo = join(workspace, 'source-demo');
  execFileSync(process.execPath, [fromRoot('tools/wxr-generator/generate.mjs'), '--seed', '42', '--out', sourceDemo], {
    cwd: ROOT,
    stdio: 'inherit',
  });
  const themeZip = fromRoot('artifacts/package', `${slug}-${themeVersion}.zip`);
  const pluginZip = fromRoot('artifacts/package', `jooservices-resume-${pluginVersion}.zip`);
  if (!existsSync(themeZip) || !existsSync(pluginZip))
    throw new Error('installable package builder did not produce both zips');

  const packageRoot = join(workspace, `jooservices-themeforest-${themeVersion}`);
  createThemeForestPackage({
    directory: packageRoot,
    sourceDemo,
    themeZip,
    pluginZip,
    themeVersion,
    pluginVersion,
    commitSubjects,
  });
  const demoZip = join(workspace, `jooservices-demo-content-${themeVersion}.zip`);
  writeZip(join(packageRoot, 'Demo-Content'), demoZip, { includeRoot: false });
  if (statSync(demoZip).size >= 40 * 1024 * 1024) {
    throw new Error(`demo content package exceeds the 40 MB limit (${statSync(demoZip).size} bytes)`);
  }
  copyFileSync(demoZip, join(packageRoot, 'Demo-Content', 'jooservices-demo-content.zip'));
  writeFileSync(join(packageRoot, 'SHA256SUMS'), checksumManifest(packageRoot));
  const packageZip = join(workspace, `jooservices-themeforest-${themeVersion}.zip`);
  writeZip(packageRoot, packageZip);
  const previewZip = join(workspace, `jooservices-themeforest-preview-${themeVersion}.zip`);
  createPreviewZip({ directory: workspace, output: previewZip });

  const artifactPaths = {
    [`${slug}-${themeVersion}.zip`]: themeZip,
    [`jooservices-resume-${pluginVersion}.zip`]: pluginZip,
    [`jooservices-demo-content-${themeVersion}.zip`]: demoZip,
    [`jooservices-themeforest-${themeVersion}.zip`]: packageZip,
    [`jooservices-themeforest-preview-${themeVersion}.zip`]: previewZip,
  };
  return Object.fromEntries(Object.entries(artifactPaths).map(([name, path]) => [name, readFileSync(path)]));
}

function writeEvidence({ outputDir, versions, commit, comparisons, tags, latestWordPress }) {
  const rows = comparisons
    .map(({ name, firstHash, identical }) => `| ${name} | ${firstHash} | ${identical ? 'Pass' : 'Fail'} |`)
    .join('\n');
  const text = [
    `# Release build evidence: JOOservices ${versions.theme} / Resume ${versions.plugin}`,
    '',
    `Date: ${new Date().toISOString()}. Commit: ${commit}.`,
    `Tested up to: ${versions.testedUpTo}. Release gates WordPress version supplied: ${latestWordPress || 'not supplied'}.`,
    'Sales channel: ThemeForest. Licence delivery: ThemeForest purchase code and marketplace updates; no JOOservices licence endpoint.',
    '',
    '## Checks',
    '',
    '- Release metadata, GPL headers, WordPress/PHP requirements, changelog mirrors, and the ThemeForest preview dimensions: Pass.',
    '- Build source: committed checkout; installable theme and Resume zips built separately.',
    '- Release package: ThemeForest download includes theme, plugin, buyer docs, GPL and font notices, synthetic demo content, preview assets, item description, changelog, and dependency list.',
    '- The demo package is generated from seed 42; included sample text and gradient images are locally generated and the exported profile/company records are fictionalized.',
    '- Reproducibility: both complete builds were byte-compared below.',
    '',
    '| Artifact | SHA-256 | Reproducible |',
    '|---|---|---|',
    rows,
    '',
    `Local tags: ${tags.length ? tags.join(', ') : 'not requested'}. No remote push was performed.`,
    '',
    'Marketplace submission, final preview review, and ThemeForest account/update acceptance remain manual steps.',
    '',
  ].join('\n');
  writeFileSync(join(outputDir, 'EVIDENCE.md'), text);
}

/** Builds, compares, checksums, and reports the complete release deliverable set. */
export function buildRelease({ slug = 'jooservices', tag = false, latestWordPress = '', gitContext = null } = {}) {
  const problems = checkRelease(ROOT, slug, { latestWordPress });
  if (problems.length) throw new Error(`release blocked:\n  ${problems.join('\n  ')}`);
  const status = gitContext ? gitContext.status : git(['status', '--porcelain', '--untracked-files=all']);
  if (status) throw new Error('release requires a clean committed checkout');

  const metadata = readReleaseMetadata(ROOT, slug);
  const themeVersion = metadata.theme.version;
  const pluginVersion = metadata.plugin.version;
  const commitSubjects = subjectsSinceRelease(gitContext);
  const tempRoot = fromRoot('artifacts', '.release-work');
  rmSync(tempRoot, { recursive: true, force: true });
  mkdirSync(tempRoot, { recursive: true });

  const first = buildCandidate({ buildNumber: 1, slug, themeVersion, pluginVersion, commitSubjects, tempRoot });
  const second = buildCandidate({ buildNumber: 2, slug, themeVersion, pluginVersion, commitSubjects, tempRoot });
  const comparisons = compareArtifacts(first, second);
  const differences = comparisons.filter(({ identical }) => !identical);
  if (differences.length)
    throw new Error(`reproducible build failed: ${differences.map(({ name }) => name).join(', ')}`);

  const outputDir = fromRoot('artifacts', 'release', slug, themeVersion);
  rmSync(outputDir, { recursive: true, force: true });
  mkdirSync(outputDir, { recursive: true });
  for (const [name, bytes] of Object.entries(first)) writeFileSync(join(outputDir, name), bytes);
  writeFileSync(
    join(outputDir, 'SHA256SUMS'),
    Object.entries(first)
      .map(([name, bytes]) => `${sha256(bytes)}  ${name}`)
      .sort()
      .join('\n') + '\n'
  );
  const tags = tag
    ? gitContext
      ? [`theme-${themeVersion}`, `plugin-${pluginVersion}`]
      : createLocalTags(themeVersion, pluginVersion)
    : [];
  writeEvidence({
    outputDir,
    versions: { theme: themeVersion, plugin: pluginVersion, testedUpTo: metadata.theme.testedUpTo },
    commit: gitContext ? gitContext.commit : git(['rev-parse', '--short', 'HEAD']),
    comparisons,
    tags,
    latestWordPress,
  });
  rmSync(tempRoot, { recursive: true, force: true });

  return { outputDir, versions: { theme: themeVersion, plugin: pluginVersion }, comparisons };
}

if (import.meta.url === `file://${process.argv[1]}`) {
  const args = process.argv.slice(2);
  const slug = args.includes('--theme') ? args[args.indexOf('--theme') + 1] : 'jooservices';
  const latestWordPress = args.includes('--wp') ? args[args.indexOf('--wp') + 1] : '';
  const gitContextIndex = args.indexOf('--git-context');
  const gitContext =
    gitContextIndex >= 0 ? JSON.parse(Buffer.from(args[gitContextIndex + 1], 'base64').toString('utf8')) : null;
  try {
    const result = buildRelease({ slug, tag: args.includes('--tag'), latestWordPress, gitContext });
    const sizes = result.comparisons
      .map(({ name }) => `${name} ${Math.round(statSync(join(result.outputDir, name)).size / 1024)} KB`)
      .join('\n');
    console.log(
      `release artifacts written to ${relative(ROOT, result.outputDir)}\n${sizes}\nreproducible: ${result.comparisons.every(({ identical }) => identical)}`
    );
  } catch (error) {
    console.error(`release failed: ${error.message}`);
    process.exit(1);
  }
}
