// @vitest-environment node
import { createServer } from 'node:http';
import { chromium } from '@playwright/test';
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

  it('sends Playwright to the configured server without launching a local browser', async () => {
    const server = createServer();
    let upgradePath;
    server.on('upgrade', (request, socket) => {
      upgradePath = request.url;
      socket.end('HTTP/1.1 403 Forbidden\r\nConnection: close\r\nContent-Length: 0\r\n\r\n');
    });

    await new Promise((resolve, reject) => {
      server.once('error', reject);
      server.listen(0, '127.0.0.1', () => resolve());
    });

    try {
      const address = server.address();
      if (!address || typeof address === 'string') throw new Error('fake WebSocket server did not bind to a TCP port');
      const endpoint = `ws://127.0.0.1:${address.port}/`;
      const connect = vi.fn((url) => chromium.connect(url));
      const engine = { connect, launch: vi.fn() };

      await expect(
        launchBrowser({ chromium: engine, env: { PW_TEST_CONNECT_WS_ENDPOINT: endpoint } })
      ).rejects.toThrow();

      expect(connect).toHaveBeenCalledWith(endpoint);
      expect(upgradePath).toBe('/');
      expect(connect).toHaveBeenCalledOnce();
      expect(engine.launch).not.toHaveBeenCalled();
    } finally {
      await new Promise((resolve, reject) => {
        server.close((error) => (error ? reject(error) : resolve()));
      });
    }
  });

  it('retries a transient remote startup failure', async () => {
    const engine = {
      connect: vi.fn().mockRejectedValueOnce(new Error('WebSocket error: socket hang up')).mockResolvedValue('remote'),
      launch: vi.fn(),
    };

    await expect(
      launchBrowser({ chromium: engine, env: { PW_TEST_CONNECT_WS_ENDPOINT: 'ws://127.0.0.1:9300/' } })
    ).resolves.toBe('remote');
    expect(engine.connect).toHaveBeenCalledTimes(2);
    expect(engine.launch).not.toHaveBeenCalled();
  });

  it('launches a local browser otherwise', async () => {
    const engine = { connect: vi.fn(), launch: vi.fn().mockResolvedValue('local') };
    await expect(launchBrowser({ chromium: engine, env: {} })).resolves.toBe('local');
    expect(engine.connect).not.toHaveBeenCalled();
  });
});
