<?php
/**
 * WebSite and Organization nodes.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Seo\Schema\Pieces;

use JOOservices\ThemeCore\Config\Manifest;
use JOOservices\ThemeCore\Seo\Context;
use JOOservices\ThemeCore\Seo\Text;

/**
 * Shared ids and values for site-wide nodes.
 *
 * @internal
 */
final class SiteNodes {

	/**
	 * WebSite id.
	 */
	public static function websiteId(): string {
		return home_url( '/#website' );
	}

	/**
	 * Organization id.
	 */
	public static function organizationId(): string {
		return home_url( '/#organization' );
	}

	/**
	 * Person id.
	 */
	public static function personId(): string {
		return home_url( '/#person' );
	}

	/**
	 * The site owner's user id (manifest seo.person.userId, else the first administrator).
	 *
	 * @param Manifest $manifest Manifest.
	 */
	public static function ownerId( Manifest $manifest ): int {
		$configured = (int) $manifest->get( 'seo.person.userId', 0 );
		if ( $configured > 0 && false !== get_userdata( $configured ) ) {
			return $configured;
		}
		$admins = get_users(
			array(
				'role'    => 'administrator',
				'number'  => 1,
				'orderby' => 'ID',
				'order'   => 'ASC',
				'fields'  => 'ID',
			)
		);
		return array() === $admins ? 0 : (int) $admins[0];
	}
}
