import { chromium } from '@playwright/test';

const remoteConnectAttempts = 10;
const remoteConnectRetryDelayMs = 100;

/** @param {unknown} error */
function isTransientConnectionError(error) {
  const message = error instanceof Error ? error.message : String(error);
  return /\b(?:ECONNREFUSED|ECONNRESET|EHOSTUNREACH|ETIMEDOUT|EPIPE)\b|socket hang up/i.test(message);
}

/** @param {Pick<typeof chromium, 'connect'>} engine @param {string} endpoint */
async function connectRemoteBrowser(engine, endpoint) {
  for (let attempt = 1; attempt <= remoteConnectAttempts; attempt += 1) {
    try {
      return await engine.connect(endpoint);
    } catch (error) {
      if (attempt === remoteConnectAttempts || !isTransientConnectionError(error)) throw error;
      await new Promise((resolve) => setTimeout(resolve, remoteConnectRetryDelayMs));
    }
  }
}

/**
 * Browser for the tools: the Dev VM browser when PW_TEST_CONNECT_WS_ENDPOINT is set (see docker/scripts/e2e-vm.sh),
 * a local Chromium otherwise.
 * @param {{ chromium?: Pick<typeof chromium, 'connect' | 'launch'>, env?: Record<string, string | undefined> }} [options]
 */
export async function launchBrowser({ chromium: engine = chromium, env = process.env } = {}) {
  const endpoint = env.PW_TEST_CONNECT_WS_ENDPOINT;
  return endpoint ? connectRemoteBrowser(engine, endpoint) : engine.launch();
}
