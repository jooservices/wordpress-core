#!/usr/bin/env bash
# Self-test of tools/docs-compat.mjs: deterministic output and stale detection.
set -u
cd "$(dirname "$0")/../.."
tmp_dir="$(mktemp -d .docs-compat.XXXXXX)"
trap 'rm -rf "$tmp_dir"' EXIT
fail=0
ok() { echo "ok:   $1"; }
bad() { echo "FAIL: $1"; fail=1; }
node tools/docs-compat.mjs >/dev/null && ok "generates" || bad "generate"
a="$(shasum docs/compatibility.md)"; node tools/docs-compat.mjs >/dev/null; b="$(shasum docs/compatibility.md)"
[ "$a" = "$b" ] && ok "deterministic" || bad "deterministic"
node tools/docs-compat.mjs --check >/dev/null 2>&1 && ok "check passes when current" || bad "check current"
cp tests/matrix/plugins.json "$tmp_dir/plugins.json.bak"
node -e 'const fs = require("node:fs"); const path = "tests/matrix/plugins.json"; const data = JSON.parse(fs.readFileSync(path, "utf8")); data.plugins[0].name = "Changed Name"; fs.writeFileSync(path, JSON.stringify(data));'
node tools/docs-compat.mjs --check >/dev/null 2>&1 && bad "stale not detected" || ok "stale file detected"
cp "$tmp_dir/plugins.json.bak" tests/matrix/plugins.json; node tools/docs-compat.mjs >/dev/null
if grep -Fq "Not tested here: WP Rocket, WPML (licensed)" docs/compatibility.md && awk -F'|' '
  $2 ~ /^[[:space:]]*WP Rocket[[:space:]]*$/ && $8 ~ /^[[:space:]]*NOT RUN[[:space:]]*$/ && $9 ~ /Licensed package has not been tested; no compatibility claim is made/ { rocket = 1 }
  $2 ~ /^[[:space:]]*WPML[[:space:]]*$/ && $8 ~ /^[[:space:]]*NOT RUN[[:space:]]*$/ && $9 ~ /Licensed package has not been tested; no compatibility claim is made/ { wpml = 1 }
  END { exit !(rocket && wpml) }
' docs/compatibility.md; then
  ok "licensed plugins remain untested without a compatibility claim"
else
  bad "licensed plugin reason"
fi
grep -q "No theme can guarantee" docs/compatibility.md && ok "disclaimer present" || bad "disclaimer"
exit $fail
