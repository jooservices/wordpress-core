# Static gate evidence

Machine: wsl
Branch: JOOWT-231-core-r1-1-run-the-static-gates-on-develo
Base: origin/develop
Commit at start: 93518f1aec777f38e51606dbd1470de89eb50217
Date (UTC): 2026-10-19T23:28:10Z

Initial gate run. Each command is executed through the shared Workspaces runtime; direct Node commands use the project wrapper that delegates to `/home/joos/Workspaces/scripts/docker/runtime`.

## make lint

```text
headers ok
Could not open input file: vendor/bin/phpcs
make: *** [Makefile:74: lint] Error 1

```
Exit status: 2

## make analyse

```text
Could not open input file: vendor/bin/phpstan
make: *** [Makefile:83: analyse] Error 1

```
Exit status: 2

## make test-tooling

```text
make help ok
ok:   phpcs rejects the violation
FAIL: phpcs rejected clean input
ok:   eslint rejects the violation
FAIL: eslint rejected clean input
ok:   stylelint rejects the violation
FAIL: stylelint rejected clean input
ok:   prettier rejects the violation
ok:   deptrac rejects the violation
FAIL: deptrac rejected clean input
ok:   generates
ok:   deterministic
ok:   check passes when current
ok:   stale file detected
ok:   licensed plugins carry an unsupported reason
ok:   disclaimer present
FAIL: check-version
ok:   check-version rejects the violation
ok:   runtime bundle accepts the allowed files
ok:   runtime bundle rejects the violation
ok:   i18n check passes with a complete dictionary
ok:   i18n check rejects the violation
make: *** [Makefile:90: test-tooling] Error 1

```
Exit status: 2

## node tools/check-registry.mjs (shared runtime)

```text
registry ok (150 names, 68 settings keys, 314 source files scanned)

```
Exit status: 0

## node tools/validate-schemas.mjs (shared runtime)

```text
node:internal/modules/package_json_reader:331
  throw new ERR_MODULE_NOT_FOUND(packageName, fileURLToPath(base), null);
        ^

Error [ERR_MODULE_NOT_FOUND]: Cannot find package 'ajv' imported from /home/joos/Workspaces/JOOservices/worktree/JOOWT-231-core-r1-1-run-the-static-gates-on-develo/tools/validate-schemas.mjs
    at Object.getPackageJSONURL (node:internal/modules/package_json_reader:331:9)
    at packageResolve (node:internal/modules/esm/resolve:784:25)
    at moduleResolve (node:internal/modules/esm/resolve:873:18)
    at defaultResolve (node:internal/modules/esm/resolve:1006:11)
    at #cachedDefaultResolve (node:internal/modules/esm/loader:705:20)
    at #resolveAndMaybeBlockOnLoaderThread (node:internal/modules/esm/loader:725:38)
    at ModuleLoader.resolveSync (node:internal/modules/esm/loader:763:56)
    at #resolve (node:internal/modules/esm/loader:687:17)
    at ModuleLoader.getOrCreateModuleJob (node:internal/modules/esm/loader:607:35)
    at ModuleJob.syncLink (node:internal/modules/esm/module_job:276:33) {
  code: 'ERR_MODULE_NOT_FOUND'
}

Node.js v24.21.0

```
Exit status: 1

## make docs-check

```text
node:internal/modules/package_json_reader:331
  throw new ERR_MODULE_NOT_FOUND(packageName, fileURLToPath(base), null);
        ^

Error [ERR_MODULE_NOT_FOUND]: Cannot find package '@playwright/test' imported from /home/joos/Workspaces/JOOservices/worktree/JOOWT-231-core-r1-1-run-the-static-gates-on-develo/tools/lib/browser.mjs
    at Object.getPackageJSONURL (node:internal/modules/package_json_reader:331:9)
    at packageResolve (node:internal/modules/esm/resolve:784:25)
    at moduleResolve (node:internal/modules/esm/resolve:873:18)
    at defaultResolve (node:internal/modules/esm/resolve:1006:11)
    at #cachedDefaultResolve (node:internal/modules/esm/loader:705:20)
    at #resolveAndMaybeBlockOnLoaderThread (node:internal/modules/esm/loader:725:38)
    at ModuleLoader.resolveSync (node:internal/modules/esm/loader:763:56)
    at #resolve (node:internal/modules/esm/loader:687:17)
    at ModuleLoader.getOrCreateModuleJob (node:internal/modules/esm/loader:607:35)
    at ModuleJob.syncLink (node:internal/modules/esm/module_job:276:33) {
  code: 'ERR_MODULE_NOT_FOUND'
}

Node.js v24.21.0
make: *** [Makefile:169: docs-check] Error 1

```
Exit status: 2

