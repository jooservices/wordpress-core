// ESLint rule: a user-visible string in the theme's JavaScript must come from the runtime config (`t('key')`), never
// from an English literal. Flags literals (also in template literals, `a ? 'x' : 'y'`, `a || 'x'`, `'x' + y`) that
// reach a user-visible sink: textContent/innerText/title/alt/placeholder/ariaLabel assignments, setAttribute() of
// aria-label/title/alt/placeholder, createTextNode() and the `el(tag, class, text)` helper. Strings without letters
// (punctuation, numbers, the empty string) are allowed.

const PROPERTIES = new Set(['textContent', 'innerText', 'title', 'alt', 'placeholder', 'ariaLabel']);
const ATTRIBUTES = new Set([
  'aria-label',
  'aria-description',
  'aria-roledescription',
  'aria-valuetext',
  'title',
  'alt',
  'placeholder',
]);
const HELPERS = new Map([['el', 2]]);

const hasLetters = (value) => /\p{L}/u.test(value);

/**
 * Collects the literal nodes whose text can end up in the sink.
 * @param {object} node Expression node.
 * @returns {object[]} Offending literal nodes.
 */
function literals(node) {
  if (!node) return [];
  switch (node.type) {
    case 'Literal':
      return typeof node.value === 'string' && hasLetters(node.value) ? [node] : [];
    case 'TemplateLiteral':
      return node.quasis.some((quasi) => hasLetters(quasi.value.cooked ?? '')) ? [node] : [];
    case 'ConditionalExpression':
      return [...literals(node.consequent), ...literals(node.alternate)];
    case 'LogicalExpression':
      return [...literals(node.left), ...literals(node.right)];
    case 'BinaryExpression':
      return node.operator === '+' ? [...literals(node.left), ...literals(node.right)] : [];
    default:
      return [];
  }
}

const propertyName = (member) =>
  member.type === 'MemberExpression' && !member.computed && member.property.type === 'Identifier'
    ? member.property.name
    : null;

/** @type {import('eslint').Rule.RuleModule} */
export default {
  meta: {
    type: /** @type {const} */ ('problem'),
    schema: [],
    messages: {
      literal: 'User-visible text must come from the runtime config: use t("key") and add the key to JsStrings.php.',
    },
  },
  create(context) {
    const report = (nodes) => nodes.forEach((node) => context.report({ node, messageId: 'literal' }));
    return {
      AssignmentExpression(node) {
        if (PROPERTIES.has(propertyName(node.left))) report(literals(node.right));
      },
      CallExpression(node) {
        const name = propertyName(node.callee);
        if (
          name === 'setAttribute' &&
          node.arguments[0]?.type === 'Literal' &&
          ATTRIBUTES.has(String(node.arguments[0].value))
        ) {
          report(literals(node.arguments[1]));
        } else if (name === 'createTextNode') {
          report(literals(node.arguments[0]));
        } else if (node.callee.type === 'Identifier' && HELPERS.has(node.callee.name)) {
          report(literals(node.arguments[HELPERS.get(node.callee.name)]));
        }
      },
    };
  },
};
