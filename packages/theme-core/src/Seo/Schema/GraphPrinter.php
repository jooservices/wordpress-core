<?php
/**
 * Prints the JSON-LD script block.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Seo\Schema;

use JOOservices\ThemeCore\Seo\HeadPrinter;
use JOOservices\ThemeCore\Seo\YieldPolicy;

/**
 * One `<script type="application/ld+json">` at wp_head priority 20.
 *
 * @internal
 */
final class GraphPrinter {

	/**
	 * Graph builder.
	 *
	 * @var Graph
	 */
	private Graph $graph;

	/**
	 * Yield policy.
	 *
	 * @var YieldPolicy
	 */
	private YieldPolicy $policy;

	/**
	 * Head printer (owns the cached request context).
	 *
	 * @var HeadPrinter
	 */
	private HeadPrinter $head;

	/**
	 * Creates the printer.
	 *
	 * @param Graph       $graph  Graph builder.
	 * @param YieldPolicy $policy Yield policy.
	 * @param HeadPrinter $head   Head printer.
	 */
	public function __construct( Graph $graph, YieldPolicy $policy, HeadPrinter $head ) {
		$this->graph  = $graph;
		$this->policy = $policy;
		$this->head   = $head;
	}

	/**
	 * Hooks the printer after the main query exists.
	 */
	public function hook(): void {
		if ( is_admin() ) {
			return;
		}
		add_action( 'wp', array( $this, 'attach' ), 2 );
	}

	/**
	 * Attaches wp_head output (not for feeds, embeds, REST and AJAX).
	 */
	public function attach(): void {
		if ( is_feed() || is_embed() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || wp_doing_ajax() ) {
			return;
		}
		remove_action( 'wp_head', array( $this, 'print' ), 20 );
		add_action( 'wp_head', array( $this, 'print' ), 20 );
	}

	/**
	 * Script block (empty string when nothing applies).
	 *
	 */
	public function render(): string {
		$document = $this->graph->build( $this->head->context(), $this->policy->shouldYield() );
		if ( null === $document ) {
			return '';
		}
		return '<script type="application/ld+json" class="joo-schema">' . Graph::encode( $document, defined( 'WP_DEBUG' ) && WP_DEBUG ) . '</script>';
	}

	/**
	 * Prints the block.
	 */
	public function print(): void {
		$html = $this->render();
		if ( '' !== $html ) {
			echo $html . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- encoded JSON with HEX_TAG and HEX_AMP.
		}
	}
}
