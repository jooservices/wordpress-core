/** Editorial categories that the later showcase generator can consume. */
export const EDITORIAL_CATEGORIES = Object.freeze([
  'news-announcements',
  'tutorials-howtos',
  'product-tool-reviews',
  'release-notes-changelogs',
  'security-advisories',
  'opinion-career',
  'case-studies-postmortems',
  'tips-cheatsheets',
  'glossary-faq',
  'comparisons-benchmarks',
  'hardware-homelab',
  'ai-llm',
  'devops-cloud',
  'backend',
  'frontend',
  'databases',
  'networking-security',
  'open-source',
  'events-podcasts',
  'books-resources-interviews-roundups',
]);

/** @type {Array<[string, RegExp]>} */
const RULES = [
  ['security-advisories', /\b(cve|vulnerabilit|security advisory|security update|exploit|patch|zero.day)\b/i],
  ['release-notes-changelogs', /\b(release|changelog|version \d|beta|rc\d|lts)\b/i],
  ['ai-llm', /\b(ai|llm|gpt|language model|machine learning|neural|agentic)\b/i],
  ['comparisons-benchmarks', /\b(benchmark|performance comparison|compare|comparison|latency numbers)\b/i],
  ['databases', /\b(database|postgres(?:ql)?|mysql|mariadb|mongodb|redis|sql query|indexing)\b/i],
  ['networking-security', /\b(network|dns|tls|firewall|router|tcp|http\/3|zero trust)\b/i],
  ['hardware-homelab', /\b(homelab|home lab|server rack|hardware|nas|raspberry pi|gpu)\b/i],
  ['devops-cloud', /\b(devops|kubernetes|docker|cloud|container|ci\/cd|terraform|observability)\b/i],
  ['backend', /\b(php|laravel|golang|\bgo\b|node(?:\.js)?|python|api server|backend)\b/i],
  ['frontend', /\b(frontend|css|javascript|typescript|react|browser|accessibility|web component)\b/i],
  ['product-tool-reviews', /\b(review|hands-on|first look|versus|vs\.|impressions)\b/i],
  ['case-studies-postmortems', /\b(case study|postmortem|incident report|lessons learned|migration story)\b/i],
  ['opinion-career', /\b(opinion|career|engineering culture|hiring|staff engineer|developer experience)\b/i],
  ['tutorials-howtos', /\b(tutorial|how to|step.by.step|guide|build a|implementing)\b/i],
  ['tips-cheatsheets', /\b(tip|cheat sheet|quick reference|shortcuts|tricks)\b/i],
  ['glossary-faq', /\b(glossary|what is|faq|frequently asked|explained)\b/i],
  ['events-podcasts', /\b(event|conference|podcast|meetup|webinar|episode)\b/i],
  ['books-resources-interviews-roundups', /\b(book|reading list|resources|interview|roundup|weekly links)\b/i],
  ['open-source', /\b(open.source|github|repository|maintainer|community project)\b/i],
];

/** Categorizes with an explicit override taking precedence over keyword rules. */
/** @param {Record<string, any>} item */
/** @param {Record<string, string>} [overrides] */
export function classify(item, overrides = {}) {
  const override = overrides[item.id] ?? overrides[item.url] ?? overrides[item.source_id];
  if (override) {
    if (!EDITORIAL_CATEGORIES.includes(override)) throw new Error(`Unknown editorial category override: ${override}`);
    return override;
  }
  // Source-level topic hints describe the feed broadly and can overwhelm item-level editorial classification.
  const text = `${item.title ?? ''} ${item.excerpt ?? ''} ${item.tags?.join(' ') ?? ''}`;
  return RULES.find(([, pattern]) => pattern.test(text))?.[0] ?? 'news-announcements';
}

/** Counts coverage dimensions without depending on a current clock or external inputs. */
export function coverageReport(items) {
  const byCategory = Object.fromEntries(EDITORIAL_CATEGORIES.map((category) => [category, 0]));
  const byLanguage = {};
  const bySource = {};
  const byTag = {};
  const byYear = {};
  const byMatrix = {
    categories: new Set(),
    languages: new Set(),
    authors: new Set(),
    code_languages: new Set(),
    long_form: 0,
    code_heavy: 0,
    image_heavy: 0,
    comment_heavy: 0,
  };
  for (const item of items) {
    const category = item.editorial_category ?? classify(item);
    byCategory[category] = (byCategory[category] ?? 0) + 1;
    const language = item.language ?? 'unknown';
    byLanguage[language] = (byLanguage[language] ?? 0) + 1;
    bySource[item.source_id] = (bySource[item.source_id] ?? 0) + 1;
    for (const tag of item.tags ?? []) byTag[tag] = (byTag[tag] ?? 0) + 1;
    if (item.date && /^\d{4}-/.test(item.date)) {
      const year = item.date.slice(0, 4);
      byYear[year] = (byYear[year] ?? 0) + 1;
    }
    byMatrix.categories.add(category);
    byMatrix.languages.add(language);
    if (item.author) byMatrix.authors.add(item.author);
    for (const codeLanguage of item.code_languages ?? []) byMatrix.code_languages.add(codeLanguage);
    if (item.shape?.long_form) byMatrix.long_form += 1;
    if (item.shape?.code_heavy) byMatrix.code_heavy += 1;
    if (item.shape?.image_heavy) byMatrix.image_heavy += 1;
    if (item.shape?.comment_heavy) byMatrix.comment_heavy += 1;
  }
  return {
    total_items: items.length,
    by_category: byCategory,
    by_language: byLanguage,
    by_source: Object.fromEntries(Object.entries(bySource).sort(([a], [b]) => a.localeCompare(b))),
    tag_count: Object.keys(byTag).length,
    by_tag: Object.fromEntries(Object.entries(byTag).sort(([a], [b]) => a.localeCompare(b))),
    by_year: Object.fromEntries(Object.entries(byYear).sort(([a], [b]) => a.localeCompare(b))),
    matrix: {
      category_count: byMatrix.categories.size,
      languages: [...byMatrix.languages].sort(),
      author_count: byMatrix.authors.size,
      code_languages: [...byMatrix.code_languages].sort(),
      long_form: byMatrix.long_form,
      code_heavy: byMatrix.code_heavy,
      image_heavy: byMatrix.image_heavy,
      comment_heavy: byMatrix.comment_heavy,
    },
  };
}
