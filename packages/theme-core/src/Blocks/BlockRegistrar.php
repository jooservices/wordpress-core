<?php
/**
 * Block registration from folders.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Blocks;

use JOOservices\ThemeCore\Assets\AssetRegistry;
use JOOservices\ThemeCore\Config\Manifest;
use JOOservices\ThemeCore\Container\Container;
use JOOservices\ThemeCore\Support\Hooks;
use JOOservices\ThemeCore\Support\Logger;
use JOOservices\ThemeCore\Support\Paths;

/**
 * Scans `resources/blocks/<slug>/block.json` (and folders added with `joo/blocks/register`), registers every
 * enabled block as a dynamic block and wires its built view module and style (loaded only when it renders).
 *
 * @internal
 */
final class BlockRegistrar {

	/**
	 * Container.
	 *
	 * @var Container
	 */
	private Container $container;

	/**
	 * Instances by block name.
	 *
	 * @var array<string, DynamicBlock>
	 */
	private array $instances = array();

	/**
	 * Registered block names.
	 *
	 * @var list<string>
	 */
	private array $registered = array();

	/**
	 * Block folders by name.
	 *
	 * @var array<string, string>
	 */
	private array $dirs = array();

	/**
	 * Creates the registrar.
	 *
	 * @param Container $container Container.
	 */
	public function __construct( Container $container ) {
		$this->container = $container;
	}

	/**
	 * Hooks WordPress.
	 */
	public function hook(): void {
		add_action( 'init', array( $this, 'register' ), 9 );
		add_filter( 'block_categories_all', array( $this, 'category' ) );
	}

	/**
	 * Adds the JOOservices block category.
	 *
	 * @param array<int, array<string, string>> $categories Categories.
	 * @return array<int, array<string, string>>
	 */
	public function category( $categories ): array {
		$categories   = is_array( $categories ) ? $categories : array();
		$categories[] = array(
			'slug'  => 'joo',
			'title' => __( 'JOOservices', 'joo-core' ),
		);
		return $categories;
	}

	/**
	 * Folders that hold blocks.
	 *
	 * @return list<string>
	 */
	public function folders(): array {
		$dirs = array();
		foreach ( glob( Paths::file( 'resources/blocks' ) . '/*', GLOB_ONLYDIR ) ?: array() as $dir ) {
			$dirs[] = $dir;
		}
		$extra = Hooks::filter( 'joo/blocks/register', array() );
		foreach ( is_array( $extra ) ? $extra : array() as $dir ) {
			if ( is_string( $dir ) && is_dir( $dir ) ) {
				$dirs[] = untrailingslashit( $dir );
			}
		}
		return array_values( array_unique( $dirs ) );
	}

	/**
	 * Registers the blocks.
	 */
	public function register(): void {
		/** @var Manifest $manifest */
		$manifest = $this->container->get( Manifest::class );
		/** @var Logger $logger */
		$logger   = $this->container->get( Logger::class );
		$disabled = array_map( 'strval', (array) $manifest->get( 'blocks.disabled', array() ) );
		foreach ( $this->folders() as $dir ) {
			$json = $dir . '/block.json';
			if ( ! is_readable( $json ) ) {
				continue;
			}
			$meta = json_decode( (string) file_get_contents( $json ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$name = is_array( $meta ) ? (string) ( $meta['name'] ?? '' ) : '';
			if ( 1 !== preg_match( '#^joo/[a-z0-9-]+$#', $name ) || in_array( $name, $disabled, true ) ) {
				if ( '' === $name ) {
					$logger->warning( 'Invalid block.json in ' . $dir );
				}
				continue;
			}
			if ( \WP_Block_Type_Registry::get_instance()->is_registered( $name ) ) {
				$this->registered[]  = $name;
				$this->dirs[ $name ] = $dir;
				continue;
			}
			$slug = substr( $name, 4 );
			/** @var array<string, mixed> $args */
			$args = array_merge( array( 'render_callback' => fn( $attrs, $content, $block ) => $this->render( $name, $dir, (array) $attrs, (string) $content, $block ) ), $this->assets( $slug ) );
			$type = register_block_type_from_metadata( $dir, $args );
			if ( false === $type ) {
				$logger->warning( 'Block could not be registered: ' . $name );
				continue;
			}
			GenericBlock::remember( $name, $dir );
			$this->dirs[ $name ] = $dir;
			$this->registered[]  = $name;
		}
	}

	/**
	 * The class instance of a block (null for blocks without a class).
	 *
	 * @param string $name Block name.
	 */
	public function instance( string $name ): ?DynamicBlock {
		if ( ! isset( $this->instances[ $name ] ) && isset( $this->dirs[ $name ] ) ) {
			$class = 'JOOservices\\ThemeCore\\Blocks\\Blocks\\' . str_replace( ' ', '', ucwords( str_replace( '-', ' ', substr( $name, 4 ) ) ) );
			if ( class_exists( $class ) && is_subclass_of( $class, DynamicBlock::class ) ) {
				$instance = new $class( $this->container );
				$instance->setDir( $this->dirs[ $name ] );
				$this->instances[ $name ] = $instance;
			}
		}
		return $this->instances[ $name ] ?? null;
	}

	/**
	 * Names of the blocks registered by this registrar.
	 *
	 * @return list<string>
	 */
	public function names(): array {
		return $this->registered;
	}

	/**
	 * View module and style from the build manifest, registered under `joo-block-{slug}`.
	 *
	 * @param string $slug Block slug.
	 * @return array<string, list<string>>
	 */
	private function assets( string $slug ): array {
		/** @var AssetRegistry $assets */
		$assets   = $this->container->get( AssetRegistry::class );
		$manifest = $assets->manifest();
		$args     = array();
		if ( null === $manifest ) {
			return $args;
		}
		$handle = 'joo-block-' . $slug;
		if ( $manifest->has( 'blocks/' . $slug . '/view' ) ) {
			wp_register_script_module(
				$handle,
				$manifest->url( 'blocks/' . $slug . '/view' ),
				array(
					array(
						'id'     => 'joo-runtime',
						'import' => 'static',
					),
				),
				null
			);
			$args['view_script_module_ids'] = array( $handle );
		}
		if ( $manifest->has( 'blocks/' . $slug . '/style' ) ) {
			wp_register_style( $handle, $manifest->url( 'blocks/' . $slug . '/style' ), array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- content-hashed file name.
			$args['style_handles'] = array( $handle );
		}
		return $args;
	}

	/**
	 * Render callback: a block class when one exists, else the generic template renderer.
	 *
	 * @param string               $name    Block name.
	 * @param string               $dir     Block folder.
	 * @param array<string, mixed> $attrs   Attributes.
	 * @param string               $content Content.
	 * @param \WP_Block            $block   Block.
	 */
	private function render( string $name, string $dir, array $attrs, string $content, \WP_Block $block ): string {
		$instance = $this->instance( $name );
		if ( null !== $instance ) {
			return $instance->render( $attrs, $content, $block );
		}
		return ( new GenericBlock( $this->container ) )->renderNamed( $name, $attrs, $content, $block );
	}
}
