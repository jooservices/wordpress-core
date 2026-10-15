<?php
/**
 * SEOPress adapter.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Compat\Adapters;

/**
 * Primary category from `_seopress_robots_primary_cat`, noindex when `_seopress_robots_index` is "yes".
 *
 * @internal
 */
final class SeoPressAdapter extends AbstractSeoAdapter {

	/**
	 * Key.
	 */
	public function key(): string {
		return 'seopress';
	}

	/**
	 * Notice.
	 */
	public function notice(): string {
		return __( 'Core SEO output yields to SEOPress. The primary category and noindex flags are read from it.', 'joo-core' );
	}

	/**
	 * Primary category.
	 *
	 * @param int $postId Post id.
	 */
	protected function readPrimaryCategory( int $postId ): ?int {
		$meta = get_post_meta( $postId, '_seopress_robots_primary_cat', true );
		return is_numeric( $meta ) ? (int) $meta : null;
	}

	/**
	 * Noindex flag.
	 *
	 * @param int $postId Post id.
	 */
	protected function readNoindex( int $postId ): ?bool {
		$value = (string) get_post_meta( $postId, '_seopress_robots_index', true );
		return '' === $value ? null : 'yes' === $value;
	}

	/**
	 * Meta clause.
	 *
	 * @return array<string, mixed>
	 */
	public function noindexMetaClause(): array {
		return array(
			'key'   => '_seopress_robots_index',
			'value' => 'yes',
		);
	}
}
