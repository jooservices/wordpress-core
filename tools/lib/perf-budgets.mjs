import { readFileSync } from 'node:fs';
import { fromRoot } from './paths.mjs';

const START = '<!-- enforced-code-budgets:start -->';
const END = '<!-- enforced-code-budgets:end -->';

/** Read the enforced code-budget values embedded in the performance reference. */
export function readCodeBudgets(file = fromRoot('docs', 'reference', 'budgets.md')) {
  const document = readFileSync(file, 'utf8');
  const start = document.indexOf(START);
  const end = document.indexOf(END, start + START.length);
  if (start === -1 || end === -1) throw new Error('Enforced code budgets are missing from docs/reference/budgets.md.');

  const block = document.slice(start + START.length, end);
  const json = block.match(/```json\s*([\s\S]*?)\s*```/u)?.[1];
  if (!json) throw new Error('Enforced code budgets must be a JSON code block in docs/reference/budgets.md.');

  const budgets = JSON.parse(json);
  if (!budgets || typeof budgets !== 'object' || Array.isArray(budgets)) {
    throw new Error('Enforced code budgets must be a JSON object.');
  }
  const isObject = (value) => value && typeof value === 'object' && !Array.isArray(value);
  const isPositiveNumber = (value) => typeof value === 'number' && Number.isFinite(value) && value > 0;
  const criticalCss = budgets.criticalCss;
  if (
    !isObject(budgets.boot) ||
    !isPositiveNumber(budgets.boot.emptyModuleSetMedianMilliseconds) ||
    !isPositiveNumber(budgets.boot.emptyModuleSetPeakBytes) ||
    !isObject(budgets.runtimeBundle) ||
    !isPositiveNumber(budgets.runtimeBundle.maxBytes) ||
    !isObject(budgets.javascript) ||
    Object.keys(budgets.javascript).length === 0 ||
    !Object.values(budgets.javascript).every(isPositiveNumber) ||
    !isObject(criticalCss) ||
    !isPositiveNumber(criticalCss.maxGzipBytes) ||
    !Array.isArray(criticalCss.themes) ||
    !criticalCss.themes.length ||
    !criticalCss.themes.every((theme) => typeof theme === 'string' && theme.length > 0) ||
    !Array.isArray(criticalCss.templates) ||
    !criticalCss.templates.length ||
    !criticalCss.templates.every((template) => typeof template === 'string' && template.length > 0)
  ) {
    throw new Error('Enforced code budgets are missing or invalid in docs/reference/budgets.md.');
  }
  return budgets;
}
