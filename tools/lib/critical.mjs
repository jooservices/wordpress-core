// Extracts the CSS that is needed to paint the first viewport of a page, using the live CSSOM of Chromium.
// A style rule is kept when one of its selectors matches an element that intersects the viewport.
import { transform } from 'lightningcss';
import { gzipSync } from 'node:zlib';
import { resolve } from 'node:path';
import { fromRoot } from './paths.mjs';

/** Resolve the critical CSS output directory from the repository root. */
export function criticalOutputDirectory(theme, outputDirectory) {
  return outputDirectory ? resolve(fromRoot(), outputDirectory) : fromRoot('themes', theme, 'assets', 'critical');
}

/**
 * Make same-site asset URLs portable across local and production hosts.
 * @param {string} css Critical CSS.
 * @param {string} siteUrl URL used to capture the CSS.
 * @returns {string} CSS with the capture origin removed from same-site URLs.
 */
export function localizeSiteUrls(css, siteUrl) {
  const { origin } = new URL(siteUrl);
  return css.replaceAll(`${origin}/`, '/');
}

/**
 * Collect top-level critical rules from the current page.
 * @param {import('@playwright/test').Page} page Page that finished loading.
 * @returns {Promise<string[]>} Rule texts in source order.
 */
export async function extractCriticalRules(page) {
  return page.evaluate(() => {
    const vh = window.innerHeight;
    const pseudo =
      /::?(before|after|first-line|first-letter|selection|placeholder|marker|backdrop|file-selector-button)/g;
    const states = /:(hover|focus|active|focus-visible|focus-within|visited|checked|disabled|target|invalid|valid)\b/g;
    const matchesViewport = (selector) => {
      const base = selector.replace(pseudo, '').replace(states, '').trim();
      if (base === '' || /^(:root|html|body|\*)$/.test(base)) return true;
      try {
        for (const el of document.querySelectorAll(base)) {
          const rect = el.getBoundingClientRect();
          if (rect.bottom > 0 && rect.top < vh) return true;
        }
      } catch {
        return false;
      }
      return false;
    };
    /** @param {CSSRuleList} rules @returns {string[]} */
    const walk = (rules) => {
      const out = [];
      for (const rule of rules) {
        if (rule instanceof CSSStyleRule) {
          const selectors = rule.selectorText.split(/,(?![^(]*\))/).map((s) => s.trim());
          if (selectors.some(matchesViewport)) out.push(rule.cssText);
        } else if (rule instanceof CSSFontFaceRule) {
          out.push(rule.cssText);
        } else if (
          rule instanceof CSSMediaRule ||
          rule instanceof CSSSupportsRule ||
          (typeof CSSContainerRule !== 'undefined' && rule instanceof CSSContainerRule) ||
          (typeof CSSLayerBlockRule !== 'undefined' && rule instanceof CSSLayerBlockRule)
        ) {
          const inner = walk(rule.cssRules);
          if (inner.length) out.push(`${rule.cssText.slice(0, rule.cssText.indexOf('{'))}{${inner.join('')}}`);
        } else if (rule instanceof CSSImportRule && rule.styleSheet) {
          out.push(...walk(rule.styleSheet.cssRules));
        } else if (typeof CSSLayerStatementRule !== 'undefined' && rule instanceof CSSLayerStatementRule) {
          out.push(rule.cssText);
        }
      }
      return out;
    };
    const rules = [];
    for (const sheet of document.styleSheets) {
      if (sheet.ownerNode instanceof Element && sheet.ownerNode.id === 'joo-critical') continue; // do not feed our own output back
      try {
        rules.push(...walk(sheet.cssRules));
      } catch {
        /* cross-origin stylesheet: not readable */
      }
    }
    return rules;
  });
}

/**
 * Merge rule lists from several viewports (unique, first-seen order) and minify.
 * @param {string[][]} lists Rule lists.
 * @returns {{ css: string, size: number, gzip: number }} Minified CSS and its sizes.
 */
export function mergeAndMinify(lists) {
  const seen = new Set();
  const merged = [];
  for (const list of lists) {
    for (const rule of list) {
      if (!seen.has(rule)) {
        seen.add(rule);
        merged.push(rule);
      }
    }
  }
  const { code } = transform({ filename: 'critical.css', code: Buffer.from(merged.join('\n')), minify: true });
  const css = code.toString();
  return { css, size: css.length, gzip: gzipSync(css).length };
}
