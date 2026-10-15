<?php
/**
 * Language description.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Compat\Multilingual;

/**
 * One language of the site.
 *
 * @api
 */
final class Lang {

	/**
	 * Code (`en`, `vi`, `pt-BR`).
	 *
	 * @var string
	 */
	public string $code;

	/**
	 * Locale (`en_US`).
	 *
	 * @var string
	 */
	public string $locale;

	/**
	 * Name.
	 *
	 * @var string
	 */
	public string $name;

	/**
	 * URL of the current page in this language (or its home).
	 *
	 * @var string
	 */
	public string $url;

	/**
	 * Whether it is the current language.
	 *
	 * @var bool
	 */
	public bool $isCurrent;

	/**
	 * Creates a language.
	 *
	 * @param string $code      Code.
	 * @param string $locale    Locale.
	 * @param string $name      Name.
	 * @param string $url       URL.
	 * @param bool   $isCurrent Current?.
	 */
	public function __construct( string $code, string $locale, string $name, string $url, bool $isCurrent = false ) {
		$this->code      = $code;
		$this->locale    = $locale;
		$this->name      = $name;
		$this->url       = $url;
		$this->isCurrent = $isCurrent;
	}
}