## Preparation: make deps (locked project dependencies)

```text
Installing dependencies from lock file (including require-dev)
Verifying lock file contents can be installed on current platform.
Package operations: 49 installs, 0 updates, 0 removals
  - Downloading squizlabs/php_codesniffer (3.13.6)
  - Downloading dealerdirect/phpcodesniffer-composer-installer (v1.2.1)
  - Downloading phpstan/extension-installer (1.4.3)
  - Downloading antecedent/patchwork (2.2.3)
  - Downloading brain/monkey (2.7.0)
  - Downloading doctrine/instantiator (2.0.0)
  - Downloading php-stubs/wordpress-stubs (v6.9.4)
  - Downloading php-stubs/wp-cli-stubs (v2.12.0)
  - Downloading phpcompatibility/php-compatibility (9.3.5)
  - Downloading phpcompatibility/phpcompatibility-paragonie (1.3.4)
  - Downloading phpcompatibility/phpcompatibility-wp (2.1.8)
  - Downloading phpunit/php-invoker (3.1.1)
  - Downloading phpunit/php-text-template (2.0.4)
  - Downloading phpunit/php-timer (5.0.3)
  - Downloading sebastian/version (3.0.2)
  - Downloading sebastian/diff (4.0.6)
  - Downloading sebastian/cli-parser (1.0.2)
  - Downloading sebastian/type (3.2.1)
  - Downloading sebastian/resource-operations (3.0.4)
  - Downloading sebastian/recursion-context (4.0.7)
  - Downloading sebastian/object-reflector (2.0.4)
  - Downloading sebastian/object-enumerator (4.0.4)
  - Downloading sebastian/global-state (5.0.8)
  - Downloading sebastian/exporter (4.0.9)
  - Downloading sebastian/environment (5.1.5)
  - Downloading sebastian/comparator (4.0.10)
  - Downloading sebastian/code-unit (1.0.8)
  - Downloading phpunit/php-file-iterator (3.0.6)
  - Downloading theseer/tokenizer (1.3.1)
  - Downloading sebastian/lines-of-code (1.0.4)
  - Downloading sebastian/complexity (2.0.3)
  - Downloading sebastian/code-unit-reverse-lookup (2.0.3)
  - Downloading phpunit/php-code-coverage (9.2.32)
  - Downloading phpunit/phpunit (9.6.38)
  - Downloading phpunit/phpcov (8.2.1)
  - Downloading qossmic/deptrac (2.0.4)
  - Downloading szepeviktor/phpstan-wordpress (v2.0.4)
  - Downloading phpcsstandards/phpcsutils (1.2.3)
  - Downloading phpcsstandards/phpcsextra (1.5.1)
  - Downloading wp-coding-standards/wpcs (3.4.1)
  - Downloading wp-phpunit/wp-phpunit (7.1.1)
  - Downloading yoast/phpunit-polyfills (2.0.5)
  0/42 [>---------------------------]   0%
  8/42 [=====>----------------------]  19%
  9/42 [======>---------------------]  21%
 15/42 [==========>-----------------]  35%
 17/42 [===========>----------------]  40%
 22/42 [==============>-------------]  52%
 26/42 [=================>----------]  61%
 32/42 [=====================>------]  76%
 34/42 [======================>-----]  80%
 38/42 [=========================>--]  90%
 41/42 [===========================>]  97%
 42/42 [============================] 100%
  - Installing squizlabs/php_codesniffer (3.13.6): Extracting archive
  - Installing dealerdirect/phpcodesniffer-composer-installer (v1.2.1): Extracting archive
  - Installing phpstan/phpstan (2.3.1): Extracting archive
  - Installing phpstan/extension-installer (1.4.3): Extracting archive
  - Installing hamcrest/hamcrest-php (v3.0.0): Extracting archive
  - Installing mockery/mockery (1.6.15): Extracting archive
  - Installing antecedent/patchwork (2.2.3): Extracting archive
  - Installing brain/monkey (2.7.0): Extracting archive
  - Installing doctrine/instantiator (2.0.0): Extracting archive
  - Installing myclabs/deep-copy (1.14.0): Extracting archive
  - Installing phar-io/version (3.2.1): Extracting archive
  - Installing phar-io/manifest (2.0.4): Extracting archive
  - Installing php-stubs/wordpress-stubs (v6.9.4): Extracting archive
  - Installing php-stubs/wp-cli-stubs (v2.12.0): Extracting archive
  - Installing phpcompatibility/php-compatibility (9.3.5): Extracting archive
  - Installing phpcompatibility/phpcompatibility-paragonie (1.3.4): Extracting archive
  - Installing phpcompatibility/phpcompatibility-wp (2.1.8): Extracting archive
  - Installing phpunit/php-invoker (3.1.1): Extracting archive
  - Installing phpunit/php-text-template (2.0.4): Extracting archive
  - Installing phpunit/php-timer (5.0.3): Extracting archive
  - Installing sebastian/version (3.0.2): Extracting archive
  - Installing sebastian/diff (4.0.6): Extracting archive
  - Installing sebastian/cli-parser (1.0.2): Extracting archive
  - Installing sebastian/type (3.2.1): Extracting archive
  - Installing sebastian/resource-operations (3.0.4): Extracting archive
  - Installing sebastian/recursion-context (4.0.7): Extracting archive
  - Installing sebastian/object-reflector (2.0.4): Extracting archive
  - Installing sebastian/object-enumerator (4.0.4): Extracting archive
  - Installing sebastian/global-state (5.0.8): Extracting archive
  - Installing sebastian/exporter (4.0.9): Extracting archive
  - Installing sebastian/environment (5.1.5): Extracting archive
  - Installing sebastian/comparator (4.0.10): Extracting archive
  - Installing sebastian/code-unit (1.0.8): Extracting archive
  - Installing phpunit/php-file-iterator (3.0.6): Extracting archive
  - Installing theseer/tokenizer (1.3.1): Extracting archive
  - Installing nikic/php-parser (v5.9.0): Extracting archive
  - Installing sebastian/lines-of-code (1.0.4): Extracting archive
  - Installing sebastian/complexity (2.0.3): Extracting archive
  - Installing sebastian/code-unit-reverse-lookup (2.0.3): Extracting archive
  - Installing phpunit/php-code-coverage (9.2.32): Extracting archive
  - Installing phpunit/phpunit (9.6.38): Extracting archive
  - Installing phpunit/phpcov (8.2.1): Extracting archive
  - Installing qossmic/deptrac (2.0.4): Extracting archive
  - Installing szepeviktor/phpstan-wordpress (v2.0.4): Extracting archive
  - Installing phpcsstandards/phpcsutils (1.2.3): Extracting archive
  - Installing phpcsstandards/phpcsextra (1.5.1): Extracting archive
  - Installing wp-coding-standards/wpcs (3.4.1): Extracting archive
  - Installing wp-phpunit/wp-phpunit (7.1.1): Extracting archive
  - Installing yoast/phpunit-polyfills (2.0.5): Extracting archive
  0/45 [>---------------------------]   0%
 43/45 [==========================>-]  95%
 45/45 [============================] 100%
Package qossmic/deptrac is abandoned, you should avoid using it. Use deptrac/deptrac instead.
Generating optimized autoload files
35 packages you are using are looking for funding.
Use the `composer fund` command to find out more!
PHP CodeSniffer Config installed_paths set to ../../phpcompatibility/php-compatibility,../../phpcompatibility/phpcompatibility-paragonie,../../phpcompatibility/phpcompatibility-wp,../../phpcsstandards/phpcsextra,../../phpcsstandards/phpcsutils,../../wp-coding-standards/wpcs
phpstan/extension-installer: Extensions installed
> szepeviktor/phpstan-wordpress: installed

added 411 packages in 11s
npm warn install-scripts 1 package has install scripts not yet covered by allowScripts:
npm warn install-scripts   esbuild@0.28.2 (postinstall: node install.js)
npm warn install-scripts
npm warn install-scripts Run `npm install-scripts ls` to review, or `npm install-scripts approve <pkg>` to allow.

```
Exit status: 0

