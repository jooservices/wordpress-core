import { chromium } from '@playwright/test';

/**
 * Browser for the tools: the Dev VM browser when PW_TEST_CONNECT_WS_ENDPOINT is set (see docker/scripts/e2e-vm.sh),
 * a local Chromium otherwise.
 * @param {{ chromium?: Pick<typeof chromium, 'connect' | 'launch'>, env?: Record<string, string | undefined> }} [options]
 */
export function launchBrowser({ chromium: engine = chromium, env = process.env } = {}) {
  const endpoint = env.PW_TEST_CONNECT_WS_ENDPOINT;
  return endpoint ? engine.connect(endpoint) : engine.launch();
}
