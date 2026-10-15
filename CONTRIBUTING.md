# Contributing

## Setup
Requirements: Docker Desktop, Node >= 22, Composer 2, PHP 8.1+ (CLI), GNU make.

```bash
make help            # every task
make init-env        # creates .env with random secrets (never committed)
make demo THEME=jooservices
```

## Branches and commits
- Branches: `feature/JOOWT-<n>-<slug>`, `fix/JOOWT-<n>-<slug>`, `chore/JOOWT-<n>-<slug>`.
- Commit first line: `JOOWT-<n>: <imperative summary>` (max 72 chars). The regex enforced by the `commit-msg` hook is `^JOOWT-[0-9]+: .{1,72}$` (`Merge ` and `Revert ` prefixes are allowed).
- One logical change per commit. Never force-push shared branches. Git identity: `Viet Vu <jooservices@gmail.com>`.
- This repository is local-only: do not add a remote or push unless the owner asks.

## Standards
Language: English for code, comments, commits and Jira. See `docs/definition-of-done.md` for gates G1..G9 and the per-level Definition of Done, and the engineering standards in the Jira epic JOOWT-1.

## Pull request checklist
Use `.github/pull_request_template.md`: summary, Jira key, acceptance criteria checked, tests run with results, screenshots for UI, risks, docs updated.

## Hooks
`make hooks` installs the repository hooks (pre-commit lint of staged files, commit-msg format, secrets scan).
