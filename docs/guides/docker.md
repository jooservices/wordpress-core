# Docker development setup

Theme work uses only shared Workspaces Docker services; the repository builds no image and starts no container of its own. Read the Workspaces `dockers/README.md` and the `workspace-runtimes` skill for the shared groups (`workspaces-shared` apps and `workspaces-cli-shared` CLI runtimes).

| Need | Shared service | How the repository uses it |
|---|---|---|
| PHP, Composer, WP-CLI (gates, unit tests, packaging) | `php-8.5-app` (PHP 8.5, Node 24 binary, all extensions) | `docker/scripts/runtime.sh php\|composer\|wp\|php-node`, one-off `run --rm`, worktree mounted at `/workspace` |
| JavaScript tools | `php-8.5-app` (Node 24, npm, npx are in the same image) | `runtime.sh node\|npm\|npx` |
| Database | shared MariaDB (`mariadb`) | one `wp_JOOS_<n>` schema and scoped user per ticket (`shared-db.sh`) |
| WordPress web server | `wp-web` (Apache + the same PHP image, PHP 8.3 here) | one container at a time, started by `wp-task.sh` under the lock, this worktree mounted through a generated override |
| Mail | shared `mailpit` (UI `http://127.0.0.1:18025`) | `docker/mu-plugins/joowt-mail.php` sends to `mailpit:1025` |
| Browser E2E | Dev VM (`ssh dev`), not Docker | `docker/scripts/e2e-vm.sh`: reverse tunnel of the site port, rsync, run, copy reports back |
| Sonar scan | `sonar-scanner` (CLI project) | `scripts/docker/shared sonar-scan` from the Workspaces root |

## Set up a ticket worktree

For memory problems, slow tests and the shared WordPress lock see the [Docker troubleshooting guide](docker-troubleshooting.md).

```sh
make init-env              # ignored .env with this ticket's database name and random passwords
make deps                  # composer install and npm ci in the shared runtimes
make shared-db-create      # this ticket's schema and user in the shared MariaDB
make up THEME=jooservices  # wp-web with this worktree mounted, WordPress downloaded, installed, theme active
```

Credentials stay in the ignored worktree `.env` and the Workspaces root `.env`. `make shared-db-drop CONFIRM=JOOS-<n>` drops only that ticket's database and user.

## The shared WordPress web container

There is exactly one `wp-web` container (`workspaces-wp-web`, loopback port from `WP_SITE_URL`, default `http://127.0.0.1:8082`). `docker/scripts/wp-task.sh` takes the Workspaces-wide lock `Workspaces/.tmp/jooservices-wp-shared-test.lock`, starts a fresh container for the current worktree (new web root, WordPress core from the WP-CLI download cache, `wp-config.php` for this ticket's database), and runs the task. Integration, E2E, health, seeding, matrix, marketplace and screenshot tasks are therefore serialized across worktrees. PHP lint, analysis and unit work are not: they use one-off CLI containers and can run in parallel.

Select versions with environment variables: `WP_VERSION` (`latest`, a series such as `6.6`, or a full version) and `WP_PHP_VERSION` (8.1 to 8.5; the matching `workspaces/php-app:<version>` image is built on first use). Both default to the latest WordPress on PHP 8.3, the compatibility target of the theme.

```sh
make demo THEME=jooservices
make test-integration THEME=jooservices
WP_VERSION=6.6 make test-integration THEME=jooservices
make test-e2e THEME=jooservices ARGS="specs/shared"
```

`make down` removes the web container (database and uploads are kept). `make reset CONFIRM=yes` also removes the web root, this ticket's uploads volume and its database; it never touches Workspaces core services or Mailpit. Integration tables use the `wptests_` prefix inside the ticket schema.

## E2E: tests here, browser on the Dev VM

Nothing is copied to the VM. `docker/scripts/e2e-vm.sh` (called by `make test-e2e` through `runtime.sh playwright`):

1. starts `playwright run-server` on the Dev VM (`ssh dev`; Playwright 1.64.1 from `~/pw`, the same version as this project) on port 9300 (`E2E_VM_WS_PORT` changes it);
2. opens one ssh session with two tunnels: VM port 9300 to Mac (so the tests reach the browser server) and the site port to the Mac (so the browser on the VM opens `http://127.0.0.1:8082`, the local `wp-web`);
3. runs `playwright test` in the shared `php-8.5-e2e` container, which shares `wp-web`'s network, with `PW_TEST_CONNECT_WS_ENDPOINT` pointing at the VM browser through `tcp-forward.mjs` (the VM server only accepts loopback host names);
4. writes reports and traces here (`artifacts/reports/e2e`), then stops the VM server and the tunnels.

It needs the lock (held by `wp-task.sh`) and `ssh dev` to work. Tools that launch a browser themselves (`screenshots`, `critical`, `marketplace-preview`) still need a remote-browser option and are not covered by this path.

## Owner demo

The owner demo is not part of the test web container. A long-lived demo can be run as a second named instance of the same shared service (`WP_WEB_NAME`, `WP_WEB_PROJECT` and its own port), and it must use the shared MariaDB and Mailpit like everything else.
