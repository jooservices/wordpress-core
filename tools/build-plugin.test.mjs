// @vitest-environment node
import { describe, expect, it } from 'vitest';
import { excluded, pluginHeader } from './build-plugin.mjs';

describe('build-plugin', () => {
  it('reads the plugin header', () => {
    const header = pluginHeader('<?php\n/**\n * Plugin Name: Acme\n * Version: 1.2.3\n * Requires PHP: 8.3\n */');
    expect(header['Plugin Name']).toBe('Acme');
    expect(header.Version).toBe('1.2.3');
  });
  it('excludes folders, files and nested names', () => {
    const patterns = ['tests/', 'vendor/', 'phpunit.xml', '.DS_Store'];
    expect(excluded('tests/Unit/a.php', patterns)).toBe(true);
    expect(excluded('vendor/autoload.php', patterns)).toBe(true);
    expect(excluded('src/.DS_Store', patterns)).toBe(true);
    expect(excluded('phpunit.xml', patterns)).toBe(true);
    expect(excluded('src/Plugin.php', patterns)).toBe(false);
    expect(excluded('tests-helper/a.php', patterns)).toBe(false);
  });
});
