<?php
/**
 * Base class of server-rendered blocks.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Blocks;

use JOOservices\ThemeCore\Container\Container;
use JOOservices\ThemeCore\Performance\FragmentCache;
use JOOservices\ThemeCore\Support\Hooks;
use JOOservices\ThemeCore\Support\Logger;

/**
 * Subclasses provide the data; the template (theme override or core default) turns it into HTML.
 * Templates receive $data, $attrs, $block and $wrapper (get_block_wrapper_attributes()) and must escape their output.
 *
 * @api
 */
abstract class DynamicBlock {

	/**
	 * Container.
	 *
	 * @var Container
	 */
	protected Container $container;

	/**
	 * Folder of the block (render.php lives here).
	 *
	 * @var string
	 */
	private string $dir = '';

	/**
	 * Creates the block.
	 *
	 * @param Container $container Container.
	 */
	public function __construct( Container $container ) {
		$this->container = $container;
	}

	/**
	 * Block name (`joo/profile-card`).
	 */
	abstract public static function name(): string;

	/**
	 * Data handed to the template.
	 *
	 * @param array<string, mixed> $attrs Block attributes.
	 * @param \WP_Block            $block Block instance.
	 * @return array<string, mixed>
	 */
	abstract protected function data( array $attrs, \WP_Block $block ): array;

	/**
	 * Tells the block where its folder is.
	 *
	 * @param string $dir Folder.
	 */
	public function setDir( string $dir ): void {
		$this->dir = $dir;
	}

	/**
	 * Whether the output may be cached (never when it depends on the visitor).
	 */
	public function cacheable(): bool {
		return false;
	}

	/**
	 * Cache key for a set of attributes.
	 *
	 * @param array<string, mixed> $attrs Attributes.
	 */
	public function cacheKey( array $attrs ): string {
		ksort( $attrs );
		return md5( (string) wp_json_encode( $attrs ) );
	}

	/**
	 * Slug part of the name.
	 */
	public static function slug(): string {
		return substr( static::name(), 4 );
	}

	/**
	 * Render callback.
	 *
	 * @param array<string, mixed> $attrs   Attributes.
	 * @param string               $content Inner content.
	 * @param \WP_Block            $block   Block.
	 */
	public function render( array $attrs, string $content, \WP_Block $block ): string {
		unset( $content );
		if ( $this->cacheable() && ! is_user_logged_in() && ! is_preview() ) {
			/** @var FragmentCache $cache */
			$cache = $this->container->get( FragmentCache::class );
			return (string) $cache->remember( 'block:' . static::slug() . ':' . $this->cacheKey( $attrs ), 300, fn() => $this->build( $attrs, $block ), array( 'posts', 'terms', 'companies', 'profile' ), 'blocks' );
		}
		return $this->build( $attrs, $block );
	}

	/**
	 * Builds the HTML (data filter, template, output filter).
	 *
	 * @param array<string, mixed> $attrs Attributes.
	 * @param \WP_Block            $block Block.
	 */
	private function build( array $attrs, \WP_Block $block ): string {
		$slug = static::slug();
		/** @var Logger $logger */
		$logger = $this->container->get( Logger::class );
		try {
			$data = $this->data( $attrs, $block );
			$data = Hooks::filter( sprintf( 'joo/blocks/data/%s', $slug ), $data, $attrs );
			$html = self::renderTemplate( $slug, $this->dir, is_array( $data ) ? $data : array(), $attrs, $block );
		} catch ( \Throwable $error ) {
			$logger->error( sprintf( 'Block %s failed: %s', $slug, $error->getMessage() ) );
			return defined( 'WP_DEBUG' ) && WP_DEBUG ? '<!-- joo block ' . esc_html( $slug ) . ' failed -->' : '';
		}
		$html = Hooks::filter( sprintf( 'joo/blocks/html/%s', $slug ), $html, $attrs );
		return is_string( $html ) ? $html : '';
	}

	/**
	 * Includes the template with output buffering. Throws when the template does.
	 *
	 * @param string               $slug  Slug.
	 * @param string               $dir   Block folder.
	 * @param array<string, mixed> $data  Data.
	 * @param array<string, mixed> $attrs Attributes.
	 * @param \WP_Block            $block Block.
	 * @throws \Throwable Template errors.
	 */
	public static function renderTemplate( string $slug, string $dir, array $data, array $attrs, \WP_Block $block ): string {
		$file = TemplateLocator::locate( $slug, $dir );
		if ( '' === $file ) {
			return '';
		}
		$wrapper = get_block_wrapper_attributes();
		$level   = ob_get_level();
		ob_start();
		try {
			( static function () use ( $file, $data, $attrs, $block, $wrapper ): void {
				include $file;
			} )();
		} catch ( \Throwable $error ) {
			while ( ob_get_level() > $level ) {
				ob_end_clean();
			}
			throw $error;
		}
		return trim( (string) ob_get_clean() );
	}
}
