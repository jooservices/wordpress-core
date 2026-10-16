<?php
/**
 * Reads companies as typed records.
 *
 * @package JOOservices\Resume
 */

declare(strict_types=1);

namespace JOOservices\Resume\Model;

use JOOservices\Resume\PostTypes\Company;

/**
 * Single read path for the front end, REST and exports. Existing companies keep working: every new field is empty.
 */
final class CompanyRepository {

	public const DEFINITIONS_OPTION = 'joo_resume_custom_fields';

	/**
	 * One company by id, or null when it is not a company.
	 *
	 * @param int $id Post id.
	 */
	public function find( int $id ): ?CompanyRecord {
		$post = get_post( $id );
		return $post instanceof \WP_Post && Company::TYPE === $post->post_type ? $this->record( $post ) : null;
	}

	/**
	 * Companies: the pinned one first, then by `manual` order (menu order, then newest start) or by `date` (newest start).
	 *
	 * @param string $order         `manual` or `date`.
	 * @param bool   $publishedOnly Only published companies.
	 * @return list<CompanyRecord>
	 */
	public function all( string $order = 'manual', bool $publishedOnly = true ): array {
		$posts   = get_posts(
			array(
				'post_type'      => Company::TYPE,
				'post_status'    => $publishedOnly ? 'publish' : 'any',
				'posts_per_page' => 200, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- a resume is short; bounded.
				'no_found_rows'  => true,
			)
		);
		$records = array_map( fn( \WP_Post $post ): CompanyRecord => $this->record( $post ), $posts );
		usort(
			$records,
			static function ( CompanyRecord $a, CompanyRecord $b ) use ( $order ): int {
				if ( $a->pinned !== $b->pinned ) {
					return $a->pinned ? -1 : 1;
				}
				if ( 'manual' === $order && $a->menuOrder !== $b->menuOrder ) {
					return $a->menuOrder <=> $b->menuOrder;
				}
				return strcmp( $b->from, $a->from );
			}
		);
		return $records;
	}

	/**
	 * The pinned current company.
	 */
	public function pinned(): ?CompanyRecord {
		foreach ( $this->all( 'date' ) as $record ) {
			if ( $record->pinned ) {
				return $record;
			}
		}
		return null;
	}

	/**
	 * Owner-defined custom field definitions.
	 *
	 * @return list<array<string, mixed>>
	 */
	public function definitions(): array {
		return Repeaters::definitions( Repeaters::decode( get_option( self::DEFINITIONS_OPTION, '' ) ) )['items'];
	}

	/**
	 * Saves the definitions after validation.
	 *
	 * @param list<array<string, mixed>> $definitions Raw definitions.
	 * @return list<array{code: string, field: string}> Errors (nothing is saved when there are any).
	 */
	public function saveDefinitions( array $definitions ): array {
		$result = Repeaters::definitions( $definitions );
		if ( array() === $result['errors'] ) {
			update_option( self::DEFINITIONS_OPTION, Repeaters::encode( $result['items'] ), false );
		}
		return $result['errors'];
	}

	/**
	 * Builds a record from a post and its meta.
	 *
	 * @param \WP_Post $post Company post.
	 */
	private function record( \WP_Post $post ): CompanyRecord {
		$id   = (int) $post->ID;
		$text = static fn( string $key ): string => (string) get_post_meta( $id, Keys::META[ $key ], true );
		$list = static fn( string $key ): array => Repeaters::decode( get_post_meta( $id, Keys::META[ $key ], true ) );
		$from = $text( 'from' );
		$to   = $text( 'to' );
		$tags = json_decode( $text( 'technologies' ), true );
		$cust = json_decode( $text( 'custom' ), true );
		$pub  = json_decode( $text( 'public_fields' ), true );
		return new CompanyRecord(
			$id,
			(string) $post->post_title,
			$text( 'role' ),
			$from,
			$to,
			(bool) get_post_meta( $id, '_joo_company_pinned', true ),
			$text( 'url' ),
			(int) $text( 'logo_id' ),
			'' !== $text( 'summary' ) ? $text( 'summary' ) : (string) $post->post_excerpt,
			$text( 'description' ),
			$text( 'city' ),
			$text( 'country' ),
			$text( 'employment' ),
			$text( 'industry' ),
			(bool) get_post_meta( $id, Keys::META['remote'], true ),
			$list( 'roles' ),
			$list( 'achievements' ),
			is_array( $tags ) ? Repeaters::technologies( $tags ) : array(),
			is_array( $cust ) ? $cust : array(),
			$text( 'compensation' ),
			$text( 'equity' ),
			is_array( $pub ) ? array_values( array_filter( $pub, 'is_string' ) ) : array(),
			(int) $post->menu_order
		);
	}
}
