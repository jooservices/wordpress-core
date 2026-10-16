<?php
/**
 * Page configuration for the frontend runtime.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Assets;

use JOOservices\ThemeCore\Config\Settings;
use JOOservices\ThemeCore\Support\Hooks;

/**
 * Prints <script type="application/json" id="joo-config"> (data only, safe to embed under a strict CSP).
 *
 * @internal
 */
final class RuntimeConfig {

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Registry (for lazily importable modules).
	 *
	 * @var AssetRegistry
	 */
	private AssetRegistry $registry;

	/**
	 * Creates the service.
	 *
	 * @param Settings      $settings Settings.
	 * @param AssetRegistry $registry Asset registry.
	 */
	public function __construct( Settings $settings, AssetRegistry $registry ) {
		$this->settings = $settings;
		$this->registry = $registry;
	}

	/**
	 * Hooks the printer.
	 */
	public function hook(): void {
		add_action( 'wp_footer', array( $this, 'print' ), 1 );
	}

	/**
	 * Builds the configuration array.
	 *
	 * @return array<string, mixed>
	 */
	public function data(): array {
		$features = array(
			'terminal'         => (bool) $this->settings->get( 'terminal.enabled', true ),
			'slider'           => (bool) $this->settings->get( 'features.slider', true ),
			'theme_toggle'     => (bool) $this->settings->get( 'features.theme_toggle', true ),
			'reading_progress' => (bool) $this->settings->get( 'features.reading_progress', true ),
			'lazy'             => (bool) $this->settings->get( 'lazy.enabled', true ),
		);
		$data     = array(
			'features' => $features,
			'i18n'     => array(),
			'assets'   => $this->registry->lazyModules(),
			'rest'     => esc_url_raw( rest_url( 'joo/v1/' ) ),
			'lang'     => substr( determine_locale(), 0, 2 ),
		);
		$filtered = Hooks::filter( 'joo/assets/runtime_config', $data );
		return is_array( $filtered ) ? $filtered : $data;
	}

	/**
	 * Prints the JSON element.
	 */
	public function print(): void {
		$json = wp_json_encode( $this->data(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( false === $json ) {
			return;
		}
		echo '<script type="application/json" id="joo-config">' . $json . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON encoded with HEX_TAG/HEX_AMP/HEX_APOS.
	}
}
