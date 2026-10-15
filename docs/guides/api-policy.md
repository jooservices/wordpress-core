# Public API policy

Only symbols tagged `@api` in `packages/theme-core/src` are stable. Everything else is `@internal` and may change in any release.

- Tag every public class, interface, constant and method intended for themes with `@api` in its docblock; tag implementation details `@internal`.
- Hooks named `joo/...` are part of the API once documented with `@since` and `@api`.
- Deprecation: keep the symbol for at least one minor release, call `Hooks::deprecated()` or `_deprecated_function()`, document the replacement in `CHANGELOG.md`.
- A breaking change to an `@api` symbol requires a major version bump (`tools/release.php --bump=major`) and an ADR.
- Check before every release: `tools/bc-check.sh <last-tag>` (Roave BC check on the core subtree, markdown report in `artifacts/reports/bc/`).
- Architecture rule enforced by deptrac: core depends on WordPress and itself only; themes depend on core and WordPress only.
