#!/usr/bin/env bash
# Prepares the WordPress site folder of this worktree (runs in the shared PHP CLI container, in the site folder):
# core (cached download) in the requested version, wp-config.php for this ticket's database, .htaccess, and the symlinks
# that put this worktree's themes, plugins, mu-plugins and core package into wp-content (relative, so they resolve in
# every container that mounts Workspaces).
# Environment: JOO_ROOT, JOO_SITE_DIR, WP_VERSION (latest | a series such as 6.6 | a full version), WORDPRESS_DB_*,
# WP_SITE_URL, WP_CLI_CACHE_DIR.
set -euo pipefail
: "${JOO_ROOT:?}" "${JOO_SITE_DIR:?}" "${WORDPRESS_DB_NAME:?}" "${WORDPRESS_DB_USER:?}" "${WORDPRESS_DB_PASSWORD:?}"
mkdir -p "$JOO_SITE_DIR" "${WP_CLI_CACHE_DIR:-/tmp/wp-cli-cache}"
cd "$JOO_SITE_DIR"

want="${WP_VERSION:-latest}"
# A series such as 6.6 resolves to its newest patch release; latest resolves to the newest stable release.
if [[ "$want" == latest || "$want" =~ ^[0-9]+\.[0-9]+$ ]]; then
  requested="$want"
  want="$(php -r '
    $requested = $argv[1];
    $json = @file_get_contents("https://api.wordpress.org/core/stable-check/1.0/");
    $versions = $json ? array_keys((array) json_decode($json, true)) : array();
    $match = array_values(array_filter(
        $versions,
        static fn($v) => 1 === preg_match("/^\\d+\\.\\d+(?:\\.\\d+)?$/D", (string) $v)
            && ("latest" === $requested || $v === $requested || str_starts_with($v, $requested . "."))
    ));
    usort($match, "version_compare");
    echo $match ? end($match) : "";
  ' "$requested")"
  [[ -n "$want" ]] || { echo "ERROR: could not resolve stable WordPress version '$requested'." >&2; exit 2; }
fi
if [[ ! -f wp-includes/version.php ]]; then
  wp core download --version="$want" --locale=en_US
elif [[ "$(wp core version)" != "$want" ]]; then
  wp core download --version="$want" --force --skip-content
fi

wp config create --dbhost="${WORDPRESS_DB_HOST:-mariadb:3306}" --dbname="$WORDPRESS_DB_NAME" --dbuser="$WORDPRESS_DB_USER" \
  --dbpass="$WORDPRESS_DB_PASSWORD" --skip-check --force --extra-php <<'PHP'
define( 'WP_ENVIRONMENT_TYPE', 'local' );
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
define( 'FS_METHOD', 'direct' );
define( 'JOO_DEMO', true );
$joo_site_url = getenv( 'WP_SITE_URL' ) ?: 'http://127.0.0.1:8082';
if ( ! empty( $_SERVER['HTTP_HOST'] ) ) {
	$joo_scheme   = ( ! empty( $_SERVER['HTTPS'] ) && 'off' !== $_SERVER['HTTPS'] ) ? 'https://' : 'http://';
	$joo_site_url = $joo_scheme . $_SERVER['HTTP_HOST'];
}
define( 'WP_HOME', $joo_site_url );
define( 'WP_SITEURL', $joo_site_url );
PHP

if [[ ! -f .htaccess ]]; then
  cat > .htaccess <<'HTACCESS'
# BEGIN WordPress
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
RewriteBase /
RewriteRule ^index\.php$ - [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.php [L]
</IfModule>
# END WordPress
HTACCESS
fi

link() { # $1 = target (absolute), $2 = link path inside the site
  mkdir -p "$(dirname "$2")"
  ln -sfn "$(realpath -m --relative-to="$(dirname "$2")" "$1")" "$2"
}
find wp-content/themes wp-content/plugins -maxdepth 1 -type l -delete 2>/dev/null || true
for dir in "$JOO_ROOT"/themes/*/; do [[ -d "$dir" ]] && link "${dir%/}" "wp-content/themes/$(basename "$dir")"; done
for dir in "$JOO_ROOT"/tests/fixtures/themes/*/; do [[ -d "$dir" ]] && link "${dir%/}" "wp-content/themes/$(basename "$dir")"; done
for dir in "$JOO_ROOT"/plugins/*/; do [[ -d "$dir" ]] && link "${dir%/}" "wp-content/plugins/$(basename "$dir")"; done
# Plugins staged by tools/build-plugin.mjs that have no source folder in this repository.
for dir in "$JOO_ROOT"/artifacts/plugins/*/; do
  [[ -d "$dir" && ! -d "$JOO_ROOT/plugins/$(basename "$dir")" ]] && link "${dir%/}" "wp-content/plugins/$(basename "$dir")"
done
rm -rf wp-content/mu-plugins; link "$JOO_ROOT/docker/mu-plugins" wp-content/mu-plugins
link "$JOO_ROOT/packages/theme-core" wp-content/packages/theme-core
echo "WordPress $(wp core version) site ready in $JOO_SITE_DIR"
