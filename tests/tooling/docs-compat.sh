#!/usr/bin/env bash
# Self-test of tools/docs-compat.mjs: deterministic output and stale detection.
set -u
cd "$(dirname "$0")/../.."
fail=0
ok() { echo "ok:   $1"; }
bad() { echo "FAIL: $1"; fail=1; }
node tools/docs-compat.mjs >/dev/null && ok "generates" || bad "generate"
a="$(shasum docs/compatibility.md)"; node tools/docs-compat.mjs >/dev/null; b="$(shasum docs/compatibility.md)"
[ "$a" = "$b" ] && ok "deterministic" || bad "deterministic"
node tools/docs-compat.mjs --check >/dev/null 2>&1 && ok "check passes when current" || bad "check current"
cp tests/matrix/plugins.json /tmp/plugins.json.bak
python3 - <<'PY'
import json
d=json.load(open('tests/matrix/plugins.json')); d['plugins'][0]['name']='Changed Name'
json.dump(d,open('tests/matrix/plugins.json','w'))
PY
node tools/docs-compat.mjs --check >/dev/null 2>&1 && bad "stale not detected" || ok "stale file detected"
cp /tmp/plugins.json.bak tests/matrix/plugins.json; node tools/docs-compat.mjs >/dev/null
grep -q "UNSUPPORTED" docs/compatibility.md && grep -q "licensed copy is unavailable" docs/compatibility.md && ok "licensed plugins carry an unsupported reason" || bad "licensed plugin reason"
grep -q "No theme can guarantee" docs/compatibility.md && ok "disclaimer present" || bad "disclaimer"
exit $fail
