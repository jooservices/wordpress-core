<?php
/**
 * Resource hints and Speculation Rules.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Performance;

use JOOservices\ThemeCore\Config\Settings;

/**
 * Preconnect origins from settings and the manifest, removal of the emoji prefetch and speculation rules
 * according to perf.speculation (off | conservative | moderate).
 *
 * @internal
 */
final class ResourceHints {

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Creates the service.
	 *
	 * @param Settings $settings Settings.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Hooks WordPress.
	 */
	public function hook(): void {
		add_filter( 'wp_resource_hints', array( $this, 'hints' ), 10, 2 );
		if ( function_exists( 'wp_get_speculation_rules_configuration' ) ) {
			add_filter( 'wp_speculation_rules_configuration', array( $this, 'coreConfiguration' ) );
		} else {
			add_action( 'wp_footer', array( $this, 'printRules' ), 30 );
		}
	}

	/**
	 * Filter callback for wp_resource_hints.
	 *
	 * @param array<int, mixed> $urls          Hint URLs.
	 * @param string            $relation_type Relation type.
	 * @return array<int, mixed>
	 */
	public function hints( array $urls, string $relation_type ): array {
		if ( 'preconnect' === $relation_type ) {
			foreach ( (array) $this->settings->get( 'perf.preconnect', array() ) as $origin ) {
				if ( is_string( $origin ) && 0 === strpos( $origin, 'https://' ) ) {
					$urls[] = array(
						'href'        => $origin,
						'crossorigin' => 'anonymous',
					);
				}
			}
		}
		if ( 'dns-prefetch' === $relation_type && (bool) $this->settings->get( 'perf.disable_emoji', true ) ) {
			$urls = array_values( array_filter( $urls, static fn( $url ) => ! ( is_string( $url ) && false !== strpos( $url, 's.w.org' ) ) ) );
		}
		return $urls;
	}

	/**
	 * Configuration for the core Speculation Rules API (WordPress 6.8+).
	 *
	 * @param array<string, string>|null $config Core configuration.
	 * @return array<string, string>|null
	 */
	public function coreConfiguration( $config ) {
		unset( $config );
		switch ( (string) $this->settings->get( 'perf.speculation', 'conservative' ) ) {
			case 'off':
				return null;
			case 'moderate':
				return array(
					'mode'      => 'prerender',
					'eagerness' => 'moderate',
				);
			default:
				return array(
					'mode'      => 'prefetch',
					'eagerness' => 'conservative',
				);
		}
	}

	/**
	 * Whether rules should be printed for this request.
	 */
	public function enabled(): bool {
		return 'off' !== (string) $this->settings->get( 'perf.speculation', 'conservative' )
			&& ! is_user_logged_in()
			&& ! is_preview()
			&& ! is_customize_preview()
			&& ! isset( $_SERVER['HTTP_SAVE_DATA'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- only presence is tested.
	}

	/**
	 * Rules document.
	 *
	 * @return array<string, mixed>
	 */
	public function rules(): array {
		$moderate = 'moderate' === (string) $this->settings->get( 'perf.speculation', 'conservative' );
		$where    = array(
			'and' => array(
				array( 'href_matches' => '/*' ),
				array( 'not' => array( 'href_matches' => array( '/wp-admin/*', '/wp-login.php', '/wp-json/*', '/*\\?*(^|&)_wpnonce=*', '/cart*', '/checkout*', '/my-account*' ) ) ),
				array( 'not' => array( 'selector_matches' => '[rel~=nofollow]' ) ),
			),
		);
		$rule     = array(
			'where'     => $where,
			'eagerness' => $moderate ? 'moderate' : 'conservative',
		);
		return $moderate ? array( 'prerender' => array( $rule ) ) : array( 'prefetch' => array( $rule ) );
	}

	/**
	 * Prints the rules (WordPress before 6.8, which has no core API).
	 */
	public function printRules(): void {
		if ( ! $this->enabled() ) {
			return;
		}
		$json = wp_json_encode( $this->rules(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES );
		if ( false !== $json ) {
			echo '<script type="speculationrules">' . $json . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON encoded with HEX_TAG.
		}
	}
}
