import { Linter } from 'eslint';
import { describe, expect, it } from 'vitest';
import rule from './no-literal-ui-string.mjs';

const lint = (code) => {
  const linter = new Linter();
  return linter.verify(
    code,
    [
      {
        files: ['**/*.js'],
        languageOptions: { ecmaVersion: 2022, sourceType: 'module' },
        plugins: { joo: { rules: { 'no-literal-ui-string': rule } } },
        rules: { 'joo/no-literal-ui-string': 'error' },
      },
    ],
    { filename: 'x.js' }
  );
};

describe('joo/no-literal-ui-string', () => {
  it.each([
    "node.textContent = 'Copied';",
    'node.textContent = `Page ${n}`;',
    "node.textContent = ok ? 'Copied' : t('x');",
    "iframe.title = a.getAttribute('title') || 'Video';",
    "button.setAttribute('aria-label', 'Pause autoplay');",
    "target.append(document.createTextNode('Did you mean ' + t('x')));",
    "el('p', 'cls', 'Could not load');",
    "line(text('English', 'err'));",
    'line(text(`bash: ${name}: command not found`));',
  ])('rejects %s', (code) => {
    const messages = lint(code);
    expect(messages).toHaveLength(1);
    expect(messages[0].ruleId).toBe('joo/no-literal-ui-string');
  });

  it.each([
    "node.textContent = t('code.copied');",
    "node.textContent = '';",
    "node.textContent = ' · ';",
    'node.textContent = String(page);',
    "button.setAttribute('aria-expanded', 'true');",
    "button.setAttribute('aria-label', t('slider.pause'));",
    "el('p', 'cls', t('load_failed'));",
    "line(text(message('terminal.try_help'), 'err'));",
    "line(text(args.join(' ')));",
    "node.className = 'joo-company';",
  ])('accepts %s', (code) => {
    expect(lint(code)).toEqual([]);
  });
});
