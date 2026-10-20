import { afterEach, describe, expect, it, vi } from 'vitest';
import { createAnnouncer, formatAnnouncement } from './announcer.js';

afterEach(() => vi.useRealTimers());

describe('terminal announcer', () => {
  it('formats a concise command and output summary', () => {
    expect(formatAnnouncement('ls', 'ai\nREADME.md')).toBe('Command ls. ai README.md');
  });

  it('truncates long output and points to the readable terminal log', () => {
    const message = formatAnnouncement('grep', 'match '.repeat(100), 96);
    expect(message.length).toBeLessThanOrEqual(96);
    expect(message).toContain('More output is available in the terminal log.');
  });

  it('coalesces quick messages into one bounded announcement', () => {
    vi.useFakeTimers();
    const region = document.createElement('div');
    const announcer = createAnnouncer(region, { delay: 200, maxCharacters: 96 });
    announcer.announce('Command ls. ai');
    announcer.announce('Command pwd. /');
    expect(region.textContent).toBe('');
    vi.advanceTimersByTime(200);
    expect(region.textContent).toBe('Command ls. ai; Command pwd. /');
    announcer.destroy();
    expect(region.textContent).toBe('');
  });

  it('marks omitted rapid output without exceeding the limit', () => {
    vi.useFakeTimers();
    const region = document.createElement('div');
    const announcer = createAnnouncer(region, { delay: 200, maxCharacters: 96 });
    announcer.announce('Command grep. ' + 'match '.repeat(20));
    announcer.announce('Command grep. another result');
    vi.advanceTimersByTime(200);
    expect(region.textContent.length).toBeLessThanOrEqual(96);
    expect(region.textContent).toContain('More output is available in the terminal log.');
    announcer.destroy();
  });
});
