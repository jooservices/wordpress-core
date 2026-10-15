<?php
/**
 * Robots directives.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Seo;

use JOOservices\ThemeCore\Config\Settings;
use JOOservices\ThemeCore\Support\Hooks;

/**
 * Applies the robots matrix through the WordPress `wp_robots` filter and the X-Robots-Tag header.
 *
 * @api
 */
final class RobotsBuilder {

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Creates the builder.
	 *
	 * @param Settings $settings Settings.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Directives for a context.
	 *
	 * @param Context $context Context.
	 * @return array<string, bool|string>
	 */
	public function directives( Context $context ): array {
		$type = $context->type();
		if ( $context->isRestricted() ) {
			$out = array(
				'noindex'  => true,
				'nofollow' => true,
			);
		} elseif ( ! $context->isIndexable() || ( $context->paged() > 1 && ! $context->isSingular() && (bool) $this->settings->get( 'seo.paged_noindex', false ) ) ) {
			$out = array(
				'noindex' => true,
				'follow'  => true,
			);
		} else {
			$out = array(
				'index'             => true,
				'follow'            => true,
				'max-image-preview' => 'large',
				'max-snippet'       => '-1',
				'max-video-preview' => '-1',
			);
		}
		unset( $type );
		$filtered = Hooks::filter( 'joo/seo/robots', $out, $context );
		return is_array( $filtered ) ? $filtered : $out;
	}

	/**
	 * `wp_robots` filter callback: merges our directives over the core ones.
	 *
	 * @param array<string, bool|string> $robots  Directives from core.
	 * @param Context|null               $context Context (resolved from the query when null).
	 * @return array<string, bool|string>
	 */
	public function filter( array $robots, ?Context $context = null ): array {
		$ours = $this->directives( $context ?? Context::fromGlobals() );
		if ( ! empty( $ours['noindex'] ) ) {
			unset( $robots['index'], $robots['max-image-preview'], $robots['max-snippet'], $robots['max-video-preview'] );
		}
		return array_merge( $robots, $ours );
	}

	/**
	 * X-Robots-Tag value for non-indexable contexts (null when the page is indexable).
	 *
	 * @param Context $context Context.
	 */
	public function header( Context $context ): ?string {
		$directives = $this->directives( $context );
		if ( empty( $directives['noindex'] ) ) {
			return null;
		}
		return ! empty( $directives['nofollow'] ) ? 'noindex, nofollow' : 'noindex, follow';
	}
}
