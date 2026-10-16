<?php
/**
 * Applies the buyer-facing options owned by this theme.
 *
 * @package JOOservices
 */

declare(strict_types=1);

namespace JOOservices\Jooservices;

use JOOservices\ThemeCore\Config\Settings;

final class BuyerOptions {

	private Settings $settings;

	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	public static function hook(): void {
		$container = \JOOservices\ThemeCore\Kernel::container();
		if ( null === $container ) {
			return;
		}
		$options = new self( $container->get( Settings::class ) );
		add_filter( 'pre_option_date_format', array( $options, 'dateFormat' ), 10, 3 );
		add_filter( 'render_block', array( $options, 'filterBlock' ), 10, 3 );
		add_filter( 'body_class', array( $options, 'bodyClasses' ) );
		add_action( 'wp_body_open', array( $options, 'topAnchor' ), 0 );
		add_action( 'wp_footer', array( $options, 'backToTop' ), 20 );
	}

	/**
	 * Uses the theme's date choice for WordPress blocks and the featured slider.
	 *
	 * @param mixed  $pre     Pre-filtered value.
	 * @param string $option  Option name.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public function dateFormat( $pre, string $option = 'date_format', $default = false ) {
		unset( $option, $default );
		$format = (string) $this->settings->get( 'appearance.date_format', 'site' );
		return 'site' === $format ? $pre : $format;
	}

	/**
	 * Hides optional blocks according to the settings page values.
	 *
	 * @param string $content Rendered markup.
	 * @param array<string, mixed> $block    Parsed block.
	 * @param mixed                $instance Block instance.
	 */
	public function filterBlock( string $content, array $block, $instance = null ): string {
		unset( $instance );
		$name  = (string) ( $block['blockName'] ?? '' );
		$attrs = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array();
		if ( 'core/template-part' === $name && 'rail' === ( $attrs['slug'] ?? '' ) && ! $this->enabled( 'features.rail' ) ) {
			return '';
		}
		if ( 'joo/featured-slider' === $name && ! $this->enabled( 'features.slider' ) ) {
			return '';
		}
		$key = $this->railOption( $name, (string) ( $attrs['className'] ?? '' ) );
		return null !== $key && ! $this->enabled( $key ) ? '' : $content;
	}

	/**
	 * Adds the selected density as a body class.
	 *
	 * @param array<int, string> $classes Body classes.
	 * @return array<int, string>
	 */
	public function bodyClasses( array $classes ): array {
		if ( 'compact' === $this->settings->get( 'appearance.card_density', 'comfortable' ) ) {
			$classes[] = 'joo-card-density--compact';
		}
		return $classes;
	}

	/** Prints the optional back-to-top link. */
	public function backToTop(): void {
		if ( is_admin() || ! $this->enabled( 'features.back_to_top' ) ) {
			return;
		}
		echo '<a class="joo-back-to-top" href="#top">' . esc_html__( 'Back to top', 'jooservices' ) . '</a>';
	}

	/** Prints the return target on every template, including canvas pages without a header. */
	public function topAnchor(): void {
		if ( is_admin() ) {
			return;
		}
		echo '<span id="top"></span>';
	}

	private function enabled( string $key ): bool {
		return (bool) $this->settings->get( $key, true );
	}

	private function railOption( string $name, string $classes ): ?string {
		$blocks = array(
			'joo/profile-card'  => array( 'joo-rail__profile', 'content.rail_profile' ),
			'joo/companies'     => array( 'joo-rail__companies', 'content.rail_companies' ),
			'joo/category-tree' => array( 'joo-rail__categories', 'content.rail_categories' ),
			'joo/top-topics'    => array( 'joo-rail__topics', 'content.rail_topics' ),
			'joo/tag-list'      => array( 'joo-rail__tags', 'content.rail_tags' ),
		);
		if ( ! isset( $blocks[ $name ] ) || false === strpos( $classes, $blocks[ $name ][0] ) ) {
			return null;
		}
		return $blocks[ $name ][1];
	}
}
