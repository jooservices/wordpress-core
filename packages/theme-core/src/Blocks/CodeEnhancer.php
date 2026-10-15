<?php
/**
 * Copy button around code blocks.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Blocks;

/**
 * Wraps `core/code` output with a language label and a copy button (hidden until the script loads); the script
 * module is enqueued only on pages that render a code block.
 *
 * @api
 */
final class CodeEnhancer {

	/**
	 * Hooks WordPress.
	 */
	public function hook(): void {
		add_filter( 'render_block_core/code', array( $this, 'filter' ), 10, 2 );
	}

	/**
	 * Filter for render_block_core/code.
	 *
	 * @param string               $html  Rendered block.
	 * @param array<string, mixed> $block Parsed block.
	 */
	public function filter( $html, $block = array() ): string {
		unset( $block );
		$html = (string) $html;
		if ( '' === trim( $html ) || false !== strpos( $html, 'joo-code' ) ) {
			return $html;
		}
		$lang = 1 === preg_match( '/\blanguage-([a-z0-9+#-]+)/i', $html, $m ) ? strtolower( $m[1] ) : '';
		wp_enqueue_script_module( 'joo-code-copy' );
		return '<div class="joo-code"' . ( '' !== $lang ? ' data-lang="' . esc_attr( $lang ) . '"' : '' ) . '><div class="joo-code__bar">'
			. ( '' !== $lang ? '<span class="joo-code__lang">' . esc_html( $lang ) . '</span>' : '<span class="joo-code__lang"></span>' )
			. '<button type="button" class="joo-code__copy" hidden aria-label="' . esc_attr__( 'Copy code', 'joo-core' ) . '">' . esc_html__( 'Copy', 'joo-core' ) . '</button>'
			. '<span class="screen-reader-text joo-code__status" role="status" aria-live="polite"></span></div>' . $html . '</div>';
	}
}
