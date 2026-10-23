<?php
/**
 * Colour scheme without a flash.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Blocks;

use JOOservices\ThemeCore\Config\Manifest;
use JOOservices\ThemeCore\Support\Hooks;

/**
 * Sets `data-theme` on <html> in PHP (no-JS default) and with a tiny inline script before first paint
 * (stored choice, else the system preference when `design.followSystem`, else the default).
 *
 * @internal
 */
final class ThemeScheme {

	public const MAX_SCRIPT_BYTES = 300;

	/**
	 * Manifest.
	 *
	 * @var Manifest
	 */
	private Manifest $manifest;

	/**
	 * Creates the service.
	 *
	 * @param Manifest $manifest Manifest.
	 */
	public function __construct( Manifest $manifest ) {
		$this->manifest = $manifest;
	}

	/**
	 * Hooks WordPress.
	 */
	public function hook(): void {
		if ( is_admin() ) {
			return;
		}
		add_filter( 'language_attributes', array( $this, 'htmlAttribute' ), 30, 2 );
		add_action( 'wp_head', array( $this, 'print' ), 0 );
	}

	/**
	 * Allowed schemes.
	 *
	 * @return list<string>
	 */
	public function schemes(): array {
		$schemes = array_values(
			array_unique(
				array_filter(
					array_map( 'strval', (array) $this->manifest->get( 'design.colorSchemes', array( 'dark', 'light' ) ) ),
					static fn( string $scheme ): bool => 1 === preg_match( '/^[a-z][a-z0-9-]{0,31}$/', $scheme )
				)
			)
		);
		return array() === $schemes ? array( 'dark' ) : $schemes;
	}

	/**
	 * Default scheme.
	 */
	public function defaultScheme(): string {
		$default = (string) $this->manifest->get( 'design.defaultScheme', 'dark' );
		return in_array( $default, $this->schemes(), true ) ? $default : $this->schemes()[0];
	}

	/**
	 * Adds data-theme to the html tag.
	 *
	 * @param string $output  Attributes.
	 * @param string $doctype Doctype.
	 */
	public function htmlAttribute( $output, $doctype = 'html' ): string {
		$output = (string) $output;
		if ( 'html' !== $doctype || false !== strpos( $output, 'data-theme=' ) ) {
			return $output;
		}
		return $output . ' data-theme="' . esc_attr( $this->defaultScheme() ) . '"';
	}

	/**
	 * The inline script (empty when only one scheme exists).
	 */
	public function script(): string {
		if ( count( $this->schemes() ) < 2 ) {
			return '';
		}
		$default = $this->defaultScheme();
		$follow  = (bool) $this->manifest->get( 'design.followSystem', true );
		$allowed = wp_json_encode( $this->schemes() );
		$system  = $follow ? "matchMedia('(prefers-color-scheme:light)').matches?'light':'dark'" : "'" . $default . "'";
		return sprintf(
			'try{var a=%s,s=localStorage.getItem(\'joo-theme\'),m=%s;document.documentElement.dataset.theme=a.includes(s)?s:(a.includes(m)?m:\'%s\')}catch(e){}',
			$allowed,
			$system,
			$default
		);
	}

	/**
	 * Prints the script in the head.
	 */
	public function print(): void {
		$script = $this->script();
		if ( '' === $script ) {
			return;
		}
		$nonce = (string) Hooks::filter( 'joo/assets/inline_script_nonce', '' );
		echo '<script id="joo-theme-init"' . ( '' !== $nonce ? ' nonce="' . esc_attr( $nonce ) . '"' : '' ) . '>' . $script . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static string built from validated manifest values.
	}
}
