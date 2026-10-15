import { afterEach, describe, expect, it, vi } from 'vitest';
import api from './blocks.js';

describe('generic block editor helpers', () => {
  afterEach(() => vi.useRealTimers());

  it('keeps only known control types that match a declared attribute', () => {
    const controls = [
      { attribute: 'limit', type: 'range' },
      { attribute: 'missing', type: 'toggle' },
      { attribute: 'limit', type: 'weird' },
      null,
    ];
    expect(api.usableControls(controls, { limit: {} })).toEqual([{ attribute: 'limit', type: 'range' }]);
    expect(api.usableControls(undefined, {})).toEqual([]);
  });

  it('describes each control type', () => {
    expect(api.describeControl({ attribute: 'n', type: 'range', min: 1, max: 12, label: 'Items' }, 5)).toEqual({
      component: 'RangeControl',
      props: { label: 'Items', value: 5, min: 1, max: 12, withInputField: true },
    });
    expect(api.describeControl({ attribute: 'x', type: 'toggle', label: 'Show' }, 1).props.checked).toBe(true);
    const select = api.describeControl({ attribute: 's', type: 'select', options: [['count', 'Most posts']] }, 'count');
    expect(select.props.options).toEqual([{ value: 'count', label: 'Most posts' }]);
    expect(api.describeControl({ attribute: 't', type: 'text' }, undefined).props.value).toBe('');
    expect(api.describeControl({ attribute: 'n', type: 'number' }, 3).props).toMatchObject({
      type: 'number',
      value: '3',
    });
    expect(api.describeControl({ attribute: 'c', type: 'color' }, '#fff').component).toBe('ColorPalette');
  });

  it('converts raw values to attributes', () => {
    expect(api.toAttribute({ type: 'range' }, '7')).toBe(7);
    expect(api.toAttribute({ type: 'number' }, 'abc')).toBe(0);
    expect(api.toAttribute({ type: 'toggle' }, 1)).toBe(true);
    expect(api.toAttribute({ type: 'text' }, 'hi')).toBe('hi');
  });

  it('debounces calls', () => {
    vi.useFakeTimers();
    const spy = vi.fn();
    const call = api.debounce(spy, 300);
    call(1);
    call(2);
    call(3);
    vi.advanceTimersByTime(299);
    expect(spy).not.toHaveBeenCalled();
    vi.advanceTimersByTime(1);
    expect(spy).toHaveBeenCalledTimes(1);
    expect(spy).toHaveBeenCalledWith(3);
  });
});
