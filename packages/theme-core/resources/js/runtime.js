// JOOservices frontend runtime: tiny shared kernel imported by every interactive module (as the script module "joo-runtime").
// Progressive enhancement rule: nothing here is required for content to be readable.

/** @typedef {{ features: Record<string, boolean>, i18n: Record<string, string>, assets: Record<string, string>, rest: string, lang: string }} JooConfig */

/** @type {JooConfig} */
const DEFAULTS = { features: {}, i18n: {}, assets: {}, rest: '/wp-json/joo/v1/', lang: 'en' };
let cached = null;

/**
 * The page configuration printed by PHP in <script type="application/json" id="joo-config">.
 * @returns {Readonly<JooConfig>} Frozen configuration (defaults when the element is missing or invalid).
 */
export function config() {
  if (cached) return cached;
  let parsed;
  try {
    const node = document.getElementById('joo-config');
    parsed = node ? JSON.parse(node.textContent || '{}') : {};
  } catch {
    parsed = {};
  }
  cached = Object.freeze({ ...DEFAULTS, ...parsed });
  return cached;
}

/** Forget the cached configuration (tests). */
export function resetConfig() {
  cached = null;
}

/**
 * Whether a feature flag is on.
 * @param {string} name Feature name.
 * @returns {boolean} True when enabled.
 */
export const feature = (name) => Boolean(config().features[name]);

/**
 * Translate a UI string using the strings PHP provided.
 * @param {string} key String key.
 * @param {string} [fallback] Text when the key is unknown.
 * @returns {string} Translated text.
 */
export const t = (key, fallback = key) => config().i18n[key] ?? fallback;

/**
 * Build a REST URL below the joo/v1 namespace.
 * @param {string} path Path such as "fragment/tags".
 * @returns {string} URL.
 */
export const restUrl = (path) => config().rest.replace(/\/$/, '') + '/' + path.replace(/^\//, '');

/**
 * Run a function once the DOM is parsed (modules are deferred, so usually immediately).
 * @param {() => void} fn Callback.
 * @returns {void}
 */
export function ready(fn) {
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn, { once: true });
  else fn();
}

/**
 * Define a custom element once.
 * @param {string} tag Element name.
 * @param {CustomElementConstructor} ctor Class.
 * @returns {void}
 */
export function define(tag, ctor) {
  if (!customElements.get(tag)) customElements.define(tag, ctor);
}

/**
 * Run a function when the browser is idle (setTimeout fallback).
 * @param {() => void} fn Callback.
 * @param {number} [timeout] Maximum wait in ms.
 * @returns {void}
 */
export function idle(fn, timeout = 2000) {
  if ('requestIdleCallback' in window) window.requestIdleCallback(fn, { timeout });
  else setTimeout(fn, 1);
}

const observers = new Map();

/** Forget the shared observers (tests). */
export function resetObservers() {
  observers.clear();
}

/**
 * Call back when an element approaches the viewport. One IntersectionObserver is shared per rootMargin.
 * @param {Element} el Element to watch.
 * @param {(entry: IntersectionObserverEntry | null) => void} cb Callback.
 * @param {{ rootMargin?: string, once?: boolean }} [opts] Options.
 * @returns {() => void} Function that stops watching.
 */
export function inView(el, cb, opts = {}) {
  if (!('IntersectionObserver' in window)) {
    cb(null);
    return () => {};
  }
  const margin = opts.rootMargin ?? '0px';
  const once = opts.once !== false;
  let entry = observers.get(margin);
  if (!entry) {
    const targets = new Map();
    const observer = new IntersectionObserver(
      (entries) => {
        for (const item of entries) {
          const handler = targets.get(item.target);
          if (handler && item.isIntersecting) {
            if (handler.once) {
              observer.unobserve(item.target);
              targets.delete(item.target);
            }
            handler.cb(item);
          }
        }
      },
      { rootMargin: margin }
    );
    entry = { observer, targets };
    observers.set(margin, entry);
  }
  entry.targets.set(el, { cb, once });
  entry.observer.observe(el);
  return () => {
    entry.observer.unobserve(el);
    entry.targets.delete(el);
  };
}

const modules = new Map();

/**
 * Lazily import a module that PHP registered in the config "assets" map.
 * @param {string} name Logical asset name.
 * @returns {Promise<unknown>} The module namespace.
 */
export function load(name) {
  if (!modules.has(name)) {
    const url = config().assets[name];
    modules.set(name, url ? import(/* @vite-ignore */ url) : Promise.reject(new Error(`Unknown joo module: ${name}`)));
  }
  return modules.get(name);
}

const listeners = new EventTarget();

/** Tiny event bus. */
export const bus = {
  /**
   * @param {string} type Event type.
   * @param {(detail: unknown) => void} fn Listener.
   * @returns {() => void} Unsubscribe function.
   */
  on(type, fn) {
    const handler = (event) => fn(/** @type {CustomEvent} */ (event).detail);
    listeners.addEventListener(type, handler);
    return () => listeners.removeEventListener(type, handler);
  },
  /**
   * @param {string} type Event type.
   * @param {unknown} [detail] Payload.
   * @returns {void}
   */
  emit(type, detail) {
    listeners.dispatchEvent(new CustomEvent(type, { detail }));
  },
};

/**
 * localStorage wrapper that never throws (private mode safe) and falls back to memory.
 * @param {string} key Storage key.
 * @returns {{ get: () => string | null, set: (value: string) => void, remove: () => void }} Accessors.
 */
export function store(key) {
  let memory = null;
  return {
    get() {
      try {
        return window.localStorage.getItem(key);
      } catch {
        return memory;
      }
    },
    set(value) {
      memory = value;
      try {
        window.localStorage.setItem(key, value);
      } catch {
        /* storage unavailable: keep the in-memory value */
      }
    },
    remove() {
      memory = null;
      try {
        window.localStorage.removeItem(key);
      } catch {
        /* storage unavailable */
      }
    },
  };
}

/**
 * @returns {boolean} True when the visitor asked for reduced motion.
 */
export const prefersReducedMotion = () => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches === true;

/**
 * @returns {boolean} True when the Save-Data hint is on.
 */
export const saveData = () => /** @type {any} */ (navigator).connection?.saveData === true;
