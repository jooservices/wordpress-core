/* Search-result preview and length counters for the SEO panel.
 * Pure functions, exposed as window.jooSerp in the editor and as a CommonJS/ES export for tests. */
(function (root) {
  'use strict';
  const TITLE_SOFT = 60;
  const TITLE_HARD = 70;
  const DESC_MIN = 120;
  const DESC_MAX = 160;

  /** Counts user-perceived characters (surrogate pairs count once). */
  function count(text) {
    return Array.from(String(text || '')).length;
  }

  /** Cuts text on a word boundary with an ellipsis, never splitting a character. */
  function clamp(text, max) {
    const chars = Array.from(
      String(text || '')
        .replace(/\s+/g, ' ')
        .trim()
    );
    if (chars.length <= max) {
      return chars.join('');
    }
    let cut = chars.slice(0, max - 1).join('');
    const space = cut.lastIndexOf(' ');
    if (space >= max * 0.5) {
      cut = cut.slice(0, space);
    }
    return cut.replace(/[\s,;:|-]+$/, '') + '…';
  }

  /**
   * @param {{title?: string, description?: string, url?: string}} input
   * @returns {{title: string, description: string, url: string, titleCount: number, descCount: number, titleState: string, descState: string}}
   */
  function serp(input) {
    const title = String((input && input.title) || '').trim();
    const description = String((input && input.description) || '').trim();
    const titleCount = count(title);
    const descCount = count(description);
    return {
      title: clamp(title, TITLE_HARD),
      description: clamp(description, DESC_MAX),
      url: String((input && input.url) || '')
        .replace(/^https?:\/\//, '')
        .replace(/\/$/, '')
        .split('/')
        .join(' › '),
      titleCount: titleCount,
      descCount: descCount,
      titleState:
        titleCount === 0 ? 'empty' : titleCount > TITLE_HARD ? 'over' : titleCount > TITLE_SOFT ? 'long' : 'ok',
      descState: descCount === 0 ? 'empty' : descCount > DESC_MAX ? 'over' : descCount < DESC_MIN ? 'short' : 'ok',
    };
  }

  const api = { serp: serp, count: count, clamp: clamp };
  root.jooSerp = api;
  if (typeof module !== 'undefined' && module.exports) {
    module.exports = api;
  }
})(typeof window !== 'undefined' ? window : globalThis);
