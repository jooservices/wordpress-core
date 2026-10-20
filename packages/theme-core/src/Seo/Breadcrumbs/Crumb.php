<?php
/**
 * Breadcrumb item.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Seo\Breadcrumbs;

/**
 * Label and optional URL (the current page has none).
 *
 * @internal
 */
final class Crumb {

	/**
	 * Label.
	 *
	 * @var string
	 */
	public string $label;

	/**
	 * URL (null for the current page and for label-only parents).
	 *
	 * @var string|null
	 */
	public ?string $url;

	/**
	 * Creates a crumb.
	 *
	 * @param string      $label Label.
	 * @param string|null $url   URL.
	 */
	public function __construct( string $label, ?string $url = null ) {
		$this->label = $label;
		$this->url   = $url;
	}
}
