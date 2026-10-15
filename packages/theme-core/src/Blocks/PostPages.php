<?php
/**
 * Page links of multi-page posts.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Blocks;

/**
 * A post split with `<!--nextpage-->` shows one part per URL; the block editor's Post Content block prints no links
 * between the parts, so they would be unreachable. This adds the classic page links after the content.
 *
 * @internal
 */
final class PostPages {

	/**
	 * Hooks WordPress.
	 */
	public function hook(): void {
		add_filter( 'render_block_core/post-content', array( $this, 'append' ) );
	}

	/**
	 * Appends the page links.
	 *
	 * @param mixed $html Rendered post content block.
	 */
	public function append( $html ): string {
		$html = (string) $html;
		if ( ! is_singular() || false !== strpos( $html, 'joo-pages' ) ) {
			return $html;
		}
		$links = wp_link_pages(
			array(
				'echo'        => 0,
				'before'      => '<nav class="joo-pages" aria-label="' . esc_attr__( 'Pages of this post', 'joo-core' ) . '"><span class="joo-pages__label">' . esc_html__( 'Pages', 'joo-core' ) . '</span>',
				'after'       => '</nav>',
				'link_before' => '<span class="joo-pages__item">',
				'link_after'  => '</span>',
			)
		);
		return $html . (string) $links;
	}
}
