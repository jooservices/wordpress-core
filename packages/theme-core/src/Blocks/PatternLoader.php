<?php
/**
 * Pattern registration helpers.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Blocks;

use JOOservices\ThemeCore\Config\Manifest;

/**
 * Registers the `jooservices` pattern category, patterns from extra theme folders and can remove the core patterns.
 *
 * @api
 */
final class PatternLoader {

	/**
	 * Manifest.
	 *
	 * @var Manifest
	 */
	private Manifest $manifest;

	/**
	 * Creates the loader.
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
		add_action( 'init', array( $this, 'register' ), 9 );
		if ( (bool) $this->manifest->get( 'blocks.removeCorePatterns', false ) ) {
			add_action(
				'after_setup_theme',
				static function (): void {
					remove_theme_support( 'core-block-patterns' );
				},
				20
			);
		}
	}

	/**
	 * Registers the category and the patterns of the manifest folders.
	 */
	public function register(): void {
		register_block_pattern_category( 'jooservices', array( 'label' => __( 'JOOservices', 'joo-core' ) ) );
		foreach ( (array) $this->manifest->get( 'blocks.patterns', array() ) as $relative ) {
			$relative = trim( (string) $relative, '/' );
			if ( '' !== $relative && false === strpos( $relative, '..' ) ) {
				$this->fromDirectory( trailingslashit( get_stylesheet_directory() ) . $relative );
			}
		}
	}

	/**
	 * Registers every PHP pattern in a folder (WordPress file header: Title, Slug, Categories, Keywords, Description, Viewport Width).
	 *
	 * @param string $dir Folder.
	 * @return list<string> Slugs registered.
	 */
	public function fromDirectory( string $dir ): array {
		$slugs = array();
		foreach ( glob( trailingslashit( $dir ) . '*.php' ) ?: array() as $file ) {
			$data = get_file_data(
				$file,
				array(
					'title'       => 'Title',
					'slug'        => 'Slug',
					'categories'  => 'Categories',
					'keywords'    => 'Keywords',
					'description' => 'Description',
					'viewport'    => 'Viewport Width',
				)
			);
			if ( '' === $data['title'] || 1 !== preg_match( '#^[a-z0-9-]+/[a-z0-9-]+$#', $data['slug'] ) ) {
				continue;
			}
			ob_start();
			include $file;
			$content = trim( (string) ob_get_clean() );
			$args    = array(
				'title'      => $data['title'],
				'content'    => $content,
				'categories' => array_values( array_filter( array_map( 'trim', explode( ',', '' !== $data['categories'] ? $data['categories'] : 'jooservices' ) ) ) ),
				'keywords'   => array_values( array_filter( array_map( 'trim', explode( ',', $data['keywords'] ) ) ) ),
			);
			if ( '' !== $data['description'] ) {
				$args['description'] = $data['description'];
			}
			if ( '' !== $data['viewport'] ) {
				$args['viewportWidth'] = (int) $data['viewport'];
			}
			if ( \WP_Block_Patterns_Registry::get_instance()->is_registered( $data['slug'] ) ) {
				continue;
			}
			if ( register_block_pattern( $data['slug'], $args ) ) {
				$slugs[] = $data['slug'];
			}
		}
		return $slugs;
	}
}
