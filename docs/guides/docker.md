# Docker development setup

JOOservices theme worktrees share the Workspaces Docker network `workspaces-shared` and its `mariadb` service. The repo does not start another MariaDB container or create a project bridge network.

## Set up a ticket worktree

For Docker memory problems, crashed browser runs, slow tests, and the shared WordPress test lock, see the [Docker troubleshooting guide](docker-troubleshooting.md).

From the Workspaces root, start the shared database and long-lived developer runtimes once:

```sh
scripts/docker/shared up mariadb
scripts/docker/shared runtimes-up php-app playwright-runner
```

In the ticket worktree, generate its ignored `.env`, install locked dependencies, create the ticket database, and start WordPress:

```sh
make init-env
make deps
make shared-db-create
make up THEME=jooservices
```

`make init-env` reads the `JOOS-<n>` key from the worktree branch and creates `DB_NAME=wp_JOOS_<n>`, a matching scoped database user, and local passwords. The WordPress test container uses only that database. `make shared-db-drop CONFIRM=JOOS-<n>` drops only the named ticket database and its matching user. Credentials stay in the ignored Workspaces and worktree `.env` files.

The shared test Compose project is fixed at `joowt-wp-shared-test`. It has one PHP 8.3 Apache WordPress container and a per-demo Mailpit service. Worktrees select the container's source mounts through `docker/scripts/wp-task.sh`; the shared lock at `Workspaces/.tmp/jooservices-wp-shared-test.lock` serializes integration, E2E, health, and other WordPress operations. PHP, Node, and Playwright one-off commands mount the selected checkout at `/workspace`, so PHP lint, analysis, and unit work can run concurrently across worktrees.

## URL and WordPress versions

`WP_SITE_URL` is the single source for the host port. A fresh ticket `.env` defaults to `http://127.0.0.1:8082`; Compose derives the published port from this URL. The shared Node and Playwright containers use the in-network WordPress service name `http://wordpress` by default. `make health` and `make test-e2e` therefore target the selected shared test container and do not fall back to port 8080. Set `E2E_BASE_URL` only when intentionally testing another URL reachable from the runner container.

Use the latest WordPress image by default or select the 6.6 minimum for integration tests:

```sh
make demo THEME=jooservices
make test-integration THEME=jooservices
WP_VERSION=6.6 make test-integration THEME=jooservices
make test-e2e THEME=jooservices ARGS="tests/e2e/specs/shared"
```

Both images use PHP 8.3. Each WordPress task mounts the active worktree in the single test web container and renews its anonymous core volume so the selected version's files are actually used; Mailpit remains running between tasks. The database schema and uploads volume are ticket-scoped, so media survives web-container recreation without crossing ticket boundaries. Integration tables use the separate `wptests_` table prefix within the ticket schema. `make down` stops only the shared test WordPress and Mailpit containers. `make reset CONFIRM=yes` removes those test containers, the current ticket's uploads volume, and only that ticket database; it does not stop any Workspaces core service.

## Existing main demo

The owner's existing `main` demo on project `jooservices-wp-themes` and port 8080 is still on its previous isolated MariaDB and Compose network. This feature does not stop, recreate, reset, or migrate that running stack. The new ticket worktree targets a different Compose project and uses port 8082 by default.

After an owner-approved migration, use this sequence for the main demo. It is documented here and has **not** been run:

1. Record the current main Compose configuration and make a private, verified backup of its WordPress database and ignored `.env`. Keep both the old container and volume available for rollback.
2. Create dedicated main-demo schemas and a least-privilege database account in shared MariaDB. Keep the test ticket's `wp_JOOS_<n>` schema separate from the main demo schemas.
3. Export the old `wordpress` site schema with a transaction-consistent dump, import it into the new main-demo schema, and verify the imported row counts before changing the running WordPress container.
4. Update only the main demo's private database settings to use `mariadb` on `workspaces-shared`. Keep its Compose project name, published port 8080, WordPress version, and Mailpit endpoint unchanged for cutover.
5. During the approved cutover, reconnect/recreate only the main demo's WordPress service, then check the homepage, login, admin writes, media, permalinks, mail capture, and database connectivity. If any check fails, restore the prior private settings and reconnect to the old MariaDB volume.
6. Retain the old MariaDB container and volume until the owner accepts the migration. Remove them only in a separate, explicitly approved cleanup step.

Do not run the ticket database scripts for the main demo. The running main stack remains outside the worktree test lock and its migration is pending an owner-approved step.
