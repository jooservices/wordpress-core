#!/usr/bin/env bash
# Exercises the downloadable demo importer on the ticket's clean shared WordPress site.
set -euo pipefail
cd "${JOO_SITE_DIR:?}"

package="${DEMO_PACKAGE:-${JOO_ROOT:?}/artifacts/package/jooservices-demo-content.zip}"
[[ -f "$package" ]] || { echo "ERROR: demo package missing: $package" >&2; exit 1; }

package_hash="$(sha256sum "$package" | awk '{print $1}')"
active_hash="$(wp eval ' $ledger = get_option("joo_demo_import_ledger", array()); echo is_array($ledger) ? (string) ($ledger["hash"] ?? "") : "";')"
[[ -z "$active_hash" ]] || { echo 'ERROR: the test site already has a demo import; reset it before running this clean-site test.' >&2; exit 1; }

test_post_id=""
cleanup() {
  current_hash="$(wp eval ' $ledger = get_option("joo_demo_import_ledger", array()); echo is_array($ledger) ? (string) ($ledger["hash"] ?? "") : "";' 2>/dev/null || true)"
  if [[ "$current_hash" == "$package_hash" ]]; then
    wp joo demo rollback --yes >/dev/null 2>&1 || true
  fi
  if [[ -n "$test_post_id" ]]; then
    wp post delete "$test_post_id" --force >/dev/null 2>&1 || true
  fi
}
trap cleanup EXIT

old_name="$(wp option get blogname)"
old_description="$(wp option get blogdescription)"
hello_id="$(wp post list --post_type=post --name=hello-world --field=ID --format=ids | awk '{print $1}')"
test_post_id="$(wp post create --post_type=post --post_status=publish --post_name=jooservices-user-content --post_title='User content to preserve' --porcelain)"

wp joo demo import "$package" --dry-run
SECONDS=0
wp joo demo import "$package" --batch-size=10
elapsed="$SECONDS"
(( elapsed < 180 )) || { echo "ERROR: import exceeded three minutes (${elapsed}s)." >&2; exit 1; }
wp joo demo diff "$package"
wp joo demo import "$package" --batch-size=10

[[ "$(wp post get "$test_post_id" --field=post_name)" == 'jooservices-user-content' ]] || {
  echo 'ERROR: user content changed during import.' >&2
  exit 1
}
if [[ -n "$hello_id" ]]; then
  wp post get "$hello_id" --field=post_name >/dev/null
fi

wp joo demo rollback --yes
[[ "$(wp option get blogname)" == "$old_name" ]] || { echo 'ERROR: site title was not restored.' >&2; exit 1; }
[[ "$(wp option get blogdescription)" == "$old_description" ]] || { echo 'ERROR: site description was not restored.' >&2; exit 1; }
wp post get "$test_post_id" --field=post_name >/dev/null
if [[ -n "$hello_id" ]]; then
  wp post get "$hello_id" --field=post_name >/dev/null
fi
remaining="$(wp eval 'echo count(get_posts(array("post_type" => array_values(get_post_types()), "post_status" => array_keys(get_post_stati()), "posts_per_page" => -1, "fields" => "ids", "meta_query" => array(array("key" => "_joo_demo_import_id", "compare" => "EXISTS")))));')"
[[ "$remaining" == '0' ]] || { echo "ERROR: rollback left marked posts ($remaining)." >&2; exit 1; }
echo "PASS: clean-site import, zero structural differences, idempotent rerun, owner-content preservation, rollback (${elapsed}s)."
