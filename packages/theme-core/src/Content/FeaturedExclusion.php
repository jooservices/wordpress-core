<?php
/**
 * Keeps featured posts out of the latest list on the home page.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Content;

use JOOservices\ThemeCore\Config\Settings;
use JOOservices\ThemeCore\Support\Hooks;

/**
 * The featured slider already shows these posts, so the main query of the blog home leaves them out when the
 * `features.hide_featured_in_list` setting is on. They stay reachable through categories, tags and search.
 *
 * @internal
 */
final class FeaturedExclusion {

	/**
	 * How many featured posts the slider shows by default.
	 */
	public const DEFAULT_LIMIT = 5;

	/**
	 * Provider.
	 *
	 * @var ContentProviderInterface
	 */
	private ContentProviderInterface $provider;

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Creates the service.
	 *
	 * @param ContentProviderInterface $provider Content provider.
	 * @param Settings                 $settings Settings.
	 */
	public function __construct( ContentProviderInterface $provider, Settings $settings ) {
		$this->provider = $provider;
		$this->settings = $settings;
	}

	/**
	 * Hooks WordPress.
	 */
	public function hook(): void {
		add_action( 'pre_get_posts', array( $this, 'filter' ) );
	}

	/**
	 * Adds `post__not_in` to the main query of the blog home.
	 *
	 * @param \WP_Query $query Query.
	 */
	public function filter( $query ): void {
		if ( ! $query instanceof \WP_Query || is_admin() || ! $query->is_main_query() || ! $query->is_home() || $query->is_feed() ) {
			return;
		}
		if ( ! (bool) $this->settings->get( 'features.hide_featured_in_list', true ) ) {
			return;
		}
		$limit = (int) Hooks::filter( 'joo/content/featured_exclusion_limit', self::DEFAULT_LIMIT );
		if ( $limit < 1 ) {
			return;
		}
		$ids = array_map( static fn( $post ): int => $post->id, $this->provider->featured( min( 12, $limit ) ) );
		if ( array() === $ids ) {
			return;
		}
		$existing = array_map( 'intval', (array) $query->get( 'post__not_in' ) );
		$query->set( 'post__not_in', array_values( array_unique( array_merge( $existing, $ids ) ) ) );
	}
}
