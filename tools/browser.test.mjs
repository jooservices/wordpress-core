// @vitest-environment node
import { describe, expect, it, vi } from 'vitest';
import { launchBrowser } from './lib/browser.mjs';

describe('launchBrowser', () => {
  it('connects to the remote browser when an endpoint is configured', async () => {
    const engine = { connect: vi.fn().mockResolvedValue('remote'), launch: vi.fn() };
    await expect(
      launchBrowser({ chromium: engine, env: { PW_TEST_CONNECT_WS_ENDPOINT: 'ws://127.0.0.1:9300/' } })
    ).resolves.toBe('remote');
    expect(engine.connect).toHaveBeenCalledWith('ws://127.0.0.1:9300/');
    expect(engine.launch).not.toHaveBeenCalled();
  });

  it('retries a transient socket hang up from the VM forwarder', async () => {
    const engine = {
      connect: vi
        .fn()
        .mockRejectedValueOnce(new Error('WebSocket error: socket hang up'))
        .mockResolvedValueOnce('remote'),
      launch: vi.fn(),
    };

    await expect(
      launchBrowser({ chromium: engine, env: { PW_TEST_CONNECT_WS_ENDPOINT: 'ws://127.0.0.1:9300/' } })
    ).resolves.toBe('remote');
    expect(engine.connect).toHaveBeenCalledTimes(2);
  });

  it('launches a local browser otherwise', async () => {
    const engine = { connect: vi.fn(), launch: vi.fn().mockResolvedValue('local') };
    await expect(launchBrowser({ chromium: engine, env: {} })).resolves.toBe('local');
    expect(engine.connect).not.toHaveBeenCalled();
  });
});