# Final gate run after locked dependency setup

Commit: 93518f1aec777f38e51606dbd1470de89eb50217
Date (UTC): 2026-10-19T23:29:12Z

## make lint

```text
headers ok
........ 8 / 8 (100%)


Time: 3.33 secs; Memory: 14MB

Checking formatting...
All matched files use Prettier code style!

```
Exit status: 0

## make analyse

```text

 [OK] No errors


 [OK] No errors


 -------------------- -----
  Report
 -------------------- -----
  Violations           0
  Skipped violations   0
  Uncovered            0
  Allowed              517
  Warnings             0
  Errors               0
 -------------------- -----


```
Exit status: 0

## make test-tooling

```text
make help ok
ok:   phpcs rejects the violation
FAIL: phpcs rejected clean input
ok:   eslint rejects the violation
ok:   eslint accepts clean input
ok:   stylelint rejects the violation
ok:   stylelint accepts clean input
ok:   prettier rejects the violation
ok:   deptrac rejects the violation
FAIL: deptrac rejected clean input
ok:   generates
ok:   deterministic
ok:   check passes when current
ok:   stale file detected
ok:   licensed plugins carry an unsupported reason
ok:   disclaimer present
FAIL: check-version
ok:   check-version rejects the violation
ok:   runtime bundle accepts the allowed files
ok:   runtime bundle rejects the violation
ok:   i18n check passes with a complete dictionary
ok:   i18n check rejects the violation
make: *** [Makefile:90: test-tooling] Error 1

```
Exit status: 2

