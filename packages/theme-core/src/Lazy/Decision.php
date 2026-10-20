<?php
/**
 * Lazy loading decision.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Lazy;

/**
 * Outcome of Policy::decide().
 *
 * @internal
 */
final class Decision {

	/**
	 * Whether the item should be lazy.
	 *
	 * @var bool
	 */
	public bool $lazy;

	/**
	 * Why: module-off, context, post-off, post-on, type-off, yield:<plugin>, eager, lcp, filter, default.
	 *
	 * @var string
	 */
	public string $reason;

	/**
	 * Creates a decision.
	 *
	 * @param bool   $lazy   Lazy or not.
	 * @param string $reason Reason code.
	 */
	public function __construct( bool $lazy, string $reason ) {
		$this->lazy   = $lazy;
		$this->reason = $reason;
	}
}
