# JOOservices plugins

Two plugins are developed and deployed with the themes.

| Plugin | Folder in WordPress | Source | Purpose |
|---|---|---|---|
| JOOservices Resume | `jooservices-resume` | `plugins/jooservices-resume` | Company history and the Profile and editorial fields module (PHP 8.1+) |
| JOOservices WordPress - MCP | `wordpress-chatgpt` | `../wordpress-mcp/packages/wordpress-plugin` (own repository) | Scoped REST API, connection tokens, audit log and rate limit for the MCP server (PHP 8.3+) |

The MCP server itself is a Node service (`../wordpress-mcp/packages/mcp-server`, Node 24). `../wordpress-sdk` is a PHP 8.5 client library for the WordPress REST API; it is not part of the demo or of a site deployment.

## Local demo

```bash
make demo THEME=jooservices     # WordPress, theme, demo content
make demo-mcp                   # stages both plugins, connects them, starts the MCP server
```

`make demo-mcp` does, in order:

1. adds `MCP_WORDPRESS_TOKEN` and `MCP_AUTH_SECRET` to `.env` (random, never printed);
2. `tools/build-plugin.mjs` stages each plugin in `artifacts/plugins/<slug>` (runtime files only, `composer install --no-dev` for the MCP plugin inside the WordPress container), which is the folder the container mounts, so the demo runs the same files as the zip;
3. recreates the WordPress container once so it sees the mount, activates both plugins;
4. `docker/scripts/mcp-seed.php` creates the MCP connection (hashed token, all scopes, owned by the admin user);
5. builds and starts the MCP server container (`profile mcp`, `http://127.0.0.1:3000`, static secret, reaches WordPress as `http://wordpress`);
6. runs `tools/mcp-smoke.mjs`: plugin REST with the token (and a wrong token), MCP `initialize`, `tools/list` (53 tools), and a real `wordpress_search_content` call that returns the demo posts.

Stop the server with `make mcp-down`. The mu-plugin `docker/mu-plugins/joowt-mcp.php` only relaxes the plugin's rate limit and public-URL check for this local network.

### Plugins on or off
The e2e specs and `make health` assume a plain site; the jooservices.com mirror (Yoast, SyntaxHighlighter and so on) changes the head and the editor. `make plugins-off` deactivates the mirror plugins and the MCP plugin, `make plugins-on` activates them again. With only the MCP plugin active the whole e2e suite passes.

### Connect an AI client locally
Use `http://127.0.0.1:3000/mcp` with the header `Authorization: Bearer <MCP_AUTH_SECRET>` (read the value from `.env`). Static mode is for local use only.

## Build the zips

```bash
make plugins-package            # artifacts/package/jooservices-resume-<v>.zip and wordpress-chatgpt-<v>.zip
node tools/build-plugin.mjs --plugin jooservices-resume
```

`MCP_PLUGIN_SRC` points the build at another checkout of the MCP plugin. The version comes from the plugin header; the MCP repository releases with its own tags (`make plugin-release` there builds the same zip).

## Deploy to the server

Order: JOOservices Resume, MCP plugin, MCP server. Take a backup first (database and `wp-content`).

1. **JOOservices Resume**: upload `jooservices-resume-<v>.zip` (Plugins, Add New, Upload) or `wp plugin install ... --activate`. Add companies and fill your profile. Nothing else to configure. Rollback: deactivate (data stays).
2. **MCP plugin**: upload `wordpress-chatgpt-<v>.zip` and activate (needs PHP 8.3). In wp-admin open the plugin's connections screen, create a connection for an administrator, pick the scopes (start with read scopes), copy the token once (it is stored hashed). Rollback: deactivate; the connection table stays.
3. **MCP server**: deploy from the `wordpress-mcp` repository (`docs/DEPLOYMENT.md`): `make mcp-up` asks for the site URL and the token and writes `.env.prod`; `make prod-https` adds Caddy with TLS (set `MCP_DOMAIN` and `ACME_EMAIL`). In production use OAuth 2.1 "Mixed" mode, not the static secret. Set `WORDPRESS_URL` to the public https URL of the site.
4. **Check**: `curl -s https://<mcp-domain>/health`; in the client call `wordpress_get_site`; in wp-admin the audit log shows the request. Use confirm-gated tools (publish, delete) only after reading the audit log once.

Security notes: never reuse the demo token or secret in production; scopes are the main control, keep write and delete scopes off until needed; rate limits and the audit log stay enabled; the live site already runs plugin 1.4.5, so upgrade it with the zip and check the connection afterwards.

## Tests
`tools/build-plugin.test.mjs` (packaging rules), `tools/mcp-smoke.test.mjs` (response parsing); the live check is `node tools/mcp-smoke.mjs` (run by `make demo-mcp`).
