/** License identifiers admitted by the sourcing pipeline. */
export const LICENSES = Object.freeze({
  ALL_RIGHTS_RESERVED: 'ALL-RIGHTS-RESERVED',
  UNKNOWN: 'LICENSE-UNKNOWN',
  CC0: 'CC0-1.0',
  PUBLIC_DOMAIN_US: 'PUBLIC-DOMAIN-US',
  CC_BY_3: 'CC-BY-3.0',
  CC_BY_4: 'CC-BY-4.0',
  CC_BY_SA_2_5: 'CC-BY-SA-2.5',
  CC_BY_SA_3: 'CC-BY-SA-3.0',
  CC_BY_SA_4: 'CC-BY-SA-4.0',
  MIT: 'MIT',
});

const LICENSE_RULES = Object.freeze({
  'ALL-RIGHTS-RESERVED': { tiers: ['A'], scope: 'HEADLINE', redistribution: false, attribution: false },
  'LICENSE-UNKNOWN': { tiers: ['A'], scope: 'HEADLINE', redistribution: false, attribution: false },
  'CC0-1.0': { tiers: ['A', 'B'], scope: 'BODY', redistribution: true, attribution: false },
  'PUBLIC-DOMAIN-US': { tiers: ['A', 'B'], scope: 'BODY', redistribution: true, attribution: false },
  'CC-BY-3.0': { tiers: ['A', 'B'], scope: 'BODY', redistribution: true, attribution: true },
  'CC-BY-4.0': { tiers: ['A', 'B'], scope: 'BODY', redistribution: true, attribution: true },
  'CC-BY-SA-2.5': { tiers: ['A', 'B'], scope: 'BODY', redistribution: true, attribution: true, shareAlike: true },
  'CC-BY-SA-3.0': { tiers: ['A', 'B'], scope: 'BODY', redistribution: true, attribution: true, shareAlike: true },
  'CC-BY-SA-4.0': { tiers: ['A', 'B'], scope: 'BODY', redistribution: true, attribution: true, shareAlike: true },
  MIT: { tiers: ['A', 'B'], scope: 'BODY', redistribution: true, attribution: true },
});

/** Returns whether a known license permits an item in a tier. */
export function licenseRule(license) {
  return LICENSE_RULES[license] ?? null;
}

/** Fails closed when an item or tier would exceed the source's known rights. */
export function auditItem(item, tier) {
  const errors = [];
  const normalizedTier = String(tier).toUpperCase();
  if (!['A', 'B'].includes(normalizedTier)) errors.push(`tier must be A or B, got ${tier}`);
  if (!item || typeof item !== 'object') return ['item must be an object'];
  for (const field of ['source_url', 'license', 'fetched_at']) {
    if (typeof item[field] !== 'string' || item[field].trim() === '') errors.push(`${field} is required`);
  }
  if (item.source_url && !isHttpUrl(item.source_url)) errors.push('source_url must be an http(s) URL');
  if (
    item.fetched_at &&
    (!/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/.test(item.fetched_at) ||
      !Number.isFinite(Date.parse(item.fetched_at)))
  ) {
    errors.push('fetched_at must be an ISO timestamp with a timezone');
  }
  if (typeof item.title !== 'string' || item.title.trim() === '') errors.push('title is required');

  const rule = licenseRule(item.license);
  if (!rule) {
    errors.push(`license is not on the allowlist: ${item.license}`);
    return errors;
  }
  if (['A', 'B'].includes(normalizedTier) && !rule.tiers.includes(normalizedTier)) {
    errors.push(`${item.license} is not allowed for Tier ${normalizedTier}`);
  } else if (normalizedTier === 'B' && !rule.redistribution) {
    errors.push(`${item.license} is not redistributable in Tier B`);
  }
  const scope = item.content_scope ?? 'HEADLINE';
  if (scope === 'HEADLINE' && (typeof item.title !== 'string' || item.title.trim() === ''))
    errors.push('headline scope requires a title');
  if (scope !== 'HEADLINE' && !rule.redistribution) errors.push(`${item.license} permits headline facts only`);
  if (!['HEADLINE', 'EXCERPT', 'BODY'].includes(scope)) errors.push(`invalid content_scope: ${scope}`);
  if (rule.attribution) {
    const attribution = item.attribution;
    if (!attribution || typeof attribution !== 'object') errors.push(`${item.license} requires an attribution block`);
    else {
      for (const field of ['title', 'author', 'url', 'license_url']) {
        if (typeof attribution[field] !== 'string' || attribution[field].trim() === '')
          errors.push(`attribution.${field} is required for ${item.license}`);
      }
    }
  }
  return errors;
}

/** Throws a concise error when a record fails the requested tier audit. */
export function assertItemAllowed(item, tier) {
  const errors = auditItem(item, tier);
  if (errors.length) throw new Error(`${item?.id ?? 'item'}: ${errors.join('; ')}`);
  return item;
}

function isHttpUrl(value) {
  try {
    return ['http:', 'https:'].includes(new URL(value).protocol);
  } catch {
    return false;
  }
}
