// <joo-toc>: marks the heading in view with aria-current="location" and keeps the hash in sync.
import { define, prefersReducedMotion } from 'joo-runtime';

export class JooToc extends HTMLElement {
  connectedCallback() {
    this.links = Array.from(this.querySelectorAll('a[href^="#"]'));
    this.targets = new Map();
    for (const link of this.links) {
      const id = decodeURIComponent(link.getAttribute('href').slice(1));
      const heading = document.getElementById(id);
      if (heading) this.targets.set(heading, link);
      link.addEventListener('click', (event) => this.onClick(event, heading, id));
    }
    if (!this.targets.size) return;
    this.observer = new IntersectionObserver((entries) => this.onIntersect(entries), {
      rootMargin: '0px 0px -70% 0px',
    });
    this.targets.forEach((_link, heading) => this.observer.observe(heading));
  }

  disconnectedCallback() {
    this.observer?.disconnect();
  }

  onIntersect(entries) {
    const visible = entries.filter((entry) => entry.isIntersecting);
    if (!visible.length) return;
    this.setCurrent(this.targets.get(visible[0].target));
  }

  setCurrent(link) {
    for (const other of this.links) other.removeAttribute('aria-current');
    link?.setAttribute('aria-current', 'location');
  }

  onClick(event, heading, id) {
    if (!heading) return;
    event.preventDefault();
    heading.scrollIntoView?.({ behavior: prefersReducedMotion() ? 'auto' : 'smooth', block: 'start' });
    history.replaceState(null, '', '#' + encodeURIComponent(id));
    this.setCurrent(this.targets.get(heading));
  }
}

define('joo-toc', JooToc);
