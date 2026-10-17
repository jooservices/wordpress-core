#!/usr/bin/env bash
# Installs and activates the plugins of docker/demo-plugins.json (those not marked skipInstall). Runs in the shared PHP CLI container, in the site folder.
set -uo pipefail
cd "${JOO_SITE_DIR:?}"
LIST="${JOO_ROOT:?}/docker/demo-plugins.json"
for slug in $(php -r '$j=json_decode(file_get_contents($argv[1]),true);foreach($j["plugins"] as $p){if(empty($p["skipInstall"]))echo $p["slug"],"\n";}' "$LIST"); do
  if wp plugin is-installed "$slug" 2>/dev/null; then
    wp plugin is-active "$slug" 2>/dev/null || wp plugin activate "$slug"
  else
    wp plugin install "$slug" --activate || echo "warning: could not install $slug (offline?)"
  fi
done
# Plugin settings that make the demo look like the live site.
wp eval '
$opt = get_option( "syntaxhighlighter_settings", array() );
if ( is_array( $opt ) ) { $opt["theme"] = "midnight"; update_option( "syntaxhighlighter_settings", $opt ); }
' 2>/dev/null || true
wp plugin list --status=active --fields=name,version --format=csv
