<?php
/**
 * Structured data piece.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Seo\Schema;

use JOOservices\ThemeCore\Seo\Context;

/**
 * One node of the JSON-LD graph.
 *
 * @internal
 */
interface Piece {

	/**
	 * Absolute IRI of the node (for example home_url('/#website')).
	 *
	 * @param Context $context Context.
	 */
	public function id( Context $context ): string;

	/**
	 * Whether the node belongs on this page.
	 *
	 * @param Context $context Context.
	 */
	public function isNeeded( Context $context ): bool;

	/**
	 * Node properties (references to other nodes use ['@id' => ...]).
	 *
	 * @param Context $context Context.
	 * @return array<string, mixed>
	 */
	public function generate( Context $context ): array;

	/**
	 * Whether the node is still printed while an SEO plugin owns the rest of the graph.
	 */
	public function alwaysOn(): bool;

	/**
	 * Lower numbers are kept first when the size guard drops nodes.
	 */
	public function priority(): int;
}
