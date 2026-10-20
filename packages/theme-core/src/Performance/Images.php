<?php
/**
 * Image pipeline.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Performance;

use JOOservices\ThemeCore\Assets\Preloader;
use JOOservices\ThemeCore\Config\Manifest;
use JOOservices\ThemeCore\Support\Hooks;

/**
 * Responsive, correctly sized images with intrinsic dimensions, modern formats and an LCP marker.
 *
 * @internal
 */
final class Images {

	/**
	 * Manifest.
	 *
	 * @var Manifest
	 */
	private Manifest $manifest;

	/**
	 * Preloader (optional).
	 *
	 * @var Preloader|null
	 */
	private ?Preloader $preloader;

	/**
	 * Attachment id marked as the LCP candidate.
	 *
	 * @var int
	 */
	private int $lcp = 0;

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
		add_action( 'after_setup_theme', array( $this, 'registerSizes' ), 20 );
		add_filter( 'max_srcset_image_width', static fn(): int => 1920 );
		add_filter( 'image_editor_output_format', array( $this, 'outputFormats' ) );
		add_filter( 'wp_content_img_tag', array( $this, 'contentImage' ), 10, 3 );
	}

	/**
	 * Registers the manifest image sizes.
	 */
	public function registerSizes(): void {
		$sizes = $this->manifest->get( 'imageSizes', array() );
		foreach ( is_array( $sizes ) ? $sizes : array() as $size ) {
			if ( ! is_array( $size ) || ! isset( $size['name'], $size['w'], $size['h'] ) ) {
				continue;
			}
			add_image_size( (string) $size['name'], (int) $size['w'], (int) $size['h'], ! empty( $size['crop'] ) );
			if ( ! empty( $size['retina'] ) ) {
				// Same aspect ratio at twice the size: WordPress builds srcset only from sizes sharing the ratio.
				add_image_size( $size['name'] . '-2x', (int) $size['w'] * 2, (int) $size['h'] * 2, ! empty( $size['crop'] ) );
			}
		}
	}

	/**
	 * Registered size names.
	 *
	 * @return list<string>
	 */
	public function sizes(): array {
		$names = array();
		foreach ( (array) $this->manifest->get( 'imageSizes', array() ) as $size ) {
			if ( is_array( $size ) && isset( $size['name'] ) ) {
				$names[] = (string) $size['name'];
				if ( ! empty( $size['retina'] ) ) {
					$names[] = $size['name'] . '-2x';
				}
			}
		}
		return $names;
	}

	/**
	 * A named `sizes` attribute (manifest images.sizesPresets), or the input when it is not a preset name.
	 *
	 * @param string $preset Preset name or a literal sizes value.
	 */
	public function sizesFor( string $preset ): string {
		$presets = $this->manifest->get( 'images.sizesPresets', array() );
		return is_array( $presets ) && isset( $presets[ $preset ] ) ? (string) $presets[ $preset ] : $preset;
	}

	/**
	 * Marks an attachment as the LCP candidate: eager, high priority and preloaded.
	 *
	 * @param int $attachment_id Attachment id.
	 */
	public function markLcp( int $attachment_id ): void {
		$this->lcp = $attachment_id;
	}

	/**
	 * The LCP attachment id (0 when none).
	 */
	public function lcp(): int {
		return $this->lcp;
	}

	/**
	 * Builds an <img> with width, height, srcset, sizes and decoding.
	 *
	 * Options: sizes (preset or literal), class, alt, lcp (bool), loading (eager|lazy), decorative (bool).
	 *
	 * @param int                  $attachment_id Attachment id.
	 * @param string               $size          Registered size name.
	 * @param array<string, mixed> $options       Options.
	 */
	public function tag( int $attachment_id, string $size, array $options = array() ): string {
		$is_lcp = ! empty( $options['lcp'] ) || ( $this->lcp > 0 && $this->lcp === $attachment_id );
		$attr   = array( 'decoding' => 'async' );
		if ( isset( $options['sizes'] ) ) {
			$attr['sizes'] = $this->sizesFor( (string) $options['sizes'] );
		}
		if ( isset( $options['class'] ) ) {
			$attr['class'] = (string) $options['class'];
		}
		if ( isset( $options['alt'] ) ) {
			$attr['alt'] = (string) $options['alt'];
		} elseif ( ! empty( $options['decorative'] ) ) {
			$attr['alt'] = '';
		}
		if ( $is_lcp ) {
			$attr['loading']       = 'eager';
			$attr['fetchpriority'] = 'high';
			$this->preload( $attachment_id, $size, $attr['sizes'] ?? '' );
		} elseif ( isset( $options['loading'] ) ) {
			$attr['loading'] = (string) $options['loading'];
		}
		$html = wp_get_attachment_image( $attachment_id, $size, false, $attr );
		return is_string( $html ) ? $html : '';
	}

	/**
	 * Converts JPEG and PNG sub-sizes to WebP when the server supports it.
	 *
	 * @param array<string, string> $formats Source mime => output mime.
	 * @return array<string, string>
	 */
	public function outputFormats( array $formats ): array {
		$target = Hooks::filter(
			'joo/perf/image_formats',
			array(
				'image/jpeg' => 'image/webp',
				'image/png'  => 'image/webp',
			)
		);
		if ( ! is_array( $target ) || ! wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ) ) {
			return $formats;
		}
		return array_merge( $formats, $target );
	}

	/**
	 * Adds missing width/height (prevents layout shift) and a theme `sizes` preset to content images.
	 *
	 * @param string $html    Image tag.
	 * @param string $context Context.
	 * @param int    $id      Attachment id.
	 */
	public function contentImage( string $html, string $context, int $id ): string {
		unset( $context );
		if ( $id > 0 && ( false === strpos( $html, ' width=' ) || false === strpos( $html, ' height=' ) ) ) {
			$meta = wp_get_attachment_metadata( $id );
			if ( is_array( $meta ) && ! empty( $meta['width'] ) && ! empty( $meta['height'] ) ) {
				if ( false === strpos( $html, ' width=' ) ) {
					$html = str_replace( '<img ', '<img width="' . (int) $meta['width'] . '" ', $html );
				}
				if ( false === strpos( $html, ' height=' ) ) {
					$html = str_replace( '<img ', '<img height="' . (int) $meta['height'] . '" ', $html );
				}
			}
		}
		$preset = $this->sizesFor( 'content' );
		if ( 'content' !== $preset && false !== strpos( $html, ' sizes="' ) ) {
			$html = (string) preg_replace( '/ sizes="[^"]*"/', ' sizes="' . esc_attr( $preset ) . '"', $html, 1 );
		}
		return $html;
	}

	/**
	 * Adds a preload for the LCP image.
	 *
	 * @param int    $attachment_id Attachment id.
	 * @param string $size          Size name.
	 * @param string $sizes         Sizes attribute.
	 */
	private function preload( int $attachment_id, string $size, string $sizes ): void {
		if ( null === $this->preloader ) {
			return;
		}
		$src = wp_get_attachment_image_src( $attachment_id, $size );
		if ( ! is_array( $src ) ) {
			return;
		}
		$attrs  = array( 'fetchpriority' => 'high' );
		$srcset = wp_get_attachment_image_srcset( $attachment_id, $size );
		if ( is_string( $srcset ) && '' !== $srcset ) {
			$attrs['imagesrcset'] = $srcset;
			if ( '' !== $sizes ) {
				$attrs['imagesizes'] = $sizes;
			}
		}
		$this->preloader->add( $src[0], 'image', $attrs );
	}
}
