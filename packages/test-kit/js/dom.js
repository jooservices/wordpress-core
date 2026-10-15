// DOM helpers for Vitest (jsdom).

/**
 * Mount an HTML string into document.body and return the container.
 * @param {string} html Trusted test markup.
 * @returns {HTMLElement} Container element.
 */
export function mount(html) {
  const container = document.createElement('div');
  container.innerHTML = html; // eslint-disable-line no-restricted-syntax -- trusted test fixture markup
  document.body.replaceChildren(container);
  return container;
}

/**
 * Wait for pending microtasks and zero-delay timers.
 * @returns {Promise<void>} Resolves after the queues are flushed.
 */
export async function flush() {
  await Promise.resolve();
  await new Promise((resolve) => setTimeout(resolve, 0));
}

/**
 * Install a controllable IntersectionObserver mock (jsdom has none).
 * @returns {{ trigger: (target: Element, isIntersecting?: boolean) => void, instances: object[] }} Controller.
 */
export function mockIntersectionObserver() {
  const instances = [];
  class MockObserver {
    constructor(callback, options) {
      this.callback = callback;
      this.options = options;
      this.targets = new Set();
      instances.push(this);
    }
    observe(target) {
      this.targets.add(target);
    }
    unobserve(target) {
      this.targets.delete(target);
    }
    disconnect() {
      this.targets.clear();
    }
  }
  globalThis.IntersectionObserver = MockObserver;
  return {
    instances,
    trigger(target, isIntersecting = true) {
      for (const observer of instances) {
        if (observer.targets.has(target)) {
          observer.callback([{ target, isIntersecting, intersectionRatio: isIntersecting ? 1 : 0 }], observer);
        }
      }
    },
  };
}

/**
 * Mock window.matchMedia with a map of query -> boolean.
 * @param {Record<string, boolean>} map Query results.
 * @returns {void}
 */
export function mockMatchMedia(map = {}) {
  window.matchMedia = (query) => ({
    matches: Boolean(map[query]),
    media: query,
    addEventListener() {},
    removeEventListener() {},
    addListener() {},
    removeListener() {},
    dispatchEvent: () => false,
  });
}
