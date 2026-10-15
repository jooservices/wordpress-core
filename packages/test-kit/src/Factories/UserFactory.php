<?php
/**
 * User factory.
 *
 * @package JOOservices\TestKit
 */

declare(strict_types=1);

namespace JOOservices\TestKit\Factories;

/**
 * Example: `UserFactory::owner( array( 'display_name' => 'Viet Vu' ), array( 'joo_title' => 'CTO' ) )`.
 */
final class UserFactory {

	/**
	 * Creates the site owner (an administrator) with profile meta.
	 *
	 * @param array<string, mixed> $args User arguments.
	 * @param array<string, mixed> $meta User meta (joo_title, joo_short_bio, joo_socials...).
	 * @return int User id.
	 */
	public static function owner( array $args = array(), array $meta = array() ): int {
		$id = (int) ( new \WP_UnitTest_Factory() )->user->create( array_merge( array( 'role' => 'administrator' ), $args ) );
		foreach ( $meta as $key => $value ) {
			update_user_meta( $id, $key, $value );
		}
		return $id;
	}

	/**
	 * Creates a user with a role.
	 *
	 * @param string               $role Role.
	 * @param array<string, mixed> $args User arguments.
	 */
	public static function withRole( string $role, array $args = array() ): int {
		return (int) ( new \WP_UnitTest_Factory() )->user->create( array_merge( array( 'role' => $role ), $args ) );
	}
}
