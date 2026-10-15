// Turns artifacts/reports/perf/<theme>.json (make perf) into a Markdown table.
// Usage: node tools/perf-report.mjs --theme jooservices
import { readFileSync, writeFileSync } from 'node:fs';
import { fromRoot } from './lib/paths.mjs';

/** Markdown table of the perf results. */
export function render(theme, results) {
  const rows = results.map(
    (r) =>
      `| ${r.name} | ${r.scores.performance} | ${r.scores.accessibility} | ${r.scores['best-practices']} | ${r.scores.seo} | ${r.metrics['cumulative-layout-shift'].toFixed(3)} | ${Math.round(r.metrics['total-blocking-time'])} | ${Math.round(r.metrics['largest-contentful-paint'])} | ${r.bytes.css} / ${r.bytes.js} / ${r.bytes.fonts} | ${r.problems.length ? r.problems.join('; ') : 'ok'} |`
  );
  return `# Performance and SEO: ${theme}\n\n| Page | Perf | A11y | BP | SEO | CLS | TBT ms | LCP ms | CSS / JS / fonts bytes | Result |\n| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |\n${rows.join('\n')}\n`;
}

if (import.meta.url === `file://${process.argv[1]}`) {
  const args = process.argv.slice(2);
  const theme = args.includes('--theme') ? args[args.indexOf('--theme') + 1] : 'jooservices';
  const results = JSON.parse(readFileSync(fromRoot('artifacts/reports/perf', `${theme}.json`), 'utf8'));
  const file = fromRoot('artifacts/reports/perf', `${theme}.md`);
  writeFileSync(file, render(theme, results));
  console.log(`wrote ${file}`);
}
