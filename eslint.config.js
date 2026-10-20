import js from '@eslint/js';
import globals from 'globals';
import jsdoc from 'eslint-plugin-jsdoc';
import joo from './tools/eslint/joo-plugin.mjs';

const unsafeHtml = [
  {
    selector: 'AssignmentExpression[left.property.name=/^(innerHTML|outerHTML)$/]',
    message: 'Assigning HTML strings is forbidden: build DOM nodes with textContent/createElement (XSS).',
  },
  {
    selector: "CallExpression[callee.property.name='insertAdjacentHTML']",
    message: 'insertAdjacentHTML is forbidden: build DOM nodes instead (XSS).',
  },
];

export default [
  {
    ignores: [
      '**/node_modules/**',
      '**/build/**',
      '**/vendor/**',
      'artifacts/**',
      'tests/tooling/fixtures/**',
      'themes/*/core/**',
      'themes/*/assets/critical/**',
    ],
  },
  js.configs.recommended,
  {
    files: ['**/*.js', '**/*.mjs'],
    languageOptions: { ecmaVersion: 2022, sourceType: 'module', globals: { ...globals.browser, ...globals.node } },
    plugins: { jsdoc },
    rules: {
      eqeqeq: 'error',
      'prefer-const': 'error',
      'no-eval': 'error',
      'no-new-func': 'error',
      'no-implied-eval': 'error',
      'no-var': 'error',
      'no-restricted-syntax': ['error', ...unsafeHtml],
      'jsdoc/check-types': 'warn',
      'no-unused-vars': ['error', { argsIgnorePattern: '^_', caughtErrors: 'none' }],
    },
  },
  {
    // Terminal engine files must stay free of DOM and network access (see JOOWT-C15.2).
    files: [
      'packages/theme-core/resources/js/terminal/shell.js',
      'packages/theme-core/resources/js/terminal/fs.js',
      'packages/theme-core/resources/js/terminal/commands/**/*.js',
    ],
    rules: {
      'no-restricted-globals': [
        'error',
        'document',
        'window',
        'fetch',
        'localStorage',
        'sessionStorage',
        'XMLHttpRequest',
        'location',
        'navigator',
      ],
    },
  },
  {
    // User-visible strings must come from the runtime config that PHP fills with __() (JOOWT-277).
    files: ['packages/theme-core/resources/js/**/*.js', 'packages/theme-core/resources/blocks/*/view.js'],
    ignores: ['**/*.test.js', '**/__fixtures__/**'],
    plugins: { joo },
    rules: { 'joo/no-untranslated-string': 'error' },
  },
];
