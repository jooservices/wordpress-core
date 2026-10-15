// @vitest-environment node
import { describe, expect, it } from 'vitest';
import { parseMcpBody } from './mcp-smoke.mjs';

describe('mcp-smoke', () => {
  it('reads plain JSON and SSE bodies', () => {
    expect(parseMcpBody('{"result":{"tools":[1]}}').result.tools).toEqual([1]);
    expect(parseMcpBody('event: message\ndata: {"result":{"ok":true}}\n\n').result.ok).toBe(true);
  });
  it('rejects unreadable bodies', () => {
    expect(() => parseMcpBody('garbage')).toThrow(/unreadable/);
  });
});
