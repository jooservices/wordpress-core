<?php
/**
 * Resource preload links.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Assets;

use JOOservices\ThemeCore\Support\Hooks;
use JOOservices\ThemeCore\Support\Logger;

/**
 * Prints at most four <link rel="preload"> elements (fonts, the LCP image, the main stylesheet).
 *
 * @internal
 */
final class Preloader {

	private const LIMIT = 4;

	/**
	 * Preloads: [href, as, attributes].
	 *
	 * @var list<array{href: string, as: string, attrs: array<string, string>}>
	 */
	private array $items = array();

	/**
	 * Logger.
	 *
	 * @var Logger
	 */
	private Logger $logger;

	/**
	 * Creates the preloader.
	 *
	 * @param Logger $logger Logger.
	 */
	public function __construct( Logger $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Hooks the printer.
	 */
	public function hook(): void {
		add_action( 'wp_head', array( $this, 'print' ), 3 );
	}

	/**
	 * Adds a preload (same href is added once).
	 *
	 * @param string               $href  URL.
	 * @param string               $as    style|font|image|script|fetch.
	 * @param array<string,string> $attrs Extra attributes (type, crossorigin, fetchpriority, imagesrcset, imagesizes).
	 */
	public function add( string $href, string $as, array $attrs = array() ): void {
		foreach ( $this->items as $item ) {
			if ( $item['href'] === $href ) {
				return;
			}
		}
		$this->items[] = array(
			'href'  => $href,
			'as'    => $as,
			'attrs' => $attrs,
		);
	}

	/**
	 * Current list (after the filter, limited).
	 *
	 * @return list<array{href: string, as: string, attrs: array<string, string>}>
	 */
	public function items(): array {
		$items = Hooks::filter( 'joo/perf/preload', $this->items );
		$items = is_array( $items ) ? array_values( $items ) : array();
		if ( count( $items ) > self::LIMIT ) {
			$this->logger->warning( sprintf( 'Preload limit of %d reached; %d link(s) dropped.', self::LIMIT, count( $items ) - self::LIMIT ) );
			$items = array_slice( $items, 0, self::LIMIT );
		}
		return $items;
	}

	/**
	 * Prints the links.
	 */
	public function print(): void {
		foreach ( $this->items() as $item ) {
			$attributes = ' rel="preload" as="' . esc_attr( $item['as'] ) . '" href="' . esc_url( $item['href'] ) . '"';
			foreach ( $item['attrs'] as $name => $value ) {
				if ( 1 === preg_match( '/^[a-z-]+$/', (string) $name ) ) {
					$attributes .= ' ' . $name . '="' . esc_attr( $value ) . '"';
				}
			}
			echo '<link' . $attributes . '>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every part is escaped above; attribute names are validated.
		}
	}
}
