<?php
/**
 * BreadcrumbList node.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Seo\Schema\Pieces;

use JOOservices\ThemeCore\Config\Settings;
use JOOservices\ThemeCore\Seo\Breadcrumbs\TrailBuilder;
use JOOservices\ThemeCore\Seo\Context;

/**
 * Same trail as the visible breadcrumbs block.
 *
 * @internal
 */
final class BreadcrumbPiece extends AbstractPiece {

	/**
	 * Page URL helper.
	 *
	 * @var PageUrl
	 */
	private PageUrl $url;

	/**
	 * Trail builder.
	 *
	 * @var TrailBuilder
	 */
	private TrailBuilder $trails;

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Creates the piece.
	 *
	 * @param PageUrl      $url      Page URL helper.
	 * @param TrailBuilder $trails   Trail builder.
	 * @param Settings     $settings Settings.
	 */
	public function __construct( PageUrl $url, TrailBuilder $trails, Settings $settings ) {
		$this->url      = $url;
		$this->trails   = $trails;
		$this->settings = $settings;
	}

	/**
	 * Name.
	 */
	protected function name(): string {
		return 'breadcrumb';
	}

	/**
	 * Id.
	 *
	 * @param Context $context Context.
	 */
	public function id( Context $context ): string {
		return ( $this->url->of( $context ) ?? '' ) . '#breadcrumb';
	}

	/**
	 * Needed when core owns breadcrumb schema and the trail has at least two items.
	 *
	 * @param Context $context Context.
	 */
	public function isNeeded( Context $context ): bool {
		return 'core' === (string) $this->settings->get( 'seo.breadcrumbs_owner', 'core' )
			&& null !== $this->url->of( $context )
			&& count( $this->trails->build( $context ) ) >= 2;
	}

	/**
	 * Node.
	 *
	 * @param Context $context Context.
	 * @return array<string, mixed>
	 */
	protected function build( Context $context ): array {
		return array(
			'@type'           => 'BreadcrumbList',
			'itemListElement' => $this->trails->toSchemaList( $this->trails->build( $context ) ),
		);
	}

	/**
	 * Kept even while an SEO plugin owns the rest.
	 */
	public function alwaysOn(): bool {
		return true;
	}

	/**
	 * Priority.
	 */
	public function priority(): int {
		return 6;
	}
}
