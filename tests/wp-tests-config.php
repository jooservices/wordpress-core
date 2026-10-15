<?php
/**
 * WordPress test-suite configuration (runs inside the Docker WordPress container).
 *
 * @package JOOservices
 */

define( 'ABSPATH', '/var/www/html/' );
define( 'WP_DEBUG', true );
define( 'DB_NAME', 'wp_tests' );
define( 'DB_USER', getenv( 'WORDPRESS_DB_USER' ) ?: 'wordpress' );
define( 'DB_PASSWORD', getenv( 'WORDPRESS_DB_PASSWORD' ) ?: '' );
define( 'DB_HOST', getenv( 'WORDPRESS_DB_HOST' ) ?: 'db' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );
define( 'WP_TESTS_DOMAIN', 'example.test' );
define( 'WP_TESTS_EMAIL', 'admin@example.test' );
define( 'WP_TESTS_TITLE', 'JOOWT Tests' );
define( 'WP_PHP_BINARY', 'php' );
define( 'WPLANG', '' );
define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', '/workspace/vendor/yoast/phpunit-polyfills' );

$table_prefix = 'wptests_'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
