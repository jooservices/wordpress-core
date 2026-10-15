<?php
/**
 * Inline critical CSS per template type.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Assets;

use JOOservices\ThemeCore\Support\Hooks;
use JOOservices\ThemeCore\Support\Paths;

/**
 * Prints <style id="joo-critical"> with CSS generated at build time (tools/critical.mjs). The theme's
 * assets/critical/{context}.css overrides core's resources/critical/{context}.css.
 *
 * @internal
 */
final class CriticalCss {

	/**
	 * Cached CSS by context.
	 *
	 * @var array<string, string>
	 */
	private array $cache = array();

	/**
	 * Extra CSS appended to every context (for example generated @font-face rules).
	 *
	 * @var list<string>
	 */
	private array $extra = array();

	/**
	 * Hooks the printer.
	 */
	public function hook(): void {
		add_action( 'wp_head', array( $this, 'print' ), 2 );
	}

	/**
	 * Appends CSS to the critical block (used by the font loader).
	 *
	 * @param string $css CSS text without style tags.
	 */
	public function append( string $css ): void {
		$this->extra[] = $css;
	}

	/**
	 * Template context key.
	 */
	public function context(): string {
		if ( is_404() ) {
			return '404';
		}
		if ( is_search() ) {
			return 'search';
		}
		if ( is_singular() ) {
			return is_page() ? 'page' : 'single';
		}
		if ( is_archive() ) {
			return 'archive';
		}
		return 'home';
	}

	/**
	 * Critical CSS for the current request ('' when none exists).
	 */
	public function css(): string {
		if ( (bool) Hooks::filter( 'joo/perf/skip_critical_css', false ) ) {
			return '';
		}
		$context = $this->context();
		if ( ! isset( $this->cache[ $context ] ) ) {
			$css = '';
			foreach ( array( trailingslashit( get_stylesheet_directory() ) . 'assets/critical/' . $context . '.css', Paths::file( 'resources/critical/' . $context . '.css' ) ) as $file ) {
				if ( is_readable( $file ) ) {
					$css = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
					break;
				}
			}
			$this->cache[ $context ] = $css;
		}
		$css = $this->cache[ $context ];
		return '' === $css ? '' : implode( '', $this->extra ) . $css;
	}

	/**
	 * Whether critical CSS will be printed for this request.
	 */
	public function available(): bool {
		return '' !== $this->css();
	}

	/**
	 * Prints the style block.
	 */
	public function print(): void {
		$css = $this->css();
		if ( '' === $css || false !== stripos( $css, '</style' ) ) {
			return;
		}
		echo '<style id="joo-critical">' . $css . '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- build output, checked for a closing style tag above.
	}
}
