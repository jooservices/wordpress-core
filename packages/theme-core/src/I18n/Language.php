<?php
/**
 * Language value.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\I18n;

/**
 * A language of the site.
 *
 * @api
 */
final class Language {

	/**
	 * Code (`en`, `vi`, `pt-BR`).
	 *
	 * @var string
	 */
	public readonly string $code;

	/**
	 * Locale (`en_US`).
	 *
	 * @var string
	 */
	public readonly string $locale;

	/**
	 * Native name (`Tiếng Việt`).
	 *
	 * @var string
	 */
	public readonly string $name;

	/**
	 * Whether the script is written right to left.
	 *
	 * @var bool
	 */
	public readonly bool $rtl;

	/**
	 * Creates a language.
	 *
	 * @param string $code   Code.
	 * @param string $locale Locale.
	 * @param string $name   Native name.
	 * @param bool   $rtl    Right to left.
	 */
	public function __construct( string $code, string $locale, string $name, bool $rtl = false ) {
		$this->code   = $code;
		$this->locale = $locale;
		$this->name   = $name;
		$this->rtl    = $rtl;
	}
}
