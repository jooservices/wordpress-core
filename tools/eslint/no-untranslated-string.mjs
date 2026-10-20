// ESLint rule joo/no-untranslated-string: user-visible text in the runtime JavaScript must come from the config
// object that PHP fills with __() (t() / message()), never from a literal.

const LETTERS = /\p{L}{2,}/u;
const TEXT_PROPERTIES = new Set(['textContent', 'innerText', 'title', 'placeholder', 'alt', 'ariaLabel', 'value']);
const TEXT_ATTRIBUTES = new Set(['aria-label', 'aria-description', 'aria-valuetext', 'title', 'placeholder', 'alt']);
// Function name => index of the argument that carries visible text.
const TEXT_FUNCTIONS = { text: 0, link: 0, el: 2, fail: 1 };
const TEXT_METHODS = { createTextNode: 0, insertAdjacentText: 1 };
// DOM methods that show every string argument as text.
const TEXT_VARIADIC = new Set(['append', 'prepend', 'before', 'after', 'replaceWith', 'replaceChildren']);

/** @param {object} node MemberExpression ESTree node. @returns {string|null} Static property name. */
function memberName(node) {
  if (!node.computed) return node.property.type === 'Identifier' ? node.property.name : null;
  if (node.property.type === 'Literal' && typeof node.property.value === 'string') return node.property.value;
  if (node.property.type === 'TemplateLiteral' && node.property.expressions.length === 0)
    return node.property.quasis[0]?.value.cooked ?? null;
  return null;
}

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
    case 'CallExpression':
      if (node.callee.type === 'Identifier') {
        if (['t', 'message'].includes(node.callee.name)) return [];
        if (Object.hasOwn(TEXT_FUNCTIONS, node.callee.name))
          return literals(node.arguments[TEXT_FUNCTIONS[node.callee.name]]);
      }
      if (node.callee.type === 'MemberExpression') {
        const name = memberName(node.callee);
        if (['getAttribute', 'hasAttribute', 'removeAttribute', 'setAttribute'].includes(name)) return [];
        const args = ['replace', 'replaceAll'].includes(name) ? node.arguments.slice(1) : node.arguments;
        return [...literals(node.callee.object), ...args.flatMap(literals)];
      }
      return node.arguments.flatMap(literals);
    case 'NewExpression':
      return node.arguments.flatMap(literals);
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
        if (left.type === 'MemberExpression' && TEXT_PROPERTIES.has(memberName(left))) check(node.right);
      },
      CallExpression(node) {
        const { callee, arguments: args } = node;
        if (callee.type === 'Identifier' && Object.hasOwn(TEXT_FUNCTIONS, callee.name)) {
          check(args[TEXT_FUNCTIONS[callee.name]]);
        } else if (callee.type === 'MemberExpression') {
          const name = memberName(callee);
          if (name === 'setAttribute' && args[0]?.type === 'Literal' && TEXT_ATTRIBUTES.has(args[0].value))
            check(args[1]);
          else if (Object.hasOwn(TEXT_METHODS, name)) check(args[TEXT_METHODS[name]]);
          else if (TEXT_VARIADIC.has(name)) args.forEach(check);
        }
      },
    };
  },
};
