<?php
/**
 * Jetpack adapter.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Compat\Adapters;

use JOOservices\ThemeCore\Compat\Detector;
use JOOservices\ThemeCore\Config\Settings;

/**
 * Core Open Graph wins: Jetpack's tags are switched off while core prints SEO (setting seo.yield_jetpack).
 *
 * @internal
 */
final class JetpackAdapter extends AbstractAdapter {

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Whether core currently prints SEO tags.
	 *
	 * @var callable(): bool
	 */
	private $corePrints;

	/**
	 * Creates the adapter.
	 *
	 * @param Detector      $detector   Detector.
	 * @param Settings      $settings   Settings.
	 * @param callable      $corePrints Returns true while core prints the SEO tags.
	 */
	public function __construct( Detector $detector, Settings $settings, callable $corePrints ) {
		parent::__construct( $detector );
		$this->settings   = $settings;
		$this->corePrints = $corePrints;
	}

	/**
	 * Key.
	 */
	public function key(): string {
		return 'jetpack';
	}

	/**
	 * Notice.
	 */
	public function notice(): string {
		return __( 'Core Open Graph tags win; Jetpack Open Graph is switched off (setting "Replace Jetpack Open Graph"). Jetpack lazy images are detected.', 'joo-core' );
	}

	/**
	 * Hooks.
	 */
	public function boot(): void {
		add_filter( 'jetpack_enable_open_graph', array( $this, 'openGraph' ), 99 );
	}

	/**
	 * Filter: Jetpack Open Graph on/off.
	 *
	 * @param mixed $enabled Current value.
	 */
	public function openGraph( $enabled ): bool {
		if ( (bool) $this->settings->get( 'seo.yield_jetpack', true ) && (bool) ( $this->corePrints )() ) {
			return false;
		}
		return (bool) $enabled;
	}

	/**
	 * Whether the Jetpack lazy images module is active.
	 */
	public function providesLazyload(): bool {
		if ( class_exists( 'Jetpack', false ) && is_callable( array( 'Jetpack', 'is_module_active' ) ) ) {
			try {
				return (bool) \Jetpack::is_module_active( 'lazy-images' );
			} catch ( \Throwable $error ) {
				return false;
			}
		}
		return false;
	}
}
