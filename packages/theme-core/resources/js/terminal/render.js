// Safe renderer: builds DOM nodes from structured shell output (createElement + textContent only).

/**
 * Builds one span.
 * @param {{text: string, style?: string, cmd?: string}} span Span.
 * @returns {Node} The node.
 */
export function renderSpan(span) {
  const style = span.style ? `joo-term__${span.style}` : '';
  if (span.cmd) {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = ['joo-term__cmd', style].filter(Boolean).join(' ');
    button.dataset.cmd = span.cmd;
    button.textContent = span.text;
    return button;
  }
  if (!style) return document.createTextNode(span.text);
  const node = document.createElement('span');
  node.className = style;
  node.textContent = span.text;
  return node;
}

/**
 * Renders the blocks of a shell result into a fragment.
 * @param {object[]} blocks Result blocks.
 * @returns {DocumentFragment} The fragment.
 */
export function renderBlocks(blocks) {
  const fragment = document.createDocumentFragment();
  for (const block of blocks) {
    if (block.kind === 'cols') {
      const wrap = document.createElement('div');
      wrap.className = 'joo-term__cols';
      for (const item of block.items) wrap.append(renderSpan(item));
      fragment.append(wrap);
    } else if (block.kind === 'lines') {
      for (const spans of block.lines) {
        const row = document.createElement('div');
        row.className = 'joo-term__line';
        if (!spans.length) row.append(document.createTextNode(' '));
        for (const span of spans) row.append(renderSpan(span));
        fragment.append(row);
      }
    }
  }
  return fragment;
}

/** A plain line (used for the echo of typed commands and banners). */
export function plainLine(spans) {
  const row = document.createElement('div');
  row.className = 'joo-term__line';
  for (const span of spans) row.append(renderSpan(span));
  return row;
}
