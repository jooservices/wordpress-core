<?php
/**
 * SEO plugin yield policy.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Seo;

use JOOservices\ThemeCore\Compat\Detector;
use JOOservices\ThemeCore\Config\Settings;
use JOOservices\ThemeCore\Support\Hooks;

/**
 * Decides whether core prints SEO tags or leaves them to an SEO plugin.
 * (The spec sketch named this namespace "Yield"; that is a reserved word, so the class lives in Seo directly.)
 *
 * @internal
 */
final class YieldPolicy {

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Detector.
	 *
	 * @var Detector
	 */
	private Detector $detector;

	/**
	 * Creates the policy.
	 *
	 * @param Settings $settings Settings.
	 * @param Detector $detector Plugin detector.
	 */
	public function __construct( Settings $settings, Detector $detector ) {
		$this->settings = $settings;
		$this->detector = $detector;
	}

	/**
	 * Configured mode: auto, never or always.
	 */
	public function mode(): string {
		$mode = (string) $this->settings->get( 'seo.yield', 'auto' );
		return in_array( $mode, array( 'auto', 'never', 'always' ), true ) ? $mode : 'auto';
	}

	/**
	 * The plugin core yields to (null when none is relevant).
	 *
	 * @return array{name: string, version: string}|null
	 */
	public function plugin(): ?array {
		return $this->detector->seoPlugin();
	}

	/**
	 * Whether core must stay silent.
	 */
	public function shouldYield(): bool {
		$plugin = $this->plugin();
		switch ( $this->mode() ) {
			case 'always':
				$yield = true;
				break;
			case 'never':
				$yield = false;
				break;
			default:
				$yield = null !== $plugin;
		}
		return (bool) Hooks::filter( 'joo/seo/yield', $yield, null === $plugin ? '' : $plugin['name'] );
	}

	/**
	 * Whether hreflang tags are left to the plugin as well (only while yielding).
	 */
	public function yieldsHreflang(): bool {
		return $this->shouldYield() && (bool) $this->settings->get( 'seo.yield_hreflang', true );
	}

	/**
	 * Human readable state for admin screens.
	 */
	public function describe(): string {
		$plugin = $this->plugin();
		if ( ! $this->shouldYield() ) {
			return null === $plugin ? 'Core prints the SEO tags.' : 'Core prints the SEO tags (' . $plugin['name'] . ' is active; yield is set to never).';
		}
		return null === $plugin ? 'SEO output is disabled (yield: always).' : 'Yielding to ' . $plugin['name'] . ' ' . $plugin['version'] . '.';
	}
}
