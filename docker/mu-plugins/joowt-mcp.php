<?php
/**
 * Plugin Name: JOOWT MCP demo helpers
 * Description: Local Docker only. The MCP server reaches WordPress as http://wordpress while the site URL is http://127.0.0.1:8080, so the plugin's public-URL check is skipped and its rate limit is relaxed.
 *
 * @package JOOservices\Docker
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'MCP_RATE_LIMIT_MAX' ) ) {
	define( 'MCP_RATE_LIMIT_MAX', 10000 );
}

add_filter( 'jooservices_mcp_skip_public_url_verify', '__return_true' );
