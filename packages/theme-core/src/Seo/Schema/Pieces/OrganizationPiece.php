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
 * Organization.
 *
 * @internal
 */
final class OrganizationPiece extends AbstractPiece {

	/**
	 * Manifest.
	 *
	 * @var Manifest
	 */
	private Manifest $manifest;

	/**
	 * Creates the piece.
	 *
	 * @param Manifest $manifest Manifest.
	 */
	public function __construct( Manifest $manifest ) {
		$this->manifest = $manifest;
	}

	/**
	 * Name.
	 */
	protected function name(): string {
		return 'organization';
	}

	/**
	 * Id.
	 *
	 * @param Context $context Context.
	 */
	public function id( Context $context ): string {
		unset( $context );
		return SiteNodes::organizationId();
	}

	/**
	 * Everywhere.
	 *
	 * @param Context $context Context.
	 */
	public function isNeeded( Context $context ): bool {
		unset( $context );
		return true;
	}

	/**
	 * Node.
	 *
	 * @param Context $context Context.
	 * @return array<string, mixed>
	 */
	protected function build( Context $context ): array {
		unset( $context );
		$name = (string) $this->manifest->get( 'seo.organization.name', '' );
		$logo = (string) $this->manifest->get( 'seo.organization.logo', '' );
		$icon = '' !== $logo ? $logo : (string) get_site_icon_url( 512 );
		return array(
			'@type'  => 'Organization',
			'name'   => '' !== $name ? $name : Text::plain( get_bloginfo( 'name' ) ),
			'url'    => (string) $this->manifest->get( 'seo.organization.url', home_url( '/' ) ),
			'logo'   => '' === $icon ? null : array(
				'@type'      => 'ImageObject',
				'@id'        => home_url( '/#logo' ),
				'url'        => $icon,
				'contentUrl' => $icon,
				'width'      => 512,
				'height'     => 512,
			),
			'sameAs' => array_values( array_filter( (array) $this->manifest->get( 'seo.organization.sameAs', array() ), 'is_string' ) ),
		);
	}

	/**
	 * Priority.
	 */
	public function priority(): int {
		return 2;
	}
}
