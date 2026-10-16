<?php
/**
 * Creates or updates a company from a validated row.
 *
 * @package JOOservices\Resume
 */

declare(strict_types=1);

namespace JOOservices\Resume\Model;

use JOOservices\Resume\PostTypes\Company;

/**
 * Single write path for the importer: the row comes from `Transfer\Mapper::company()`, so it is already validated.
 */
final class CompanyWriter {

	/**
	 * Saves a company. Meta goes through the registered sanitisers; the pinned company stays unique.
	 *
	 * @param array<string, mixed> $row    Validated row.
	 * @param int                  $id     Existing company id, or 0 to create.
	 * @param string               $status Post status for a new company.
	 * @return int|\WP_Error Post id.
	 */
	public function save( array $row, int $id = 0, string $status = 'publish' ) {
		$postId = $id > 0 ? $id : wp_insert_post(
			array(
				'post_type'   => Company::TYPE,
				'post_title'  => (string) $row['name'],
				'post_status' => $status,
				'menu_order'  => (int) $row['menu_order'],
			),
			true
		);
		if ( is_wp_error( $postId ) ) {
			return $postId;
		}
		if ( $id > 0 ) {
			wp_update_post(
				array(
					'ID'         => $id,
					'post_title' => (string) $row['name'],
					'menu_order' => (int) $row['menu_order'],
				)
			);
		}
		$json = static fn( array $items ): string => array() === $items ? '' : (string) wp_json_encode( $items );
		$meta = array(
			Keys::META['role']          => $row['role'],
			Keys::META['from']          => $row['from'],
			Keys::META['to']            => $row['to'],
			Keys::META['url']           => $row['url'],
			Keys::META['summary']       => $row['summary'],
			Keys::META['description']   => $row['description'],
			Keys::META['city']          => $row['city'],
			Keys::META['country']       => $row['country'],
			Keys::META['employment']    => $row['employment'],
			Keys::META['industry']      => $row['industry'],
			Keys::META['remote']        => (bool) $row['remote'],
			Keys::META['roles']         => Repeaters::encode( $row['roles'] ),
			Keys::META['achievements']  => Repeaters::encode( $row['achievements'] ),
			Keys::META['technologies']  => $json( $row['technologies'] ),
			Keys::META['custom']        => $json( $row['custom'] ),
			Keys::META['compensation']  => $row['compensation'],
			Keys::META['equity']        => $row['equity'],
			Keys::META['public_fields'] => $json( $row['public_fields'] ),
			'_joo_company_pinned'       => (bool) $row['pinned'],
		);
		foreach ( $meta as $key => $value ) {
			update_post_meta( (int) $postId, $key, $value );
		}
		return (int) $postId;
	}
}
