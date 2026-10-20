// <joo-embed>: click-to-load video facade. The anchor inside is a normal link (works without JavaScript); on
// click the privacy-friendly player iframe replaces it and receives focus.
import { define, t } from 'joo-runtime';

const PLAYERS = {
  youtube: (id, start) =>
    `https://www.youtube-nocookie.com/embed/${encodeURIComponent(id)}?autoplay=1&rel=0${start > 0 ? `&start=${start}` : ''}`,
  vimeo: (id, start) =>
    `https://player.vimeo.com/video/${encodeURIComponent(id)}?autoplay=1&dnt=1${start > 0 ? `#t=${start}s` : ''}`,
};

class JooEmbed extends HTMLElement {
  connectedCallback() {
    const link = this.querySelector('a');
    if (!link) return;
    link.addEventListener('click', (event) => {
      event.preventDefault();
      this.play();
    });
    link.addEventListener('keydown', (event) => {
      if (event.key === ' ') {
        event.preventDefault();
        this.play();
      }
    });
  }

  /** Replace the facade by the player iframe. */
  play() {
    const build = PLAYERS[this.getAttribute('provider') ?? ''];
    const id = this.getAttribute('video');
    if (!build || !id) return;
    const iframe = document.createElement('iframe');
    iframe.src = build(id, Number(this.getAttribute('start')) || 0);
    iframe.title = this.getAttribute('title') || t('embed.title', 'Video');
    iframe.allow = 'autoplay; encrypted-media; picture-in-picture; fullscreen';
    iframe.allowFullscreen = true;
    iframe.setAttribute('loading', 'eager');
    iframe.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
    this.replaceChildren(iframe);
    iframe.focus();
  }
}

define('joo-embed', JooEmbed);
