# Docker troubleshooting

## Memory pressure and slow or crashed tests

Symptoms include Playwright `Target crashed` or `Page crashed`, `Failed to start forks worker`, and tests taking minutes or running much slower than usual. These can indicate Docker is short on memory.

Check memory available inside a container and current container usage:

```sh
docker run --rm alpine free -m
docker stats --no-stream
```

On this host, which has 36 GB of memory, set Docker Desktop's memory limit to 24 GB: open **Settings → Resources**, raise the limit, and apply the change. Do not stop or restart other people's services to free memory.

Run only the tests you need. For one Playwright spec, pass its path and the desktop project:

```sh
make test-e2e THEME=jooservices ARGS="path/to/spec.ts --project=desktop"
```

## A site does not respond

- `scripts/docker/web-site list` shows the sites of the shared `wp-web`; `scripts/docker/shared web-up` starts it if it is down.
- `403` on a `<host>.localhost` URL means the host is not a registered site (Apache answers the default vhost). Run `make up` to register this worktree's site.
- If `web-site add` prints an Apache error, another site's config is broken: nothing was changed and the error names the file. Fix or remove that file in `<Company>/config/apache/`, then run `make up` again.
- A site shows the wrong theme or plugin version: the symlinks in `artifacts/wp/wp-content` point to this worktree's folders; `make up` recreates them.
- To start from scratch: `make reset CONFIRM=yes && make up` (removes this worktree's site and database only).

## Browser tests no longer run in Docker

The browser runs on the Dev VM (`docker/scripts/e2e-vm.sh`), so `Target crashed` from Docker memory pressure no longer applies to E2E. If a run cannot start, check `ssh dev true`, that port 9300 is free on the VM (`ss -ltn`), that no other process holds the WordPress lock, and that `workspaces-wp-web` is running. `403 Forbidden` on the WebSocket means the test did not go through the loopback forwarder.
