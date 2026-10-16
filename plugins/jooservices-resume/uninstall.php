<?php
/**
 * Uninstall: data is kept unless JOO_RESUME_DELETE_DATA_ON_UNINSTALL is true.
 *
 * @package JOOservices\Resume
 */

declare(strict_types=1);

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

if ( defined( 'JOO_RESUME_DELETE_DATA_ON_UNINSTALL' ) && true === JOO_RESUME_DELETE_DATA_ON_UNINSTALL ) {
	$jooservices_resume_companies = get_posts(
		array(
			'post_type'      => 'joo_company',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	);
	foreach ( $jooservices_resume_companies as $jooservices_resume_company_id ) {
		wp_delete_post( (int) $jooservices_resume_company_id, true );
	}
	delete_post_meta_by_key( '_joo_featured' );
	delete_post_meta_by_key( '_joo_featured_order' );
	foreach ( array( 'joo_short_description', 'joo_image_id', 'joo_intro', 'joo_icon' ) as $jooservices_resume_term_key ) {
		delete_metadata( 'term', 0, $jooservices_resume_term_key, '', true );
	}
	foreach ( array( 'joo_title', 'joo_short_bio', 'joo_avatar_id', 'joo_socials' ) as $jooservices_resume_user_key ) {
		delete_metadata( 'user', 0, $jooservices_resume_user_key, '', true );
	}
	delete_option( 'joo_resume_version' );
	delete_option( 'joo_resume_flush_rewrite' );
}
