#!/usr/bin/env bash
# Prepares WordPress in the shared wp-web container. Stage "root" fixes ownership of the directories that bind mounts
# create; stage "user" (www-data) downloads the requested core when needed and writes wp-config.php for this ticket.
# Environment: WP_VERSION (latest or a series such as 6.6 or a full version), WORDPRESS_DB_*, WP_SITE_URL.
set -euo pipefail
stage="${1:?root|user}"
cd /var/www/html

if [[ "$stage" == root ]]; then
  mkdir -p wp-content/themes wp-content/plugins wp-content/mu-plugins wp-content/uploads wp-content/languages wp-content/upgrade
  chown www-data:www-data . wp-content wp-content/themes wp-content/plugins wp-content/languages wp-content/upgrade
  chown www-data:www-data wp-content/uploads 2>/dev/null || true
  exit 0
fi

want="${WP_VERSION:-latest}"
# A series such as 6.6 resolves to its newest patch release.
if [[ "$want" =~ ^[0-9]+\.[0-9]+$ ]]; then
  want="$(php -r '
    $series = $argv[1];
    $json = @file_get_contents("https://api.wordpress.org/core/stable-check/1.0/");
    $versions = $json ? array_keys((array) json_decode($json, true)) : array();
    $match = array_values(array_filter($versions, static fn($v) => $v === $series || str_starts_with($v, $series . ".")));
    usort($match, "version_compare");
    echo $match ? end($match) : $series;
  ' "$want")"
fi
if [[ ! -f wp-includes/version.php ]]; then
  wp core download --version="$want" --locale=en_US
elif [[ "$want" != latest && "$(wp core version)" != "$want" ]]; then
  wp core download --version="$want" --force --skip-content
fi

wp config create --dbhost="$WORDPRESS_DB_HOST" --dbname="$WORDPRESS_DB_NAME" --dbuser="$WORDPRESS_DB_USER" \
  --dbpass="$WORDPRESS_DB_PASSWORD" --skip-check --force --extra-php <<'PHP'
define( 'WP_ENVIRONMENT_TYPE', 'local' );
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', '/var/log/php/wp-debug.log' );
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
# The official WordPress image writes this file on first start; plain PHP images do not, and WP-CLI cannot detect
# mod_rewrite, so write the standard rules for pretty permalinks.
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
echo "WordPress $(wp core version) ready in $(hostname)"
