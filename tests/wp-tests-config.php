<?php
/**
 * WordPress test-suite configuration (runs in the shared PHP CLI container; the site is JOO_SITE_DIR, the checkout is the parent of this folder).
 *
 * @package JOOservices
 */

define( 'ABSPATH', rtrim( getenv( 'JOO_SITE_DIR' ) ?: '/var/www/html', '/' ) . '/' );
define( 'WP_DEBUG', true );
define( 'DB_NAME', getenv( 'WORDPRESS_DB_NAME' ) ?: 'wp_JOOS_0' );
define( 'DB_USER', getenv( 'WORDPRESS_DB_USER' ) ?: 'wp_JOOS_0' );
define( 'DB_PASSWORD', getenv( 'WORDPRESS_DB_PASSWORD' ) ?: '' );
define( 'DB_HOST', getenv( 'WORDPRESS_DB_HOST' ) ?: 'mariadb:3306' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );
define( 'WP_TESTS_DOMAIN', 'example.test' );
define( 'WP_TESTS_EMAIL', 'admin@example.test' );
define( 'WP_TESTS_TITLE', 'JOOWT Tests' );
define( 'WP_PHP_BINARY', 'php' );
define( 'WPLANG', '' );
define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills' );

$table_prefix = 'wptests_'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
