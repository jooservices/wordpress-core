<?php
/**
 * Self-hosted fonts.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Performance;

use JOOservices\ThemeCore\Assets\Preloader;
use JOOservices\ThemeCore\Config\Manifest;

/**
 * Generates @font-face rules (font-display swap plus a metric-adjusted fallback face) from the manifest and
 * preloads at most two font files.
 *
 * @api
 */
final class Fonts {

	private const MAX_PRELOADS = 2;

	/**
	 * Manifest.
	 *
	 * @var Manifest
	 */
	private Manifest $manifest;

	/**
	 * Preloader.
	 *
	 * @var Preloader|null
	 */
	private ?Preloader $preloader;

	/**
	 * Creates the service.
	 *
	 * @param Manifest       $manifest  Manifest.
	 * @param Preloader|null $preloader Preloader.
	 */
	public function __construct( Manifest $manifest, ?Preloader $preloader = null ) {
		$this->manifest  = $manifest;
		$this->preloader = $preloader;
	}

	/**
	 * Hooks WordPress.
	 */
	public function hook(): void {
		add_action( 'wp_head', array( $this, 'print' ), 2 );
		add_action( 'wp', array( $this, 'queuePreloads' ) );
	}

	/**
	 * Font definitions from the manifest.
	 *
	 * @return list<array<string, mixed>>
	 */
	public function definitions(): array {
		$fonts = $this->manifest->get( 'perf.fonts', array() );
		return is_array( $fonts ) ? array_values( array_filter( $fonts, 'is_array' ) ) : array();
	}

	/**
	 * CSS for all fonts.
	 */
	public function css(): string {
		$css = '';
		foreach ( $this->definitions() as $font ) {
			$family  = $this->cssString( (string) $font['family'] );
			$display = (string) ( $font['display'] ?? 'swap' );
			$weight  = (string) ( $font['weight'] ?? '400' );
			$style   = (string) ( $font['style'] ?? 'normal' );
			$sources = array();
			foreach ( (array) $font['files'] as $file ) {
				$sources[] = 'url(' . $this->cssString( $this->url( (string) $file ) ) . ") format('" . $this->format( (string) $file ) . "')";
			}
			$css .= sprintf( '@font-face{font-family:%1$s;src:%2$s;font-weight:%3$s;font-style:%4$s;font-display:%5$s', $family, implode( ',', $sources ), $this->token( $weight ), $this->token( $style ), $this->token( $display ) );
			if ( ! empty( $font['unicodeRange'] ) ) {
				$css .= ';unicode-range:' . $this->token( (string) $font['unicodeRange'] );
			}
			$css .= '}';
			if ( isset( $font['fallback']['family'] ) ) {
				$fallback = (array) $font['fallback'];
				$css     .= sprintf( '@font-face{font-family:%1$s;src:local(%2$s);font-weight:%3$s;font-style:%4$s', $this->cssString( $font['family'] . ' Fallback' ), $this->cssString( (string) $fallback['family'] ), $this->token( $weight ), $this->token( $style ) );
				foreach ( array(
					'sizeAdjust'      => 'size-adjust',
					'ascentOverride'  => 'ascent-override',
					'descentOverride' => 'descent-override',
					'lineGapOverride' => 'line-gap-override',
				) as $key => $property ) {
					if ( isset( $fallback[ $key ] ) ) {
						$css .= ';' . $property . ':' . $this->token( (string) $fallback[ $key ] );
					}
				}
				$css .= '}';
			}
		}
		return $css;
	}

	/**
	 * Prints the font CSS.
	 */
	public function print(): void {
		$css = $this->css();
		if ( '' !== $css ) {
			echo '<style id="joo-fonts">' . $css . '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- generated from escaped CSS tokens.
		}
	}

	/**
	 * Adds preloads for at most two fonts flagged preload.
	 */
	public function queuePreloads(): void {
		if ( null === $this->preloader ) {
			return;
		}
		$count = 0;
		foreach ( $this->definitions() as $font ) {
			if ( empty( $font['preload'] ) ) {
				continue;
			}
			foreach ( (array) $font['files'] as $file ) {
				if ( $count >= self::MAX_PRELOADS ) {
					return;
				}
				$this->preloader->add(
					$this->url( (string) $file ),
					'font',
					array(
						'type'        => 'font/' . $this->format( (string) $file ),
						'crossorigin' => '',
					)
				);
				++$count;
			}
		}
	}

	/**
	 * URL of a font file relative to the theme.
	 *
	 * @param string $file Path relative to the theme root.
	 */
	private function url( string $file ): string {
		return 0 === strpos( $file, 'http' ) ? $file : get_theme_file_uri( $file );
	}

	/**
	 * Font format from the extension.
	 *
	 * @param string $file File name.
	 */
	private function format( string $file ): string {
		$extension = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
		return 'ttf' === $extension ? 'truetype' : ( in_array( $extension, array( 'woff2', 'woff', 'otf' ), true ) ? ( 'otf' === $extension ? 'opentype' : $extension ) : 'woff2' );
	}

	/**
	 * Quoted, escaped CSS string.
	 *
	 * @param string $value Value.
	 */
	private function cssString( string $value ): string {
		return '"' . str_replace( array( '\\', '"', "\n", '<' ), array( '\\\\', '\\"', ' ', '\\3C ' ), $value ) . '"';
	}

	/**
	 * Restricts a value to safe CSS token characters.
	 *
	 * @param string $value Value.
	 */
	private function token( string $value ): string {
		return (string) preg_replace( '/[^A-Za-z0-9 .,%+\-]/', '', $value );
	}
}
