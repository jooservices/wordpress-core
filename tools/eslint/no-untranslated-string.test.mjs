import { describe, expect, it } from 'vitest';
import { ESLint, RuleTester } from 'eslint';
import rule from './no-untranslated-string.mjs';

RuleTester.describe = describe;
RuleTester.it = it;
RuleTester.itOnly = it.only;

const tester = new RuleTester({ languageOptions: { ecmaVersion: 2022, sourceType: 'module' } });

tester.run('no-untranslated-string', rule, {
  valid: [
    "el.textContent = t('a.b', 'Hello world');",
    "el.setAttribute('aria-label', t('a.b', 'Close'));",
    "el.setAttribute('data-cmd', 'help');",
    "el.setAttribute('aria-expanded', 'true');",
    "el.className = 'joo-term__line';",
    "el.textContent = ' · ';",
    "el.textContent = '× ' + count;",
    "text(message('k', 'Posts: {posts}', { posts }), 'dim');",
    "el.textContent = String(t('k', 'Hello world'));",
    "text(format(t('k', 'Hello world')));",
    "iframe.title = frame.getAttribute('title') || t('embed.title', 'Video');",
    "el('p', 'joo-note', t('k', 'Retry'));",
    "document.createTextNode(t('k', 'Present'));",
    "el.append(node, t('k', 'Present'));",
    "el.insertAdjacentText('beforeend', t('k', 'Present'));",
  ],
  invalid: [
    { code: "el.append('Hello world');", errors: [{ messageId: 'untranslated' }] },
    { code: "el.prepend(node, 'Hello world');", errors: [{ messageId: 'untranslated' }] },
    { code: "el.insertAdjacentText('beforeend', 'Hello world');", errors: [{ messageId: 'untranslated' }] },
    { code: "el.textContent = 'Copied';", errors: [{ messageId: 'untranslated' }] },
    { code: "el.title = 'Close terminal';", errors: [{ messageId: 'untranslated' }] },
    { code: "el.setAttribute('aria-label', 'Pause autoplay');", errors: [{ messageId: 'untranslated' }] },
    {
      code: "el.textContent = ok ? 'Done' : 'Failed';",
      errors: [{ messageId: 'untranslated' }, { messageId: 'untranslated' }],
    },
    {
      code: 'el.textContent = `Page ${n} of ${total}`;',
      errors: [{ messageId: 'untranslated' }, { messageId: 'untranslated' }],
    },
    { code: "el.title = node.title || 'Video';", errors: [{ messageId: 'untranslated' }] },
    { code: "document.createTextNode('Did you mean ' + x);", errors: [{ messageId: 'untranslated' }] },
    { code: "el.textContent = String('Hello world');", errors: [{ messageId: 'untranslated' }] },
    { code: "text(format('Hello world'));", errors: [{ messageId: 'untranslated' }] },
    { code: "button.value = 'Search';", errors: [{ messageId: 'untranslated' }] },
    { code: "el['textContent'] = 'Hello world';", errors: [{ messageId: 'untranslated' }] },
    { code: "text('Blocked: not a link', 'err');", errors: [{ messageId: 'untranslated' }] },
    { code: "el('button', '', 'Retry');", errors: [{ messageId: 'untranslated' }] },
    { code: "el['insertAdjacentText']('beforeend', 'Hello world');", errors: [{ messageId: 'untranslated' }] },
  ],
});

describe('shipped runtime JavaScript', () => {
  it('has no literal user-visible string outside the runtime config', async () => {
    const eslint = new ESLint({ cwd: process.cwd() });
    const results = await eslint.lintFiles([
      'packages/theme-core/resources/js/**/*.js',
      'packages/theme-core/resources/blocks/*/view.js',
    ]);
    const problems = results.flatMap((result) =>
      result.messages
        .filter((message) => message.ruleId === 'joo/no-untranslated-string')
        .map((message) => `${result.filePath}:${message.line} ${message.message}`)
    );
    expect(problems).toEqual([]);
    expect(results.length).toBeGreaterThan(10);
  });
});
