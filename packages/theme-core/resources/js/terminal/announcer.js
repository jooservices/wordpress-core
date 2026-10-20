const compact = (value) =>
  String(value ?? '')
    .replace(/\s+/g, ' ')
    .trim();
const overflowText = ' More output is available in the terminal log.';

/**
 * Makes one concise announcement for a command result.
 * @param {string} command Command that was run.
 * @param {string} output Visible command output.
 * @param {number} [maxCharacters] Maximum announcement length.
 * @returns {string} Announcement text.
 */
export function formatAnnouncement(command, output, maxCharacters = 240) {
  const limit = Math.max(64, Math.floor(maxCharacters));
  const name = compact(command);
  const result = compact(output);
  const message = `${name ? `Command ${name}.` : 'Command completed.'}${result ? ` ${result}` : ''}`;
  if (message.length <= limit) return message;
  return `${message.slice(0, limit - overflowText.length).trimEnd()}${overflowText}`;
}

/**
 * Coalesces quick updates into short polite announcements.
 * @param {HTMLElement} region Empty polite live region.
 * @param {{delay?: number, maxCharacters?: number}} [options] Announcement policy.
 * @returns {{announce: (message: string) => void, destroy: () => void}} Announcer controller.
 */
export function createAnnouncer(region, { delay = 200, maxCharacters = 240 } = {}) {
  const limit = Math.max(64, Math.floor(maxCharacters));
  let timer;
  let pending = '';
  let overflow = false;

  const flush = () => {
    region.textContent = overflow
      ? `${pending.slice(0, limit - overflowText.length).trimEnd()}${overflowText}`
      : pending;
    timer = undefined;
    pending = '';
    overflow = false;
  };

  return {
    announce(message) {
      const next = compact(message);
      if (!next) return;
      if (pending) {
        const combined = `${pending}; ${next}`;
        if (combined.length <= limit) pending = combined;
        else overflow = true;
      } else if (next.length <= limit) pending = next;
      else {
        pending = next.slice(0, limit);
        overflow = true;
      }
      if (timer === undefined) timer = window.setTimeout(flush, Math.max(0, delay));
    },
    destroy() {
      if (timer !== undefined) window.clearTimeout(timer);
      timer = undefined;
      pending = '';
      overflow = false;
      region.textContent = '';
    },
  };
}
