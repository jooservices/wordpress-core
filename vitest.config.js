import { defineConfig } from 'vitest/config';

export default defineConfig({
  resolve: {
    alias: { 'joo-runtime': new URL('./packages/theme-core/resources/js/runtime.js', import.meta.url).pathname },
  },
  test: {
    environment: 'jsdom',
    include: [
      'packages/**/resources/**/*.test.js',
      'packages/**/js/**/*.test.js',
      'themes/**/assets/src/js/**/*.test.js',
      'tools/**/*.test.mjs',
      'tests/tools/**/*.test.mjs',
      'themes/*/tests/**/*.test.mjs',
    ],
    exclude: ['**/node_modules/**', '**/build/**', 'themes/*/core/**'],
    passWithNoTests: false,
    reporters: ['default', ['junit', { outputFile: 'artifacts/reports/vitest/junit.xml' }]],
    coverage: {
      provider: 'v8',
      reportsDirectory: 'artifacts/reports/vitest/coverage',
      reporter: ['text-summary', 'html', 'lcov'],
      include: [
        'packages/theme-core/resources/js/**/*.js',
        'themes/*/assets/src/js/**/*.js',
        'tools/docs-check.mjs',
        'tools/screenshots.mjs',
      ],
      exclude: ['**/*.test.js'],
      thresholds: { lines: 85, functions: 85, branches: 75, statements: 85 },
    },
  },
});
