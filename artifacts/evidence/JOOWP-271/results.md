# JOOWP-271 security scan evidence

Run date: 2026-10-23 (UTC)

Commands: `composer audit --format=json` (Composer 2.10.3), `npm audit --omit=dev --json` (npm 11.19.1), and `gitleaks detect --source . --redact --no-banner --report-format json --report-path /tmp/joowp-271-gitleaks.json`.

## Results

| Scan | Result | Evidence |
| --- | --- | --- |
| `composer audit` | Pass; 0 advisories and 0 abandoned packages | [`composer-audit.json`](composer-audit.json) |
| `npm audit --omit=dev` | Pass; 0 vulnerabilities (including 0 High and 0 Critical) | [`npm-audit.json`](npm-audit.json) |
| Gitleaks | Pass; 291 commits scanned, no leaks found | [`gitleaks-redacted.json`](gitleaks-redacted.json) |
| SonarQube | Not run; out of scope under the owner amendment dated 2026-10-23. No scan result was produced. | — |

The Composer audit previously failed on the abandoned `qossmic/deptrac` development dependency. The dependency now uses its maintained successor, `deptrac/deptrac` `^2.0` (locked at 2.0.7), within the existing major-version policy. The rerun reports no advisories or abandoned packages.

No Critical or High finding was reported by the completed scans, so no Bug disposition was needed. The Gitleaks result and this report contain no secret values. SonarQube has no finding disposition because its scan was excluded by the owner amendment.
