# Docker development setup

Theme work uses only the shared Workspaces containers; the repository builds no image and starts no container of its own, and nothing is created per worktree, job or test run. Read the Workspaces `dockers/README.md` and the `workspace-runtimes` skill first.

| Need | Shared container | How the repository uses it |
|---|---|---|
| PHP, Composer, WP-CLI, Node, npm (gates, unit and integration tests, packaging) | `workspaces-cli-php-8.5` (default) and `workspaces-cli-php-8.3` (WordPress, WP-CLI, integration tests) | `docker/scripts/runtime.sh`, a `docker exec` in the long-lived container, the worktree as working directory |
| Database | `workspaces-shared-mariadb-1` | one `wp_JOOS_<n>` schema and scoped user per ticket (`shared-db.sh`) |
| Web server | `workspaces-wp-web` (Apache + PHP 8.3) | one vhost config per worktree site, added with `scripts/docker/web-site` |
| Mail | `workspaces-shared-mailpit-1` | `docker/mu-plugins/joowt-mail.php` sends to `mailpit:1025`; UI `http://127.0.0.1:18025` |
| Browser E2E | Dev VM browser; the test process is a `docker exec` into `wp-web` | `scripts/docker/e2e-vm` through `runtime.sh playwright` |
| Sonar scan | `workspaces-cli-sonar-scanner` | `scripts/docker/shared sonar-scan` from the Workspaces root |

## Set up a ticket worktree

```sh
make init-env              # ignored .env with this ticket's database name and random passwords
make deps                  # composer install and npm ci in the shared PHP/Node container
make shared-db-create      # this ticket's schema and user in the shared MariaDB
make up THEME=jooservices  # the worktree's site: WordPress, wp-config, symlinks, vhost, install, theme active
make demo THEME=jooservices  # seed (from the snapshot when it exists) and health check
```

The worktree's site is the folder `artifacts/wp` (git-ignored): WordPress core (downloaded once, cached in `Workspaces/artifacts/wpcli-cache`), a `wp-config.php` for the ticket database, and relative symlinks in `wp-content` to this worktree's themes, plugins, mu-plugins and `theme-core`. It is served at `http://<ticket>.jooservices-wp-themes.localhost:8082` (for example `http://joos-129.jooservices-wp-themes.localhost:8082`); `*.localhost` resolves to loopback on macOS and on the VM without any hosts file. The site is one config file, `JOOservices/config/apache/<host>.conf`, loaded by `wp-web` (see `scripts/docker/web-site`). Any number of worktrees run their sites and tests in parallel on the one `wp-web`: there is no lock, no restart and no container per worktree.

- `make down`: take the site offline (removes its vhost; folder and database stay).
- `make reset CONFIRM=yes`: remove the vhost, the folder `artifacts/wp` (including uploads) and drop the ticket database. Nothing else is touched.
- `make shared-db-drop CONFIRM=JOOS-<n>`: drop only that ticket's database and user.
- `WP_VERSION` (`latest`, a series such as `6.6`, or a full version) selects the WordPress core of the site; `WP_PHP_VERSION` (8.1 to 8.5) selects the PHP CLI container used by `make test-integration` and `make test-matrix`. The web server runs PHP 8.3.

```sh
make test-integration THEME=jooservices
WP_VERSION=6.6 make test-integration THEME=jooservices
make test-e2e THEME=jooservices ARGS="specs/shared --project=desktop"
```

## Seed snapshots

The demo content is generated and seeded once per dataset, WordPress version and code that shapes the data (the generator, `seed.php`, the Resume plugin source and `theme-core`), then stored as a local artifact in `Workspaces/artifacts/jooservices-wp-themes/snapshots/<dataset>-wp<version>-<code hash>/` (a database dump with a neutral URL, the cloned uploads and a manifest). `make seed`, `make seed-reset`, `make demo` and `make test-matrix` import it into the worktree's site: database import, URL replacement, APFS-cloned uploads (no extra storage), admin password for this ticket, theme and plugin activation, database upgrade. `make seed-reset` always restarts from the pristine snapshot. The first worktree that needs a snapshot generates it (the others wait for it); changing any input changes the key, so a stale snapshot is never used. Remove old snapshots by deleting their folders.

## E2E: tests in wp-web, browser on the Dev VM

Nothing is copied to the VM. `scripts/docker/e2e-vm` (called through `make test-e2e`): starts `playwright run-server` on the Dev VM on a free port (Playwright from `~/pw`, the same version as this project), keeps one persistent reverse tunnel for the site port, opens a per-run tunnel to the browser server, runs the tests as a `docker exec` into `wp-web` (which shares the site network, so the site is reachable at its `.localhost` host from the test process and from the VM browser), writes reports and traces here (`artifacts/reports/e2e`), then stops the server and the per-run tunnel. Parallel runs are supported. It needs `ssh dev` to work.

Tools that open a browser (`screenshots`, `critical`, `marketplace-preview`, `social-cards`) use the same VM browser when `PW_TEST_CONNECT_WS_ENDPOINT` is set (through `runtime.sh playwright`).

Known limit: layout-shift budgets are measured in a browser reached over an ssh tunnel; slower resource delivery shifts layout more than on a local browser, so `make test-matrix` can report CLS above its 0.02 budget (0.03 to 0.07 measured on the home, search and single pages) that a local browser would not.

## Staging site (the project checkout)

The project checkout (`projects/jooservices-wp-themes`, branch `develop`) is the local staging the owner reviews: `http://jooservices-wp-themes.localhost:8082` (`.localhost`, not `.local`, which macOS resolves through mDNS and takes about 5 seconds). It is one more site of `wp-web` (vhost `jooservices-wp-themes.localhost`, database `wp_staging`, shared Mailpit); no container is added. A worktree differs only in being temporary and ticket-sized.

| Command (in the project checkout) | What it does |
|---|---|
| `make staging-up` | Creates the site; seeds it from the stored snapshot **once** (it then sets the option `joo_staging_initialized`; set it yourself when you import content by hand); never reseeds an existing site |
| `make staging-update` | After a merge into `develop`: rebuilds assets, runs `core update-db` and the data migrations, clears caches, health check |
| `make staging-reseed CONFIRM=yes` | Replaces the content with the pristine snapshot (a backup is taken first) |

Rules: staging runs only in the main checkout (not a worktree), only on `develop`, and only with a clean working tree, so it shows committed code only; merge a ticket into `develop` in that checkout, then run `make staging-update`. Before anything that can change the content, a dump is written to `Workspaces/artifacts/jooservices-wp-themes/staging-backups/` (the newest 5 are kept).

## Owner demo

The owner demo moved to the shared services (JOOS-127) and is now the content of the staging site.
