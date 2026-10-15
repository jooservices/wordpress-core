<?php
/**
 * Language data contract.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Compat;

use JOOservices\ThemeCore\Compat\Multilingual\Lang;

/**
 * One interface for language data, so SEO and the switcher need no plugin specific code.
 *
 * @api
 */
interface Multilingual {

	/**
	 * Whether this implementation is usable now.
	 */
	public function active(): bool;

	/**
	 * Languages.
	 *
	 * @return list<Lang>
	 */
	public function languages(): array;

	/**
	 * Current language code.
	 */
	public function currentLanguage(): string;

	/**
	 * Default language code.
	 */
	public function defaultLanguage(): string;

	/**
	 * Translations of a post as code => post id.
	 *
	 * @param int $postId Post id.
	 * @return array<string, int>
	 */
	public function translations( int $postId ): array;

	/**
	 * Translations of a term as code => term id.
	 *
	 * @param int    $termId   Term id.
	 * @param string $taxonomy Taxonomy.
	 * @return array<string, int>
	 */
	public function termTranslations( int $termId, string $taxonomy ): array;

	/**
	 * Home URL of a language.
	 *
	 * @param string $code Language code.
	 */
	public function homeUrl( string $code ): string;
}
