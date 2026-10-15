// Copy buttons of .joo-code blocks (event delegation, no dependencies).
const FEEDBACK_MS = 1500;

/**
 * Copies text with the async clipboard API or a temporary textarea.
 * @param {string} text Text.
 * @returns {Promise<boolean>} Whether the copy worked.
 */
export async function copy(text) {
  try {
    if (navigator.clipboard?.writeText) {
      await navigator.clipboard.writeText(text);
      return true;
    }
  } catch {
    /* fall back below */
  }
  try {
    const area = document.createElement('textarea');
    area.value = text;
    area.setAttribute('readonly', '');
    area.style.cssText = 'position:fixed;top:0;left:0;opacity:0';
    document.body.append(area);
    area.select();
    const ok = document.execCommand('copy');
    area.remove();
    return ok;
  } catch {
    return false;
  }
}

/** Handles one click on a copy button. */
export async function onClick(button) {
  const box = button.closest('.joo-code');
  const code = box?.querySelector('pre code, pre');
  if (!code) return;
  const ok = await copy(code.textContent ?? '');
  const status = box.querySelector('.joo-code__status');
  const label = button.textContent;
  button.textContent = ok ? 'Copied' : 'Press Ctrl+C';
  if (status) status.textContent = ok ? 'Code copied' : 'Copy failed';
  setTimeout(() => {
    button.textContent = label;
    if (status) status.textContent = '';
  }, FEEDBACK_MS);
}

document.querySelectorAll('.joo-code__copy').forEach((button) => (button.hidden = false));
document.addEventListener('click', (event) => {
  const button = event.target instanceof Element ? event.target.closest('.joo-code__copy') : null;
  if (button) onClick(button);
});