## node tools/check-registry.mjs (shared runtime)

```text
registry ok (150 names, 68 settings keys, 314 source files scanned)

```
Exit status: 0

## node tools/validate-schemas.mjs (shared runtime)

```text
ok   valid   docs/spec/examples/jooservices.json
ok   valid   docs/spec/examples/magazine.json
ok   valid   docs/spec/examples/portfolio.json
ok   valid   docs/spec/examples/starter.json
ok   valid   themes/jooservices/joo-theme.json
ok   valid   themes/starter/joo-theme.json
ok   invalid bad-core-range.json is rejected
ok   invalid bad-enum.json is rejected
ok   invalid bad-slug.json is rejected
ok   invalid bad-type.json is rejected
ok   invalid missing-required.json is rejected
ok   invalid unknown-key.json is rejected
ok   block   packages/theme-core/resources/blocks/author-box/block.json
ok   block   packages/theme-core/resources/blocks/breadcrumbs/block.json
ok   block   packages/theme-core/resources/blocks/category-tree/block.json
ok   block   packages/theme-core/resources/blocks/companies/block.json
ok   block   packages/theme-core/resources/blocks/featured-slider/block.json
ok   block   packages/theme-core/resources/blocks/lang-switcher/block.json
ok   block   packages/theme-core/resources/blocks/not-found/block.json
ok   block   packages/theme-core/resources/blocks/post-meta/block.json
ok   block   packages/theme-core/resources/blocks/profile-card/block.json
ok   block   packages/theme-core/resources/blocks/reading-progress/block.json
ok   block   packages/theme-core/resources/blocks/related-posts/block.json
ok   block   packages/theme-core/resources/blocks/search-trigger/block.json
ok   block   packages/theme-core/resources/blocks/tag-list/block.json
ok   block   packages/theme-core/resources/blocks/terminal-dock/block.json
ok   block   packages/theme-core/resources/blocks/theme-toggle/block.json
ok   block   packages/theme-core/resources/blocks/toc/block.json
ok   block   packages/theme-core/resources/blocks/top-topics/block.json
ok   block   packages/theme-core/tests/Fixtures/blocks/boom/block.json
ok   block   packages/theme-core/tests/Fixtures/blocks/hello/block.json
ok   block   packages/theme-core/tests/Fixtures/blocks/hidden/block.json
ok   settings descriptor (68 keys) is consistent
ok   valid   plugins/jooservices-resume/schema/examples/company.json - No errors
ok   invalid company bad-month.json is rejected
ok   invalid company leaks-private-field.json is rejected

all schema checks passed

```
Exit status: 0

