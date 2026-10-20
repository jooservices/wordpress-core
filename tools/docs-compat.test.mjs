import { describe, expect, it } from 'vitest';
import { licensedTestingSection, resultsFromCompatibilityMarkdown } from './docs-compat.mjs';

const licensedPlugins = [
  {
    key: 'wp-rocket',
    name: 'WP Rocket',
    source: 'manual',
    adapter: 'CacheAdapter in CacheAdapters.php',
    manualSteps: ['Run the WP Rocket matrix check.'],
  },
  {
    key: 'wpml',
    name: 'WPML',
    source: 'manual',
    adapter: 'WpmlAdapter implementing Multilingual',
    manualSteps: ['Run the WPML matrix check.'],
  },
];

describe('licensed plugin documentation', () => {
  it('marks unrun licensed plugins as not tested and includes their adapters and manual steps', () => {
    const section = licensedTestingSection(licensedPlugins).join('\n');

    expect(section).toContain('Not tested here: WP Rocket, WPML (licensed).');
    expect(section).toContain('No compatibility claim is made');
    expect(section).toContain('CacheAdapter in CacheAdapters.php');
    expect(section).toContain('WpmlAdapter implementing Multilingual');
    expect(section).toContain('Run the WP Rocket matrix check.');
    expect(section).toContain('Run the WPML matrix check.');
  });

  it('does not label a licensed plugin not tested after a recorded matrix result', () => {
    const results = new Map([
      ['wp-rocket', { result: 'PASS' }],
      ['wpml', { result: 'PASS' }],
    ]);

    expect(licensedTestingSection(licensedPlugins, results).join('\n')).not.toContain('Not tested here:');
  });

  it('recovers the committed result table when plugin report artifacts are unavailable', () => {
    const markdown = [
      '| Plugin | Group | Source | Tested version | Date | Result | Notes |',
      '|---|---|---|---|---|---|---|',
      '| WP Rocket | Cache | manual |  |  | SKIPPED | License not available |',
      '| WPML | Multilingual | manual | 4.7 | 2026-10-15 | PASS | Fixture |',
    ].join('\n');

    const results = resultsFromCompatibilityMarkdown(markdown, licensedPlugins);

    expect(results.get('wp-rocket')).toMatchObject({ result: 'SKIPPED', notes: ['License not available'] });
    expect(results.get('wpml')).toMatchObject({ result: 'PASS', version: '4.7', date: '2026-10-15' });
  });
});
