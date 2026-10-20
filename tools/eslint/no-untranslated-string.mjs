// ESLint rule joo/no-untranslated-string: user-visible text in the runtime JavaScript must come from the config
// object that PHP fills with __() (t() / message()), never from a literal.

const LETTERS = /\p{L}{2,}/u;
const TEXT_PROPERTIES = new Set(['textContent', 'innerText', 'title', 'placeholder', 'alt', 'ariaLabel']);
const TEXT_ATTRIBUTES = new Set(['aria-label', 'aria-description', 'aria-valuetext', 'title', 'placeholder', 'alt']);
// Function name => index of the argument that carries visible text.
const TEXT_FUNCTIONS = { text: 0, link: 0, el: 2, fail: 1 };
const TEXT_METHODS = { createTextNode: 0 };

/**
 * Literal pieces of an expression that would be shown as written (calls such as t() are trusted).
 * @param {object} node ESTree expression.
 * @returns {object[]} Offending Literal / TemplateElement nodes.
 */
function literals(node) {
  if (!node) return [];
  switch (node.type) {
    case 'Literal':
      return typeof node.value === 'string' && LETTERS.test(node.value) ? [node] : [];
    case 'TemplateLiteral':
      return [...node.quasis.filter((q) => LETTERS.test(q.value.cooked ?? '')), ...node.expressions.flatMap(literals)];
    case 'BinaryExpression':
      return node.operator === '+' ? [...literals(node.left), ...literals(node.right)] : [];
    case 'LogicalExpression':
      return [...literals(node.left), ...literals(node.right)];
    case 'ConditionalExpression':
      return [...literals(node.consequent), ...literals(node.alternate)];
    default:
      return [];
  }
}

export default {
  meta: {
    type: 'problem',
    docs: { description: 'Require user-visible JavaScript strings to come from the translated runtime config.' },
    schema: [],
    messages: {
      untranslated:
        'User-visible string "{{text}}" must come from the runtime config: use t(key, fallback) and add the key to JsStrings.php.',
    },
  },
  create(context) {
    const check = (node) => {
      for (const found of literals(node)) {
        const text = found.type === 'Literal' ? found.value : found.value.cooked;
        context.report({ node: found, messageId: 'untranslated', data: { text: String(text).trim().slice(0, 40) } });
      }
    };
    return {
      AssignmentExpression(node) {
        const { left } = node;
        if (left.type === 'MemberExpression' && !left.computed && TEXT_PROPERTIES.has(left.property.name))
          check(node.right);
      },
      CallExpression(node) {
        const { callee, arguments: args } = node;
        if (callee.type === 'Identifier' && Object.hasOwn(TEXT_FUNCTIONS, callee.name)) {
          check(args[TEXT_FUNCTIONS[callee.name]]);
        } else if (callee.type === 'MemberExpression' && !callee.computed) {
          const name = callee.property.name;
          if (name === 'setAttribute' && args[0]?.type === 'Literal' && TEXT_ATTRIBUTES.has(args[0].value))
            check(args[1]);
          else if (Object.hasOwn(TEXT_METHODS, name)) check(args[TEXT_METHODS[name]]);
        }
      },
    };
  },
};
