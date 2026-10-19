# Continuous integration and branch rules

Repository: `jooservices/wordpress-core` (public). `develop` is the default branch and the base of every change; `main` is the production branch. Both are protected: changes arrive only through pull requests whose checks are green, whose branch is up to date and whose conversations are resolved. Force pushes and deletions are blocked, merge commits are allowed and branches are deleted after the merge.

## Checks that run on GitHub (required)

| Check | Workflow | What it proves |
|---|---|---|
| `Static analysis` | `ci.yml` | `make lint`, `make analyse`, naming registry, schemas, generated references, docs links, tooling self-tests |
| `Unit tests` | `ci.yml` | `make test-unit` (PHPUnit unit suites and Vitest) |
| `Validate commit messages` | `commitlint.yml` | every commit follows the rules in `.github/commitlint.config.mjs` |
| `Validate PR Title` | `semantic-pr.yml` | the pull request title is a Conventional Commit with a capitalised subject |

`docker/scripts/runtime.sh` runs `php`, `composer`, `node`, `npm` and `npx` from the host when it is outside Workspaces (or `JOOS_HOST_TOOLS=1`), so the same `make` targets work on a runner and for an outside contributor.

## Gates that run on WSL, not on GitHub

Integration (`make test-integration`), E2E (`make test-e2e`), `make perf`, `make test-matrix`, `make package`, the marketplace checks and `make demo` need a WordPress site on the Workspaces shared Docker (`wp-web`, MariaDB) and the Playwright browser on the Dev VM. A hosted runner has neither. The developer and QA AIs of AI Flow run them on WSL as listed in each ticket's Tests section, and release evidence (stories R1 and R11) archives their output. `nightly.yml` keeps the matrix workflow for a manual run (`workflow_dispatch`).

## Commit and title rules

`JOOWT-<n>: <summary>` or `<type>(<scope>): <Subject> (JOOWT-<n>)`, header at most 120 characters; see `CONTRIBUTING.md`.
