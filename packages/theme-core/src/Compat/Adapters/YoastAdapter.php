<?php
/**
 * Yoast SEO adapter.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Compat\Adapters;

/**
 * Primary term through WPSEO_Primary_Term (when present), noindex through post meta.
 *
 * @internal
 */
final class YoastAdapter extends AbstractSeoAdapter {

	/**
	 * Key.
	 */
	public function key(): string {
		return 'yoast';
	}

	/**
	 * Notice.
	 */
	public function notice(): string {
		return __( 'Core SEO output yields to Yoast SEO. The primary category and noindex flags are read from it.', 'joo-core' );
	}

	/**
	 * Primary category.
	 *
	 * @param int $postId Post id.
	 */
	protected function readPrimaryCategory( int $postId ): ?int {
		if ( class_exists( 'WPSEO_Primary_Term', false ) ) {
			$term = new \WPSEO_Primary_Term( 'category', $postId );
			$id   = $term->get_primary_term();
			if ( is_numeric( $id ) ) {
				return (int) $id;
			}
		}
		$meta = get_post_meta( $postId, '_yoast_wpseo_primary_category', true );
		return is_numeric( $meta ) ? (int) $meta : null;
	}

	/**
	 * Noindex flag (1 = noindex, 2 = index, 0 = default).
	 *
	 * @param int $postId Post id.
	 */
	protected function readNoindex( int $postId ): ?bool {
		$value = (string) get_post_meta( $postId, '_yoast_wpseo_meta-robots-noindex', true );
		return '' === $value || '0' === $value ? null : '1' === $value;
	}

	/**
	 * Meta clause.
	 *
	 * @return array<string, mixed>
	 */
	public function noindexMetaClause(): array {
		return array(
			'key'   => '_yoast_wpseo_meta-robots-noindex',
			'value' => '1',
		);
	}
}
