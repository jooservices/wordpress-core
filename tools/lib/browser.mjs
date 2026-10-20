import { chromium } from '@playwright/test';

const REMOTE_CONNECT_RETRY_DELAYS_MS = [100, 250, 500];
const TRANSIENT_REMOTE_CONNECT_ERROR = /\b(?:socket hang up|ECONNREFUSED|ECONNRESET)\b/u;

/**
 * Browser for the tools: the Dev VM browser when PW_TEST_CONNECT_WS_ENDPOINT is set (see docker/scripts/e2e-vm.sh),
 * a local Chromium otherwise.
 * @param {{ chromium?: Pick<typeof chromium, 'connect' | 'launch'>, env?: Record<string, string | undefined> }} [options]
 */
export async function launchBrowser({ chromium: engine = chromium, env = process.env } = {}) {
  const endpoint = env.PW_TEST_CONNECT_WS_ENDPOINT;
  if (!endpoint) return engine.launch();

  for (let attempt = 0; ; attempt += 1) {
    try {
      return await engine.connect(endpoint);
    } catch (error) {
      const delay = REMOTE_CONNECT_RETRY_DELAYS_MS[attempt];
      const message = error instanceof Error ? error.message : String(error);
      if (delay === undefined || !TRANSIENT_REMOTE_CONNECT_ERROR.test(message)) throw error;
      await new Promise((resolve) => setTimeout(resolve, delay));
    }
  }
}
