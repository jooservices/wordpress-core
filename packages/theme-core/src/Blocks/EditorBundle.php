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
			$dir      = $this->folderOf( $name );
			$json     = '' !== $dir ? json_decode( (string) file_get_contents( $dir . '/block.json' ), true ) : array(); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$controls = array();
			if ( is_array( $json ) && isset( $json['x-joo']['controls'] ) && is_array( $json['x-joo']['controls'] ) ) {
				$controls = array_values( $json['x-joo']['controls'] );
			}
			$out[] = array(
				'name'       => $name,
				'attributes' => $type->attributes ?? array(),
				'controls'   => $this->translateControls( $controls ),
			);
		}
		return $out;
	}

	/**
	 * Translates labels and choices from block.json before passing them to JavaScript.
	 *
	 * @param list<array<string, mixed>> $controls Controls.
	 * @return list<array<string, mixed>>
	 */
	private function translateControls( array $controls ): array {
		$known     = array(
			'About page'            => __( 'About page', 'joo-core' ),
			'Author archive'        => __( 'Author archive', 'joo-core' ),
			'Autoplay'              => __( 'Autoplay', 'joo-core' ),
			'Avatar size'           => __( 'Avatar size', 'joo-core' ),
			'Chevron'               => __( 'Chevron', 'joo-core' ),
			'Chips'                 => __( 'Chips', 'joo-core' ),
			'Cloud'                 => __( 'Cloud', 'joo-core' ),
			'Collapsed'             => __( 'Collapsed', 'joo-core' ),
			'Companies per page'    => __( 'Companies per page', 'joo-core' ),
			'Curated'               => __( 'Curated', 'joo-core' ),
			'Current company label' => __( 'Current company label', 'joo-core' ),
			'Dot'                   => __( 'Dot', 'joo-core' ),
			'Dropdown'              => __( 'Dropdown', 'joo-core' ),
			'Expanded levels'       => __( 'Expanded levels', 'joo-core' ),
			'Hide current language' => __( 'Hide current language', 'joo-core' ),
			'Hide empty categories' => __( 'Hide empty categories', 'joo-core' ),
			'Interval (ms)'         => __( 'Interval (ms)', 'joo-core' ),
			'Label'                 => __( 'Label', 'joo-core' ),
			'Label length'          => __( 'Label length', 'joo-core' ),
			'Link the category'     => __( 'Link the category', 'joo-core' ),
			'Link to'               => __( 'Link to', 'joo-core' ),
			'List'                  => __( 'List', 'joo-core' ),
			'List label'            => __( 'List label', 'joo-core' ),
			'Maximum depth'         => __( 'Maximum depth', 'joo-core' ),
			'Minimum headings'      => __( 'Minimum headings', 'joo-core' ),
			'Most posts'            => __( 'Most posts', 'joo-core' ),
			'Most used'             => __( 'Most used', 'joo-core' ),
			'Name'                  => __( 'Name', 'joo-core' ),
			'Order'                 => __( 'Order', 'joo-core' ),
			'Posts'                 => __( 'Posts', 'joo-core' ),
			'Separator'             => __( 'Separator', 'joo-core' ),
			'Show bio'              => __( 'Show bio', 'joo-core' ),
			'Show counts'           => __( 'Show counts', 'joo-core' ),
			'Show description'      => __( 'Show description', 'joo-core' ),
			'Show descriptions'     => __( 'Show descriptions', 'joo-core' ),
			'Show excerpt'          => __( 'Show excerpt', 'joo-core' ),
			'Show excerpts'         => __( 'Show excerpts', 'joo-core' ),
			'Show home'             => __( 'Show home', 'joo-core' ),
			'Show images'           => __( 'Show images', 'joo-core' ),
			'Show intro'            => __( 'Show intro', 'joo-core' ),
			'Show meta'             => __( 'Show meta', 'joo-core' ),
			'Show social links'     => __( 'Show social links', 'joo-core' ),
			'Site setting'          => __( 'Site setting', 'joo-core' ),
			'Slash'                 => __( 'Slash', 'joo-core' ),
			'Style'                 => __( 'Style', 'joo-core' ),
			'Tags'                  => __( 'Tags', 'joo-core' ),
			'Title'                 => __( 'Title', 'joo-core' ),
			'Topics'                => __( 'Topics', 'joo-core' ),
		);
		$translate = static fn( string $value ): string => $known[ $value ] ?? $value;
		foreach ( $controls as &$control ) {
			if ( isset( $control['label'] ) && is_string( $control['label'] ) ) {
				$control['label'] = $translate( $control['label'] );
			}
			if ( ! isset( $control['options'] ) || ! is_array( $control['options'] ) ) {
				continue;
			}
			foreach ( $control['options'] as &$option ) {
				if ( is_array( $option ) && isset( $option[1] ) && is_string( $option[1] ) ) {
					$option[1] = $translate( $option[1] );
				}
			}
			unset( $option );
		}
		unset( $control );
		return $controls;
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
