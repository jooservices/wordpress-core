// Updates the reading progress bar from the scroll position of the article (passive listener, one rAF per frame).
import { ready } from 'joo-runtime';

/**
 * Progress (0..100) of an element: 0 before its top reaches the viewport top, 100 once its bottom reaches the viewport bottom.
 * @param {{top: number, height: number}} rect Element rect.
 * @param {number} viewport Viewport height.
 * @returns {number} Percentage.
 */
export function progress(rect, viewport) {
  const total = rect.height - viewport;
  if (total <= 0) return rect.top <= 0 ? 100 : 0;
  return Math.max(0, Math.min(100, Math.round((-rect.top / total) * 100)));
}

ready(() => {
  const bar = document.querySelector('.joo-progress');
  const article = document.querySelector('article, main');
  if (!bar || !article) return;
  const fill = bar.querySelector('.joo-progress__bar');
  let frame = 0;
  const update = () => {
    frame = 0;
    const value = progress(article.getBoundingClientRect(), window.innerHeight);
    bar.setAttribute('aria-valuenow', String(value));
    if (fill) fill.style.transform = `scaleX(${value / 100})`;
  };
  window.addEventListener(
    'scroll',
    () => {
      if (!frame) frame = requestAnimationFrame(update);
    },
    { passive: true }
  );
  update();
});
