<?php
/**
 * Rank Math adapter.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Compat\Adapters;

/**
 * Primary category from `rank_math_primary_category`, noindex from `rank_math_robots`.
 *
 * @internal
 */
final class RankMathAdapter extends AbstractSeoAdapter {

	/**
	 * Key.
	 */
	public function key(): string {
		return 'rankmath';
	}

	/**
	 * Notice.
	 */
	public function notice(): string {
		return __( 'Core SEO output yields to Rank Math. The primary category and noindex flags are read from it.', 'joo-core' );
	}

	/**
	 * Primary category.
	 *
	 * @param int $postId Post id.
	 */
	protected function readPrimaryCategory( int $postId ): ?int {
		$meta = get_post_meta( $postId, 'rank_math_primary_category', true );
		return is_numeric( $meta ) ? (int) $meta : null;
	}

	/**
	 * Noindex flag.
	 *
	 * @param int $postId Post id.
	 */
	protected function readNoindex( int $postId ): ?bool {
		$robots = get_post_meta( $postId, 'rank_math_robots', true );
		return is_array( $robots ) ? in_array( 'noindex', $robots, true ) : null;
	}

	/**
	 * Meta clause (the value is a serialized array).
	 *
	 * @return array<string, mixed>
	 */
	public function noindexMetaClause(): array {
		return array(
			'key'     => 'rank_math_robots',
			'value'   => '"noindex"',
			'compare' => 'LIKE',
		);
	}
}
