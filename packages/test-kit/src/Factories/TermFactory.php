<?php
/**
 * Term factory.
 *
 * @package JOOservices\TestKit
 */

declare(strict_types=1);

namespace JOOservices\TestKit\Factories;

/**
 * Builds category hierarchies. Example: `TermFactory::tree( array( 'Research' => array( 'Homelab', 'Study cases' ), 'AI' ) )`.
 */
final class TermFactory {

	/**
	 * Creates a category tree.
	 *
	 * @param array<string|int, mixed> $tree Names; a name maps to an array of children.
	 * @param int                      $parent Parent id.
	 * @return array<string, int> Term ids by name.
	 */
	public static function tree( array $tree, int $parent = 0 ): array {
		$ids = array();
		foreach ( $tree as $key => $value ) {
			$name         = is_array( $value ) ? (string) $key : (string) $value;
			$id           = (int) ( new \WP_UnitTest_Factory() )->category->create(
				array(
					'name'   => $name,
					'parent' => $parent,
				)
			);
			$ids[ $name ] = $id;
			if ( is_array( $value ) ) {
				$ids += self::tree( $value, $id );
			}
		}
		return $ids;
	}

	/**
	 * Creates a tag with the optional topic meta.
	 *
	 * @param string               $name Name.
	 * @param array<string, mixed> $meta Term meta (joo_intro, joo_image_id, joo_short_description).
	 */
	public static function tag( string $name, array $meta = array() ): int {
		$id = (int) ( new \WP_UnitTest_Factory() )->tag->create( array( 'name' => $name ) );
		foreach ( $meta as $key => $value ) {
			update_term_meta( $id, $key, $value );
		}
		return $id;
	}
}
