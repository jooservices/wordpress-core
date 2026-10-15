<?php
/**
 * Uninstall: data is kept unless JOO_CONTENT_DELETE_DATA_ON_UNINSTALL is true.
 *
 * @package JOOservices\Content
 */

declare(strict_types=1);

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

if ( defined( 'JOO_CONTENT_DELETE_DATA_ON_UNINSTALL' ) && true === JOO_CONTENT_DELETE_DATA_ON_UNINSTALL ) {
	$joo_companies = get_posts(
		array(
			'post_type'      => 'joo_company',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	);
	foreach ( $joo_companies as $joo_company_id ) {
		wp_delete_post( (int) $joo_company_id, true );
	}
	delete_option( 'joo_content_version' );
	delete_option( 'joo_content_flush_rewrite' );
}
