<?php
/**
 * Block without its own class.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Blocks;

use JOOservices\ThemeCore\Container\Container;

/**
 * A folder with block.json and render.php is enough: data comes from the `joo/blocks/data/{slug}` filter.
 *
 * @internal
 */
final class GenericBlock extends DynamicBlock {

	/**
	 * Block name.
	 */
	public static function name(): string {
		return 'joo/generic';
	}

	/**
	 * Data is empty unless a filter provides it.
	 *
	 * @param array<string, mixed> $attrs Attributes.
	 * @param \WP_Block            $block Block.
	 * @return array<string, mixed>
	 */
	protected function data( array $attrs, \WP_Block $block ): array {
		unset( $block );
		return $attrs;
	}

	/**
	 * Renders with an explicit block name (so one class serves many folders).
	 *
	 * @param string               $name    Block name.
	 * @param array<string, mixed> $attrs   Attributes.
	 * @param string               $content Content.
	 * @param \WP_Block            $block   Block.
	 */
	public function renderNamed( string $name, array $attrs, string $content, \WP_Block $block ): string {
		unset( $content );
		$slug = substr( $name, 4 );
		$data = \JOOservices\ThemeCore\Support\Hooks::filter( sprintf( 'joo/blocks/data/%s', $slug ), $attrs, $attrs );
		try {
			$html = self::renderTemplate( $slug, $this->dirOf( $name ), is_array( $data ) ? $data : array(), $attrs, $block );
		} catch ( \Throwable $error ) {
			return defined( 'WP_DEBUG' ) && WP_DEBUG ? '<!-- joo block ' . esc_html( $slug ) . ' failed -->' : '';
		}
		$html = \JOOservices\ThemeCore\Support\Hooks::filter( sprintf( 'joo/blocks/html/%s', $slug ), $html, $attrs );
		return is_string( $html ) ? $html : '';
	}

	/**
	 * Block folders by name (filled by the registrar).
	 *
	 * @var array<string, string>
	 */
	private static array $dirs = array();

	/**
	 * Remembers the folder of a block.
	 *
	 * @param string $name Block name.
	 * @param string $dir  Folder.
	 */
	public static function remember( string $name, string $dir ): void {
		self::$dirs[ $name ] = $dir;
	}

	/**
	 * Folder of a block.
	 *
	 * @param string $name Block name.
	 */
	private function dirOf( string $name ): string {
		return self::$dirs[ $name ] ?? '';
	}
}
