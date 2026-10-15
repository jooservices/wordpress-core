// <joo-tree>: collapses branches (all visible without JS). The branch with the current page stays open;
// the open state is remembered in sessionStorage.
import { define } from 'joo-runtime';

const KEY = 'joo-tree';

/** @returns {Set<string>} Ids the visitor opened or closed explicitly: "id" open, "-id" closed. */
export function readState() {
  try {
    return new Set(JSON.parse(sessionStorage.getItem(KEY) ?? '[]'));
  } catch {
    return new Set();
  }
}

/** Saves the state. */
export function writeState(state) {
  try {
    sessionStorage.setItem(KEY, JSON.stringify([...state]));
  } catch {
    /* storage unavailable: the state lasts for this page only */
  }
}

export class JooTree extends HTMLElement {
  connectedCallback() {
    this.state = readState();
    const depth = Number(this.dataset.expandedDepth) || 0;
    this.querySelectorAll('.joo-tree__item').forEach((item) => {
      const toggle = item.querySelector(':scope > .joo-tree__row > .joo-tree__toggle');
      const children = item.querySelector(':scope > .joo-tree__children');
      if (!toggle || !children) return;
      const id = item.dataset.id;
      const holdsCurrent = Boolean(item.querySelector('[aria-current="page"]'));
      let open = holdsCurrent || Number(item.dataset.depth) <= depth;
      if (this.state.has(id)) open = true;
      else if (this.state.has('-' + id) && !holdsCurrent) open = false;
      toggle.hidden = false;
      this.set(item, toggle, children, open, false);
      toggle.addEventListener('click', () => this.set(item, toggle, children, children.hidden, true));
    });
  }

  set(item, toggle, children, open, remember) {
    children.hidden = !open;
    toggle.setAttribute('aria-expanded', String(open));
    if (!remember) return;
    const id = item.dataset.id;
    this.state.delete(id);
    this.state.delete('-' + id);
    this.state.add(open ? id : '-' + id);
    writeState(this.state);
  }
}

define('joo-tree', JooTree);
