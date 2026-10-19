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
- Commit first line, either `JOOWT-<n>: <imperative summary>` (max 72 chars after the prefix; `JOOS-<n>` is accepted too) or Conventional Commits `<type>(<scope>): <Sentence-case subject> (JOOWT-<n>)` with a header of at most 120 chars (the form AI Flow uses). Types: feat, fix, docs, style, refactor, perf, test, build, ci, chore, revert. The `commit-msg` hook and the `Validate commit messages` / `Validate PR Title` checks enforce this (`Merge ` and `Revert ` prefixes are allowed).
- One logical change per commit. Never force-push shared branches. Git identity: `Viet Vu <jooservices@gmail.com>`.
- The repository is `jooservices/wordpress-core` on GitHub (public). `develop` is the default branch and the base of every change; `main` is the production branch (hotfixes start there). Changes reach both only through pull requests with green checks.

## Standards
Language: English for code, comments, commits and Jira. See `docs/definition-of-done.md` for gates G1..G9 and the per-level Definition of Done, and the engineering standards in the Jira epic JOOWT-1.

## Pull request checklist
Use `.github/pull_request_template.md`: summary, Jira key, acceptance criteria checked, tests run with results, screenshots for UI, risks, docs updated.

## Hooks
`make hooks` installs the repository hooks (pre-commit lint of staged files, commit-msg format, secrets scan).