## make docs-check

```text
buyer documentation links and anchors are valid

```
Exit status: 0

## make test-tooling in the shared PHP runtime

Command: /home/joos/Workspaces/scripts/docker/runtime php make test-tooling

```text
make help ok
ok:   phpcs rejects the violation
ok:   phpcs accepts clean input
ok:   eslint rejects the violation
ok:   eslint accepts clean input
ok:   stylelint rejects the violation
ok:   stylelint accepts clean input
ok:   prettier rejects the violation
ok:   deptrac rejects the violation
ok:   deptrac accepts clean input
ok:   generates
ok:   deterministic
ok:   check passes when current
tests/tooling/docs-compat.sh: line 13: python3: command not found
FAIL: stale not detected
ok:   licensed plugins carry an unsupported reason
ok:   disclaimer present
ok:   check-version passes for the real core
ok:   check-version rejects the violation
ok:   runtime bundle accepts the allowed files
ok:   runtime bundle rejects the violation
ok:   i18n check passes with a complete dictionary
tests/tooling/run.sh: line 64: python3: command not found
FAIL: i18n check accepted a violation
make: *** [Makefile:90: test-tooling] Error 1

```
Exit status: 2

## make test-tooling with PHP, Node, and Python dispatched to shared runtimes

Command: make test-tooling; exported command wrappers dispatch each tool to scripts/docker/runtime.

```text
make help ok
ok:   phpcs rejects the violation
ok:   phpcs accepts clean input
ok:   eslint rejects the violation
ok:   eslint accepts clean input
ok:   stylelint rejects the violation
ok:   stylelint accepts clean input
ok:   prettier rejects the violation
ok:   deptrac rejects the violation
ok:   deptrac accepts clean input
ok:   generates
ok:   deterministic
ok:   check passes when current
ok:   stale file detected
ok:   licensed plugins carry an unsupported reason
ok:   disclaimer present
ok:   check-version passes for the real core
ok:   check-version rejects the violation
ok:   runtime bundle accepts the allowed files
ok:   runtime bundle rejects the violation
ok:   i18n check passes with a complete dictionary
ok:   i18n check rejects the violation
ok:   bad commit message rejected
ok:   good commit message accepted
ok:   conventional commit accepted
ok:   conventional lower-case subject rejected
ok:   conventional unknown type rejected
ok:   staged secret rejected
ok:   .env rejected
ok:   phpcs violation rejected
ok:   merge message accepted

```
Exit status: 0

# Acceptance result

All six acceptance gates pass after installing the existing locked dependencies. The final result is based on these commands:

| Acceptance command | Result |
| --- | --- |
| make lint | PASS (exit 0) |
| make analyse | PASS (exit 0) |
| make test-tooling | PASS (exit 0), internal PHP/Node/Python commands dispatched to shared runtimes |
| node tools/check-registry.mjs | PASS (exit 0), shared Node runtime |
| node tools/validate-schemas.mjs | PASS (exit 0), shared Node runtime |
| make docs-check | PASS (exit 0) |

The worktree initially had no vendor or node_modules directories. The repository's make deps command installed the exact locked dependencies (49 Composer installs and 411 npm packages, no updates or removals). No source or tool configuration changes were needed.

The first direct make test-tooling run selected host tools and failed clean-input assertions. The final run routed its tool commands to the shared runtimes as required in this WSL workspace. The command used was:

```bash
php() { /home/joos/Workspaces/scripts/docker/runtime php php "$@"; }
node() { /home/joos/Workspaces/scripts/docker/runtime node node "$@"; }
npx() { /home/joos/Workspaces/scripts/docker/runtime node npx "$@"; }
python3() { /home/joos/Workspaces/scripts/docker/runtime python python3 "$@"; }
export -f php node npx python3
make test-tooling
```

No gate failures remain; no Bug was required.

The tooling self-test regenerates docs/compatibility.md. Because this worktree has no ignored historical artifacts/reports/matrix snapshots, that self-test temporarily rewrote the generated page with NOT RUN rows; I restored the tracked page after the test. It has no remaining diff.
