// End to end check of the MCP demo: WordPress plugin REST with the connection token, then the MCP server over
// Streamable HTTP (initialize, tools/list, one read tool). Secrets come from .env and are never printed.
// Usage: node tools/mcp-smoke.mjs [--wp http://127.0.0.1:8082] [--mcp http://127.0.0.1:3000]
import { readFileSync } from 'node:fs';
import { fromRoot } from './lib/paths.mjs';

/** Parses a Streamable HTTP response body: plain JSON or an SSE stream with `data:` lines. */
export function parseMcpBody(text) {
  const trimmed = text.trim();
  if (trimmed.startsWith('{')) return JSON.parse(trimmed);
  const data = trimmed
    .split('\n')
    .filter((l) => l.startsWith('data:'))
    .map((l) => l.slice(5).trim())
    .filter(Boolean);
  if (!data.length) throw new Error(`unreadable MCP response: ${trimmed.slice(0, 80)}`);
  return JSON.parse(data.at(-1));
}

const env = Object.fromEntries(
  readFileSync(fromRoot('.env'), 'utf8')
    .split('\n')
    .filter((l) => l.includes('=') && !l.startsWith('#'))
    .map((l) => [l.slice(0, l.indexOf('=')), l.slice(l.indexOf('=') + 1)])
);

async function main() {
  const args = process.argv.slice(2);
  const arg = (name, fallback) => (args.includes(name) ? args[args.indexOf(name) + 1] : fallback);
  const wp = arg('--wp', env.WP_SITE_URL ?? 'http://127.0.0.1:8082').replace(/\/$/, '');
  const mcp = arg('--mcp', `http://127.0.0.1:${env.MCP_PORT ?? 3000}`).replace(/\/$/, '');
  const results = [];
  const check = (name, ok, detail = '') => {
    results.push(ok);
    console.log(`${ok ? 'ok  ' : 'FAIL'} ${name}${detail ? ` (${detail})` : ''}`);
  };

  // 1. The plugin REST API accepts the connection token and rejects a wrong one.
  const site = await fetch(`${wp}/wp-json/chatgpt-connector/v1/site`, {
    headers: { Authorization: `Bearer ${env.MCP_WORDPRESS_TOKEN}` },
  });
  check('plugin REST /site with the connection token', site.status === 200, String(site.status));
  const denied = await fetch(`${wp}/wp-json/chatgpt-connector/v1/site`, {
    headers: { Authorization: 'Bearer wrong-token-value' },
  });
  check('plugin REST refuses a wrong token', denied.status === 401 || denied.status === 403, String(denied.status));
  const content = await fetch(`${wp}/wp-json/chatgpt-connector/v1/content?per_page=3`, {
    headers: { Authorization: `Bearer ${env.MCP_WORDPRESS_TOKEN}` },
  });
  const contentBody = content.ok ? await content.json() : null;
  check('plugin REST lists posts', content.status === 200, String(content.status));

  // 2. The MCP server is healthy and speaks MCP with the static secret.
  const health = await fetch(`${mcp}/health`).catch(() => null);
  check('MCP server /health', !!health?.ok, health ? String(health.status) : 'unreachable');
  const headers = {
    'Content-Type': 'application/json',
    Accept: 'application/json, text/event-stream',
    Authorization: `Bearer ${env.MCP_AUTH_SECRET}`,
  };
  const init = await fetch(`${mcp}/mcp`, {
    method: 'POST',
    headers,
    body: JSON.stringify({
      jsonrpc: '2.0',
      id: 1,
      method: 'initialize',
      params: { protocolVersion: '2025-03-26', capabilities: {}, clientInfo: { name: 'joowt-smoke', version: '1' } },
    }),
  });
  const session = init.headers.get('mcp-session-id');
  check('MCP initialize', init.ok && !!session, String(init.status));
  const sessionHeaders = { ...headers, ...(session ? { 'mcp-session-id': session } : {}) };
  await fetch(`${mcp}/mcp`, {
    method: 'POST',
    headers: sessionHeaders,
    body: JSON.stringify({ jsonrpc: '2.0', method: 'notifications/initialized' }),
  });
  const list = await fetch(`${mcp}/mcp`, {
    method: 'POST',
    headers: sessionHeaders,
    body: JSON.stringify({ jsonrpc: '2.0', id: 2, method: 'tools/list' }),
  });
  const tools = list.ok ? (parseMcpBody(await list.text()).result?.tools ?? []) : [];
  check('MCP tools/list', tools.length >= 40, `${tools.length} tools`);
  const bad = await fetch(`${mcp}/mcp`, {
    method: 'POST',
    headers: { ...headers, Authorization: 'Bearer nope' },
    body: '{}',
  });
  check('MCP refuses a wrong secret', bad.status === 401 || bad.status === 403, String(bad.status));

  // 3. A real tool call goes MCP server -> plugin -> WordPress and returns the demo content.
  const call = await fetch(`${mcp}/mcp`, {
    method: 'POST',
    headers: sessionHeaders,
    body: JSON.stringify({
      jsonrpc: '2.0',
      id: 3,
      method: 'tools/call',
      params: { name: 'wordpress_search_content', arguments: { query: 'docker', limit: 3 } },
    }),
  });
  const result = call.ok ? parseMcpBody(await call.text()).result : null;
  const text = JSON.stringify(result ?? {});
  check(
    'MCP tool wordpress_search_content returns posts',
    call.ok && !result?.isError && text.length > 100,
    `${text.length} bytes`
  );
  void contentBody;

  process.exit(results.every(Boolean) ? 0 : 1);
}

if (import.meta.url === `file://${process.argv[1]}`) await main();
