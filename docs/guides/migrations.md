# Data migrations (demo and staging)

Worktree sites import a stored seed snapshot; the staging site keeps content the owner reviews. When a ticket changes the **data** those sites hold, it ships a migration so every site can be brought up to date without a reseed.

## Two kinds of change, two places

| Change | Where it lives |
|---|---|
| Needed by every installation (schema, options or meta the product reads) | An upgrade routine in `plugins/jooservices-resume` or `packages/theme-core`, versioned, tested and shipped to buyers. No SQL file in this repository. |
| Only the demo or staging data needs it (rename a seeded value, fix demo posts, add a demo option) | A migration file in `migrations/` (development only, never packaged). |

## Writing a migration

`migrations/<UTC YYYYMMDDHHMMSS>-<TICKET>-<name>.sql|php`, for example `20261020093000-JOOS-150-rename-demo-tagline.sql`.

- SQL files use `{prefix}` for the table prefix and run statement by statement. PHP files run with `$wpdb` in scope.
- **Idempotent**: running it twice leaves the same result (`REPLACE`, `DELETE` then `INSERT`, `UPDATE ... WHERE`).
- A failing file stops the run with a non-zero exit; fix it, never edit a file that was already merged (add a new one).

Applied files are recorded in `{prefix}joo_dev_migrations`, so a file that was merged out of order is still applied once (the runner does not use a "highest number" mark).

## When migrations run

| Moment | What happens |
|---|---|
| Worktree `make up` / seed import | After the snapshot import, pending migrations are applied (the snapshot itself is not regenerated for them) |
| `make migrate` | Applies pending migrations to the current site; `make migrate MIGRATE_ARGS=status` lists them |
| `make staging-update` (after a merge into `develop`) | Takes a backup of `wp_staging` when something is pending, then applies the migrations |
| `make test-migrations` (worktree only) | Applies the fixtures in `docker/scripts/migration-fixtures/` twice, forgetting the records in between, to prove the runner and the idempotency pattern work |

Staging is never reseeded by a migration or by `make staging-up`; only `make staging-reseed CONFIRM=yes` does, after a backup.
