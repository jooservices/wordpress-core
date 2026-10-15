<?php
/**
 * Generic block editor script.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Blocks;

use JOOservices\ThemeCore\Support\Paths;

/**
 * One script for all dynamic blocks: ServerSideRender preview plus inspector controls generated from the
 * `x-joo` key of block.json (see resources/editor/blocks.js).
 *
 * @api
 */
final class EditorBundle {

	/**
	 * Registrar.
	 *
	 * @var BlockRegistrar
	 */
	private BlockRegistrar $registrar;

	/**
	 * Creates the bundle.
	 *
	 * @param BlockRegistrar $registrar Registrar.
	 */
	public function __construct( BlockRegistrar $registrar ) {
		$this->registrar = $registrar;
	}

	/**
	 * Hooks WordPress.
	 */
	public function hook(): void {
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue' ) );
	}

	/**
	 * Block descriptions handed to the script: name, attributes and generated controls.
	 *
	 * @return list<array{name: string, attributes: array<string, mixed>, controls: list<array<string, mixed>>}>
	 */
	public function descriptions(): array {
		$out = array();
		foreach ( $this->registrar->names() as $name ) {
			$type = \WP_Block_Type_Registry::get_instance()->get_registered( $name );
			if ( null === $type ) {
				continue;
			}
			$dir   = $this->folderOf( $name );
			$json  = '' !== $dir ? json_decode( (string) file_get_contents( $dir . '/block.json' ), true ) : array(); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$out[] = array(
				'name'       => $name,
				'attributes' => $type->attributes ?? array(),
				'controls'   => is_array( $json ) && isset( $json['x-joo']['controls'] ) && is_array( $json['x-joo']['controls'] ) ? array_values( $json['x-joo']['controls'] ) : array(),
			);
		}
		return $out;
	}

	/**
	 * Folder of a registered block.
	 *
	 * @param string $name Block name.
	 */
	private function folderOf( string $name ): string {
		foreach ( $this->registrar->folders() as $dir ) {
			if ( is_readable( $dir . '/block.json' ) ) {
				$meta = json_decode( (string) file_get_contents( $dir . '/block.json' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				if ( is_array( $meta ) && ( $meta['name'] ?? '' ) === $name ) {
					return $dir;
				}
			}
		}
		return '';
	}

	/**
	 * Enqueues the script in the block editor.
	 */
	public function enqueue(): void {
		$descriptions = $this->descriptions();
		if ( array() === $descriptions ) {
			return;
		}
		wp_enqueue_script(
			'joo-blocks-editor',
			Paths::url( 'resources/editor/blocks.js' ),
			array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-server-side-render', 'wp-i18n' ),
			Paths::version( 'resources/editor/blocks.js' ),
			true
		);
		wp_add_inline_script( 'joo-blocks-editor', 'window.jooBlocks = ' . wp_json_encode( $descriptions, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES ) . ';', 'before' );
	}
}
