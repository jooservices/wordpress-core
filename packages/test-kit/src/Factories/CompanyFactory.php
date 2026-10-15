<?php
/**
 * Company factory (companion plugin).
 *
 * @package JOOservices\TestKit
 */

declare(strict_types=1);

namespace JOOservices\TestKit\Factories;

/**
 * Example: `CompanyFactory::create( array( 'name' => 'ACME', 'pinned' => true, 'from' => '2022-03' ) )`.
 */
final class CompanyFactory {

	/**
	 * Creates a published company.
	 *
	 * @param array<string, mixed> $args name, role, from, to, pinned, url, description, status, order.
	 * @return int Post id.
	 */
	public static function create( array $args = array() ): int {
		$name = (string) ( $args['name'] ?? 'Company ' . wp_generate_password( 4, false ) );
		$id   = (int) ( new \WP_UnitTest_Factory() )->post->create(
			array(
				'post_type'    => 'joo_company',
				'post_title'   => $name,
				'post_status'  => (string) ( $args['status'] ?? 'publish' ),
				'post_excerpt' => (string) ( $args['description'] ?? $name . ' description' ),
				'menu_order'   => (int) ( $args['order'] ?? 0 ),
			)
		);
		update_post_meta( $id, '_joo_company_role', (string) ( $args['role'] ?? 'Engineer' ) );
		update_post_meta( $id, '_joo_company_from', (string) ( $args['from'] ?? '2020-01' ) );
		update_post_meta( $id, '_joo_company_to', (string) ( $args['to'] ?? '' ) );
		update_post_meta( $id, '_joo_company_url', (string) ( $args['url'] ?? '' ) );
		if ( ! empty( $args['pinned'] ) ) {
			update_post_meta( $id, '_joo_company_pinned', true );
		}
		return $id;
	}
}
