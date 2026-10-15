<?php
/**
 * Creates (or updates) the MCP connection of the demo: one token, all scopes, owned by the admin user.
 * Run with: MCP_WORDPRESS_TOKEN=... wp eval-file /scripts/mcp-seed.php
 *
 * @package JOOservices\Docker
 */

// phpcs:disable

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit( "Run via wp eval-file\n" );
}

$autoload = WP_PLUGIN_DIR . '/wordpress-chatgpt/vendor/autoload.php';
if ( ! is_file( $autoload ) ) {
	WP_CLI::error( 'The MCP plugin is not staged: run node tools/build-plugin.mjs --plugin wordpress-chatgpt' );
}
require_once $autoload;

use JOOservices\WordPressMcp\Auth\ConnectionAuthenticator;
use JOOservices\WordPressMcp\Auth\ScopeChecker;
use JOOservices\WordPressMcp\Database\Schema;

$token = (string) getenv( 'MCP_WORDPRESS_TOKEN' );
if ( strlen( $token ) < 16 ) {
	WP_CLI::error( 'MCP_WORDPRESS_TOKEN is missing or too short (run make mcp-env).' );
}

Schema::install();
global $wpdb;
$table = Schema::connectionsTable();
$hash  = ConnectionAuthenticator::hashToken( $token );
$admin = get_users( array( 'role' => 'administrator', 'number' => 1, 'orderby' => 'ID', 'fields' => 'ID' ) );
$owner = (int) ( $admin[0] ?? 1 );
$id    = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE token_hash = %s LIMIT 1", $hash ) );

if ( $id ) {
	$wpdb->update( $table, array( 'scopes' => wp_json_encode( ScopeChecker::ALL_SCOPES ), 'active' => 1, 'user_id' => $owner ), array( 'id' => (int) $id ) );
	WP_CLI::success( "MCP connection updated (id {$id}, user {$owner})." );
} else {
	$wpdb->insert(
		$table,
		array(
			'name'       => 'JOOWT demo (MCP server)',
			'token_hash' => $hash,
			'user_id'    => $owner,
			'scopes'     => wp_json_encode( ScopeChecker::ALL_SCOPES ),
			'active'     => 1,
			'created_at' => current_time( 'mysql', true ),
		)
	);
	WP_CLI::success( "MCP connection created for user {$owner}." );
}
