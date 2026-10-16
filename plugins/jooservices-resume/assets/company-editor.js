/* Repeaters of the company screens and the custom-field builder: add, remove and move rows with buttons, announce each change. */
(function () {
  'use strict';

  const rowsOf = (root) => Array.from(root.querySelectorAll(':scope > .joo-repeater__rows > [data-joo-row]'));

  const announce = (root, message) => {
    const region = root.querySelector('[data-joo-live]');
    if (region) region.textContent = message;
  };

  /* Names, ids and labels follow the screen order, so the saved order is the visible order. */
  function reindex(root) {
    const name = root.getAttribute('data-joo-repeater');
    const noun = root.getAttribute('data-noun') || '';
    const nameRule = new RegExp('^' + name + '\\[[^\\]]*\\]');
    const idRule = new RegExp('^' + name + '_[^_]+_');
    rowsOf(root).forEach((row, index) => {
      row.querySelectorAll('input, select, textarea, label').forEach((el) => {
        ['name', 'id', 'for'].forEach((attr) => {
          const value = el.getAttribute(attr);
          if (value)
            el.setAttribute(
              attr,
              value.replace(nameRule, name + '[' + index + ']').replace(idRule, name + '_' + index + '_')
            );
        });
      });
      const title = row.querySelector('[data-joo-title]');
      if (title) title.textContent = noun + ' ' + (index + 1);
    });
  }

  /* A new row is a clone of the <template>; the placeholder index is replaced by reindex(). */
  function addRow(root) {
    const fresh = root.querySelector('template').content.firstElementChild.cloneNode(true);
    root.querySelector('.joo-repeater__rows').appendChild(fresh);
    fresh.querySelectorAll('input, select, textarea, label').forEach((el) => {
      ['name', 'id', 'for'].forEach((attr) => {
        const value = el.getAttribute(attr);
        if (value) el.setAttribute(attr, value.replace('__INDEX__', String(rowsOf(root).length - 1)));
      });
    });
    return fresh;
  }

  function init(root) {
    const list = root.querySelector('.joo-repeater__rows');
    const noun = (root.getAttribute('data-noun') || '').toLowerCase();
    reindex(root);

    root.addEventListener('click', (event) => {
      const button = event.target.closest('button');
      if (!button || !root.contains(button)) return;
      const row = button.closest('[data-joo-row]');
      if (button.hasAttribute('data-joo-add')) {
        const fresh = addRow(root);
        reindex(root);
        const first = fresh.querySelector('input, select, textarea');
        if (first) first.focus();
        announce(root, noun + ' ' + rowsOf(root).length + ' added');
      } else if (button.hasAttribute('data-joo-remove') && row) {
        const all = rowsOf(root);
        const neighbour = all[all.indexOf(row) + 1] || all[all.indexOf(row) - 1];
        row.remove();
        reindex(root);
        announce(root, noun + ' removed, ' + rowsOf(root).length + ' left');
        const target = neighbour
          ? neighbour.querySelector('input, select, textarea')
          : root.querySelector('[data-joo-add]');
        if (target) target.focus();
      } else if ((button.hasAttribute('data-joo-up') || button.hasAttribute('data-joo-down')) && row) {
        const up = button.hasAttribute('data-joo-up');
        const sibling = up ? row.previousElementSibling : row.nextElementSibling;
        if (!sibling) return;
        if (up) list.insertBefore(row, sibling);
        else list.insertBefore(sibling, row);
        reindex(root);
        announce(root, noun + ' moved to position ' + (rowsOf(root).indexOf(row) + 1) + ' of ' + rowsOf(root).length);
        button.focus();
      }
    });
  }

  document.querySelectorAll('[data-joo-repeater]').forEach(init);

  /* Unsaved changes warning on the company screen. */
  const form = document.getElementById('post');
  if (form) {
    let dirty = false;
    form.addEventListener('input', () => {
      dirty = true;
    });
    form.addEventListener('submit', () => {
      dirty = false;
    });
    window.addEventListener('beforeunload', (event) => {
      if (dirty) {
        event.preventDefault();
        event.returnValue = '';
      }
    });
  }
})();
