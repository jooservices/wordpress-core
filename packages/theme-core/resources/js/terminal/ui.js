// Terminal panel UI: output log, prompt, keyboard handling and result effects. Nothing here talks to the network.
import { t } from 'joo-runtime';
import { plainLine, renderBlocks } from './render.js';
import { link, text } from './shell.js';

const el = (tag, className, content) => {
  const node = document.createElement(tag);
  if (className) node.className = className;
  if (content !== undefined) node.textContent = content;
  return node;
};

/**
 * Returns the URL when it is an http(s) URL of this origin, otherwise null (navigation safety).
 * @param {string} url Candidate URL.
 * @returns {string|null} Safe absolute URL.
 */
export function safeUrl(url) {
  try {
    const parsed = new URL(url, window.location.href);
    return (parsed.protocol === 'http:' || parsed.protocol === 'https:') && parsed.origin === window.location.origin
      ? parsed.href
      : null;
  } catch {
    return null;
  }
}

/**
 * Mounts the terminal into a panel element.
 * @param {HTMLElement} panel Empty panel container.
 * @param {{shell: object, onClose: () => void, navigate?: (url: string) => void, shortcuts?: string[]}} options Options.
 * @returns {{run: (cmd: string) => void, focus: () => void}} Controller.
 */
export function mountTerminal(panel, { shell, onClose, navigate = (url) => window.location.assign(url), shortcuts }) {
  const title = el('b', 'joo-term__title');
  const close = el('button', 'joo-term__close', '×');
  close.type = 'button';
  close.setAttribute('aria-label', t('terminal.close', 'Close terminal'));
  const bar = el('div', 'joo-term__bar');
  bar.append(title, close);

  const chips = el('div', 'joo-term__chips');
  for (const cmd of shortcuts ?? ['ls', 'tree', 'help', 'find . -type f']) {
    const chip = el('button', 'joo-term__chip', cmd);
    chip.type = 'button';
    chip.dataset.cmd = cmd;
    chips.append(chip);
  }

  const log = el('div', 'joo-term__log');
  log.setAttribute('role', 'log');
  log.setAttribute('aria-live', 'polite');
  log.tabIndex = 0;

  const form = el('form', 'joo-term__form');
  const label = el('label', 'joo-term__prompt');
  label.htmlFor = 'joo-term-input';
  const input = el('input', 'joo-term__input');
  input.id = 'joo-term-input';
  input.autocomplete = 'off';
  input.autocapitalize = 'off';
  input.spellcheck = false;
  input.setAttribute('aria-label', t('terminal.input', 'Terminal command'));
  form.append(label, input);
  panel.replaceChildren(bar, chips, log, form);

  const syncPrompt = () => {
    label.textContent = shell.prompt();
    title.textContent = shell.prompt().replace('$', '');
  };
  const scroll = () => {
    log.scrollTop = log.scrollHeight;
  };
  const echo = (command) => log.append(plainLine([text(shell.prompt() + ' ', 'ps'), text(command)]));

  const apply = (result) => {
    for (const effect of result.effects ?? []) {
      if (effect.type === 'clear') log.replaceChildren();
      else if (effect.type === 'close') onClose();
      else if (effect.type === 'theme')
        window.dispatchEvent(new CustomEvent('joo:theme', { detail: { value: effect.value } }));
      else if (effect.type === 'lang') {
        const alt = document.querySelector(`link[rel="alternate"][hreflang="${CSS.escape(effect.value)}"]`);
        const url = alt ? safeUrl(alt.getAttribute('href') ?? '') : null;
        if (url) navigate(url);
        else
          log.append(
            plainLine([
              text(t('terminal.lang_unavailable', 'This page has no translation. Use the language switcher.'), 'dim'),
            ])
          );
      }
    }
    if (result.navigate) {
      const url = safeUrl(result.navigate.url);
      if (url) navigate(url);
      else log.append(plainLine([text(t('terminal.blocked', 'Blocked: not a link on this site.'), 'err')]));
    }
  };

  const run = (command) => {
    echo(command);
    const result = shell.run(command);
    log.append(renderBlocks(result.blocks));
    apply(result);
    syncPrompt();
    scroll();
  };

  form.addEventListener('submit', (event) => {
    event.preventDefault();
    const value = input.value;
    input.value = '';
    run(value);
  });
  input.addEventListener('keydown', (event) => {
    if (event.key === 'Tab') {
      event.preventDefault();
      const done = shell.complete(input.value);
      if (done.candidates.length) {
        echo(input.value);
        const cols = el('div', 'joo-term__cols');
        for (const c of done.candidates) cols.append(el('span', 'joo-term__dim', c));
        log.append(cols);
        scroll();
      }
      input.value = done.line;
    } else if (event.key === 'ArrowUp') {
      event.preventDefault();
      input.value = shell.navigateHistory(-1);
    } else if (event.key === 'ArrowDown') {
      event.preventDefault();
      input.value = shell.navigateHistory(1);
    } else if (event.ctrlKey && event.key === 'l') {
      event.preventDefault();
      log.replaceChildren();
    } else if (event.ctrlKey && event.key === 'c') {
      event.preventDefault();
      echo(input.value + '^C');
      input.value = '';
    } else if (event.key === 'Escape') {
      onClose();
    }
  });
  panel.addEventListener('click', (event) => {
    const target = event.target instanceof Element ? event.target.closest('[data-cmd]') : null;
    if (target instanceof HTMLElement) {
      event.preventDefault();
      run(target.dataset.cmd);
      input.focus();
    }
  });
  close.addEventListener('click', () => onClose());

  syncPrompt();
  log.append(
    plainLine([
      text(shell.fs.site.name, 'ok'),
      text(' ' + t('terminal.banner', 'read-only shell · nothing is sent anywhere'), 'dim'),
    ]),
    plainLine([
      text(t('terminal.hint', 'Type '), 'dim'),
      link('help', 'help'),
      text(' · ', 'dim'),
      link('ls', 'ls'),
      text(' · ', 'dim'),
      link('tree', 'tree'),
      text(' · ', 'dim'),
      link('cat README.md', 'cat README.md'),
    ])
  );
  return { run, focus: () => input.focus() };
}
