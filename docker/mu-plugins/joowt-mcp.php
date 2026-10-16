<?php
/**
 * Plugin Name: JOOWT MCP demo helpers
 * Description: Local Docker only. The MCP service reaches WordPress by the in-network `wordpress` host; the loopback site URL and port are configured per worktree.
 *
 * @package JOOservices\Docker
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'MCP_RATE_LIMIT_MAX' ) ) {
	define( 'MCP_RATE_LIMIT_MAX', 10000 );
}

add_filter( 'jooservices_mcp_skip_public_url_verify', '__return_true' );
