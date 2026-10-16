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

## Shared WordPress test lock

`Waiting for the shared WordPress test lock` means another WordPress task is using the single shared test container. All tickets share `Workspaces/.tmp/jooservices-wp-shared-test.lock`; hold the lock for at most 20 minutes and let the operation finish before retrying.

Inspect the lock owner's PID and worktree from the Workspaces root:

```sh
cat .tmp/jooservices-wp-shared-test.lock/pid
cat .tmp/jooservices-wp-shared-test.lock/worktree
```

The `pid` file identifies the process; the `worktree` file identifies its checkout. Do not remove a live owner's lock. If the recorded PID is dead, the stale lock may be removed:

```sh
rm .tmp/jooservices-wp-shared-test.lock/pid .tmp/jooservices-wp-shared-test.lock/worktree
rmdir .tmp/jooservices-wp-shared-test.lock
```

## Browser tests no longer run in Docker

Playwright runs on the Dev VM (`docker/scripts/e2e-vm.sh`), so `Target crashed` from Docker memory pressure no longer applies to E2E. If a run cannot start, check `ssh dev true`, that no other process holds the WordPress lock, and that the site port is free (`docker ps` shows only `workspaces-wp-web`).
