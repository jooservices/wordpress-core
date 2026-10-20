// <joo-slider>: buttons, dots, keyboard and autoplay on top of the CSS scroll-snap track (which already works without JS).
import { define, prefersReducedMotion, t } from 'joo-runtime';

export class JooSlider extends HTMLElement {
  connectedCallback() {
    this.track = this.querySelector('.joo-slider__track');
    this.slides = this.track ? Array.from(this.track.children) : [];
    if (this.slides.length < 2) return;
    this.index = 0;
    this.controls = this.querySelector('.joo-slider__controls');
    if (this.controls) this.controls.hidden = false;
    this.dots = Array.from(this.querySelectorAll('.joo-slider__dot'));
    this.querySelector('.joo-slider__prev')?.addEventListener('click', () => this.step(-1));
    this.querySelector('.joo-slider__next')?.addEventListener('click', () => this.step(1));
    this.dots.forEach((dot, i) => dot.addEventListener('click', () => this.go(i)));
    this.track.addEventListener('keydown', (event) => this.onKey(event));
    this.observer = new IntersectionObserver(
      (entries) => {
        for (const entry of entries) {
          if (entry.isIntersecting && entry.intersectionRatio >= 0.6) this.mark(this.slides.indexOf(entry.target));
        }
      },
      { root: this.track, threshold: [0.6] }
    );
    this.slides.forEach((slide) => this.observer.observe(slide));
    this.mark(0);
    this.setupAutoplay();
  }

  disconnectedCallback() {
    this.observer?.disconnect();
    this.stop();
    for (const [target, type, fn] of this.listeners ?? []) target.removeEventListener(type, fn);
  }

  /** Marks the current slide (dots and labels). */
  mark(i) {
    if (i < 0) return;
    this.index = i;
    this.dots.forEach((dot, n) => dot.setAttribute('aria-current', String(n === i)));
  }

  /** Scrolls to a slide (instant with reduced motion). */
  go(i) {
    const count = this.slides.length;
    const next = ((i % count) + count) % count;
    const rtl = this.isRtl();
    const start = this.slides[next].offsetLeft - this.track.offsetLeft;
    const left = rtl ? start - (this.track.scrollWidth - this.track.clientWidth) : start;
    this.track.scrollTo?.({ left, behavior: prefersReducedMotion() ? 'auto' : 'smooth' });
    this.mark(next);
  }

  /** Whether the track lays out right to left (scrollLeft runs negative there). */
  isRtl() {
    return getComputedStyle(this.track).direction === 'rtl';
  }

  step(direction) {
    this.go(this.index + direction);
  }

  onKey(event) {
    const forward = this.isRtl() ? -1 : 1;
    const keys = {
      ArrowLeft: () => this.step(-forward),
      ArrowRight: () => this.step(forward),
      Home: () => this.go(0),
      End: () => this.go(this.slides.length - 1),
    };
    if (keys[event.key]) {
      event.preventDefault();
      keys[event.key]();
    }
  }

  setupAutoplay() {
    this.interval = Math.max(3000, Number(this.dataset.interval) || 6500);
    this.autoplay = this.dataset.autoplay === '1' && !prefersReducedMotion();
    this.userPaused = false;
    this.pauseButton = this.querySelector('.joo-slider__pause');
    if (!this.autoplay) {
      this.track.setAttribute('aria-live', 'polite');
      return;
    }
    if (this.pauseButton) {
      this.pauseButton.hidden = false;
      this.pauseButton.addEventListener('click', () => this.toggle());
    }
    const on = (target, type, fn) => {
      target.addEventListener(type, fn);
      (this.listeners ??= []).push([target, type, fn]);
    };
    on(this, 'pointerenter', () => this.stop());
    on(this, 'focusin', () => this.stop());
    on(this, 'touchstart', () => this.stop());
    on(this, 'pointerleave', () => !this.userPaused && this.start());
    on(this, 'focusout', () => !this.userPaused && this.start());
    on(document, 'visibilitychange', () => (document.hidden ? this.stop() : !this.userPaused && this.start()));
    this.start();
  }

  start() {
    if (!this.autoplay || this.timer || document.hidden) return;
    this.timer = setInterval(() => this.step(1), this.interval);
    this.track.setAttribute('aria-live', 'off');
    this.pauseButton?.setAttribute('aria-label', t('slider.pause', 'Pause autoplay'));
  }

  stop() {
    if (this.timer) clearInterval(this.timer);
    this.timer = null;
    this.track?.setAttribute('aria-live', 'polite');
  }

  toggle() {
    this.userPaused = !this.userPaused;
    if (this.userPaused) {
      this.stop();
      this.pauseButton?.setAttribute('aria-label', t('slider.start', 'Start autoplay'));
    } else this.start();
  }
}

define('joo-slider', JooSlider);
