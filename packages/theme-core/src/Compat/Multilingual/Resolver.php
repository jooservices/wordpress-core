<?php
/**
 * Picks the multilingual implementation.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Compat\Multilingual;

use JOOservices\ThemeCore\Compat\Multilingual;

/**
 * First active plugin implementation, else the built-in tagging.
 *
 * @internal
 */
final class Resolver {

	/**
	 * Implementations in priority order.
	 *
	 * @var list<Multilingual>
	 */
	private array $implementations;

	/**
	 * Built-in fallback.
	 *
	 * @var BuiltInAdapter
	 */
	private BuiltInAdapter $builtIn;

	/**
	 * Creates the resolver.
	 *
	 * @param list<Multilingual> $implementations Plugin implementations.
	 * @param BuiltInAdapter     $builtIn         Fallback.
	 */
	public function __construct( array $implementations, BuiltInAdapter $builtIn ) {
		$this->implementations = $implementations;
		$this->builtIn         = $builtIn;
	}

	/**
	 * The implementation to use.
	 */
	public function get(): Multilingual {
		foreach ( $this->implementations as $implementation ) {
			if ( $implementation->active() ) {
				return $implementation;
			}
		}
		return $this->builtIn;
	}

	/**
	 * Whether a plugin (not the built-in tagging) is in charge.
	 */
	public function isPlugin(): bool {
		return $this->get() !== $this->builtIn;
	}
}
