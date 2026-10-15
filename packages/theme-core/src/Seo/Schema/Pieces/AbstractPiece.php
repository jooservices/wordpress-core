<?php
/**
 * Base for schema pieces.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Seo\Schema\Pieces;

use JOOservices\ThemeCore\Seo\Context;
use JOOservices\ThemeCore\Seo\Schema\Piece;
use JOOservices\ThemeCore\Support\Hooks;

/**
 * Defaults plus the per-piece adjustment filter `joo/seo/schema/{piece}`.
 *
 * @internal
 */
abstract class AbstractPiece implements Piece {

	/**
	 * Piece name used in the filter (lowercase).
	 */
	abstract protected function name(): string;

	/**
	 * Builds the node.
	 *
	 * @param Context $context Context.
	 * @return array<string, mixed>
	 */
	abstract protected function build( Context $context ): array;

	/**
	 * Node with the public filter applied.
	 *
	 * @param Context $context Context.
	 * @return array<string, mixed>
	 */
	public function generate( Context $context ): array {
		$node     = $this->build( $context );
		$filtered = Hooks::filter( sprintf( 'joo/seo/schema/%s', $this->name() ), $node, $context );
		return is_array( $filtered ) ? $filtered : $node;
	}

	/**
	 * Not kept while an SEO plugin owns the graph.
	 */
	public function alwaysOn(): bool {
		return false;
	}

	/**
	 * Priority (lower is kept first).
	 */
	public function priority(): int {
		return 10;
	}

	/**
	 * Site language tag.
	 *
	 * @param Context $context Context.
	 */
	protected function language( Context $context ): string {
		return $context->language();
	}
}
