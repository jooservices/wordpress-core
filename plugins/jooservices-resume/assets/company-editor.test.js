import { beforeEach, describe, expect, it, vi } from 'vitest';

const row = (index) => `
  <fieldset data-joo-row>
    <legend><span data-joo-title>Role ${index + 1}</span></legend>
    <label for="joo_roles_${index}_title">Title</label>
    <input id="joo_roles_${index}_title" name="joo_roles[${index}][title]" value="${index}">
    <button type="button" data-joo-up>Up</button>
    <button type="button" data-joo-down>Down</button>
    <button type="button" data-joo-remove>Remove</button>
  </fieldset>`;

const render = (count = 2) => {
  const markup = `
    <form id="post">
      <div data-joo-repeater="joo_roles" data-noun="Role">
        <div class="joo-repeater__rows">${Array.from({ length: count }, (_, index) => row(index)).join('')}</div>
        <template>
          <fieldset data-joo-row>
            <legend><span data-joo-title>Role</span></legend>
            <label for="joo_roles___INDEX___title">Title</label>
            <input id="joo_roles___INDEX___title" name="joo_roles[__INDEX__][title]">
            <button type="button" data-joo-up>Up</button>
            <button type="button" data-joo-down>Down</button>
            <button type="button" data-joo-remove>Remove</button>
          </fieldset>
        </template>
        <button type="button" data-joo-add>Add role</button>
        <p data-joo-live></p>
      </div>
    </form>`;
  // Parsed with DOMParser (the repo lint forbids assigning HTML strings).
  document.body.replaceChildren(...new DOMParser().parseFromString(markup, 'text/html').body.childNodes);
};

const rows = () => Array.from(document.querySelectorAll('[data-joo-row]'));
const names = () => rows().map((item) => item.querySelector('input').name);
const click = (item, selector) => item.querySelector(selector).click();

beforeEach(() => {
  vi.resetModules();
  render();
});

describe('company editor repeaters', () => {
  it('adds a reindexed row, focuses its first field and announces the change', async () => {
    await import('./company-editor.js');
    click(document, '[data-joo-add]');

    expect(names()).toEqual(['joo_roles[0][title]', 'joo_roles[1][title]', 'joo_roles[2][title]']);
    expect(document.activeElement).toBe(rows()[2].querySelector('input'));
    expect(document.querySelector('[data-joo-live]').textContent).toBe('role 3 added');
  });

  it('removes a row, reindexes and focuses the next neighbour (or previous at the end)', async () => {
    await import('./company-editor.js');
    click(rows()[0], '[data-joo-remove]');

    expect(names()).toEqual(['joo_roles[0][title]']);
    expect(document.activeElement).toBe(rows()[0].querySelector('input'));
    expect(document.querySelector('[data-joo-live]').textContent).toBe('role removed, 1 left');

    click(rows()[0], '[data-joo-remove]');
    expect(rows()).toHaveLength(0);
    expect(document.activeElement).toBe(document.querySelector('[data-joo-add]'));
  });

  it('moves rows up and down with names following screen order and focus on the button', async () => {
    await import('./company-editor.js');
    const down = rows()[0].querySelector('[data-joo-down]');
    down.click();
    expect(rows().map((item) => item.querySelector('input').value)).toEqual(['1', '0']);
    expect(names()).toEqual(['joo_roles[0][title]', 'joo_roles[1][title]']);
    expect(document.activeElement).toBe(down);
    expect(document.querySelector('[data-joo-live]').textContent).toBe('role moved to position 2 of 2');

    const up = rows()[1].querySelector('[data-joo-up]');
    up.click();
    expect(rows().map((item) => item.querySelector('input').value)).toEqual(['0', '1']);
    expect(document.activeElement).toBe(up);
  });

  it('does not move beyond either end', async () => {
    await import('./company-editor.js');
    const first = rows()[0];
    const last = rows()[1];
    click(first, '[data-joo-up]');
    click(last, '[data-joo-down]');

    expect(rows()).toEqual([first, last]);
    expect(document.querySelector('[data-joo-live]').textContent).toBe('');
  });

  it('warns before unload after edits, but not before edits or after submit', async () => {
    await import('./company-editor.js');
    const form = document.getElementById('post');
    const cleanEvent = new Event('beforeunload', { cancelable: true });
    window.dispatchEvent(cleanEvent);
    expect(cleanEvent.defaultPrevented).toBe(false);

    form.dispatchEvent(new Event('input', { bubbles: true }));
    const dirtyEvent = new Event('beforeunload', { cancelable: true });
    window.dispatchEvent(dirtyEvent);
    expect(dirtyEvent.defaultPrevented).toBe(true);

    form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    const submittedEvent = new Event('beforeunload', { cancelable: true });
    window.dispatchEvent(submittedEvent);
    expect(submittedEvent.defaultPrevented).toBe(false);
  });
});
