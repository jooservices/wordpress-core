<?php
/**
 * Image reference (url and size).
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Content\Model;

/**
 * Image reference (url and size). Immutable.
 *
 * @internal
 */
final class ImageRef {

	/**
	 * URL.
	 *
	 * @var string
	 */
	public readonly string $url;

	/**
	 * Width in pixels (0 when unknown).
	 *
	 * @var int
	 */
	public readonly int $width;

	/**
	 * Height in pixels (0 when unknown).
	 *
	 * @var int
	 */
	public readonly int $height;

	/**
	 * Alternative text.
	 *
	 * @var string
	 */
	public readonly string $alt;

	/**
	 * Responsive candidates (srcset attribute value, empty when none).
	 *
	 * @var string
	 */
	public readonly string $srcset;

	/**
	 * Creates the value.
	 *
	 * @param string $url URL.
	 * @param int $width Width in pixels (0 when unknown).
	 * @param int $height Height in pixels (0 when unknown).
	 * @param string $alt Alternative text.
	 * @param string $srcset Responsive candidates (srcset attribute value, empty when none).
	 */
	public function __construct( string $url, int $width, int $height, string $alt, string $srcset = '' ) {
		$this->url    = $url;
		$this->width  = $width;
		$this->height = $height;
		$this->alt    = $alt;
		$this->srcset = $srcset;
	}

	/**
	 * Array form used by the REST API.
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(): array {
		return array(
			'url'    => $this->url,
			'width'  => $this->width,
			'height' => $this->height,
			'alt'    => $this->alt,
			'srcset' => $this->srcset,
		);
	}
}
