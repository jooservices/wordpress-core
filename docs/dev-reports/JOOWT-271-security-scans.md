# JOOWT-271 dependency, secret, and static scan evidence

Date: 2026-10-20

Repository: `wordpress-core`

Branch: `JOOWT-271-core-r8-3-dependency-secret-and-static-s`

## Results

| Scan | Command | Result |
| --- | --- | --- |
| Composer audit | `scripts/docker/runtime php composer audit --locked --no-interaction --format=json` | Exit 1. Zero security advisories. Composer reports abandoned dev-only `qossmic/deptrac`, replaced by `deptrac/deptrac`; this is a maintenance notice, not a vulnerability. |
| Production npm audit | `scripts/docker/runtime node npm audit --omit=dev --json` | Exit 0. 13 production dependencies; 0 vulnerabilities at every severity. |
| Gitleaks Git history | `scripts/docker/runtime gitleaks gitleaks git --redact --report-format json --report-path artifacts/reports/security/JOOWT-271/gitleaks-git.json --no-banner .` | Exit 0. 0 findings. |
| Gitleaks worktree | `scripts/docker/runtime gitleaks gitleaks dir --redact --report-format json --report-path artifacts/reports/security/JOOWT-271/gitleaks-dir.json --no-banner .` | Exit 0. 0 findings. |
| SonarQube | `scripts/docker/shared sonar-add` / `sonar-scan` | Blocked before scan: shared service is UP, but its API reports invalid administrative authentication. No project was created and no static analysis result is available. |

## Findings and dispositions

- Composer reported no advisories. The only Composer audit item is abandoned `qossmic/deptrac`, which is listed in the root `require-dev` section and is not shipped as a runtime dependency. No Critical or High dependency vulnerability was found. No package replacement was made because it is outside the security fixes identified by this scan and the ticket excludes convenience upgrades.
- npm reported no production dependency vulnerabilities.
- Both Gitleaks scans found no secrets. They ran with `--redact` (100% redaction); both report files contain empty finding lists, and no secret value is present in scan output.
- SonarQube's authentication validation returned HTTP 200 with `valid=false`; project search returned HTTP 401. `sonar-add` reported “already exists” because the shared helper treats every non-404 response as a collision. `sonar-clean` listed no temporary projects. The existing project was not reused or deleted, and no project was created. The shared `WORKSPACES_SONAR_ADMIN_PASSWORD` must be restored to a value accepted by this SonarQube instance before the scan can run.
- The repository has no `sonar-project.properties` or configured coverage report path; coverage was not measured and no 0% coverage claim is made.

Raw command output is saved in `artifacts/reports/security/JOOWT-271/` in the worktree. The directory is ignored by the repository's existing `.gitignore`; this tracked report preserves the results and the SonarQube blocker.

## Verification limits

No Critical or High findings appeared in the completed dependency and secret scans. The SonarQube scan remains outstanding, so this report does not claim that all acceptance criteria are met.
