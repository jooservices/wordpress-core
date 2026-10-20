<?php
/**
 * Off-screen section rendering and comments on demand.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Lazy;

use JOOservices\ThemeCore\Config\Manifest;

/**
 * Adds the `joo-cv` class (CSS content-visibility:auto with a reserved size) to blocks that sit below the
 * first screen and defers anonymous comments on published, unprotected posts into a fragment.
 *
 * @internal
 */
final class SectionLazy {

	/**
	 * Blocks that are deferred by default.
	 */
	private const DEFAULT_BLOCKS = array( 'joo/related-posts', 'joo/author-box' );

	/**
	 * Policy.
	 *
	 * @var Policy
	 */
	private Policy $policy;

	/**
	 * Manifest.
	 *
	 * @var Manifest
	 */
	private Manifest $manifest;

	/**
	 * Fragment service.
	 *
	 * @var FragmentLazy
	 */
	private FragmentLazy $fragments;

	/**
	 * Creates the service.
	 *
	 * @param Policy       $policy    Policy.
	 * @param Manifest     $manifest  Manifest.
	 * @param FragmentLazy $fragments Fragment service.
	 */
	public function __construct( Policy $policy, Manifest $manifest, FragmentLazy $fragments ) {
		$this->policy    = $policy;
		$this->manifest  = $manifest;
		$this->fragments = $fragments;
	}

	/**
	 * Hooks WordPress.
	 */
	public function hook(): void {
		add_filter( 'render_block', array( $this, 'filter' ), 20, 2 );
		$this->fragments->register(
			'comments',
			null,
			array(
				'post'  => array(
					'type'     => 'int',
					'min'      => 1,
					'required' => true,
				),
				'cpage' => array(
					'type'    => 'int',
					'default' => 1,
					'min'     => 1,
				),
			),
			160
		);
	}

	/**
	 * Blocks deferred as sections.
	 *
	 * @return list<string>
	 */
	public function blocks(): array {
		$configured = $this->manifest->get( 'lazy.sectionBlocks', self::DEFAULT_BLOCKS );
		return is_array( $configured ) ? array_values( array_map( 'strval', $configured ) ) : self::DEFAULT_BLOCKS;
	}

	/**
	 * Filter for render_block.
	 *
	 * @param string               $html  Block HTML.
	 * @param array<string, mixed> $block Parsed block.
	 */
	public function filter( string $html, array $block ): string {
		$name = (string) ( $block['blockName'] ?? '' );
		if ( '' === trim( $html ) ) {
			return $html;
		}
		if ( 'core/comments' === $name && is_singular() ) {
			return $this->comments( $html );
		}
		if ( in_array( $name, $this->blocks(), true ) && $this->policy->decide( 'section' )->lazy ) {
			$processor = new \WP_HTML_Tag_Processor( $html );
			if ( $processor->next_tag() ) {
				$processor->add_class( 'joo-cv' );
				$processor->set_attribute( 'data-joo-section', $name );
				return $processor->get_updated_html();
			}
		}
		return $html;
	}

	/**
	 * Defers the comments block (comments are secondary content). Comment permalinks still work: the runtime
	 * loads the fragment when the URL hash points to a comment.
	 *
	 * @param string $html Rendered comments block.
	 */
	private function comments( string $html ): string {
		$post_id = (int) get_the_ID();
		if ( $post_id < 1 || ! $this->policy->decide( 'comments' )->lazy ) {
			return $html;
		}
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post || 'publish' !== $post->post_status || '' !== $post->post_password || is_user_logged_in() ) {
			return $html;
		}
		$commenter = wp_get_current_commenter();
		foreach ( array( 'comment_author', 'comment_author_email', 'comment_author_url' ) as $field ) {
			if ( '' !== (string) ( $commenter[ $field ] ?? '' ) ) {
				return $html;
			}
		}
		if ( str_contains( $html, '<joo-lazy name="comments"' ) ) {
			// WordPress can run render_block twice for the comments block; wrapping the wrapper would make the
			// fragment load itself forever and overwrite the stored snapshot.
			return $html;
		}
		$count = (int) get_comments_number( $post_id );
		$args  = array(
			'post'  => $post_id,
			'cpage' => max( 1, (int) get_query_var( 'cpage' ) ),
		);
		$lazy  = $this->fragments->wrap( 'comments', $args, static fn() => $html );
		if ( $lazy === $html ) {
			return $html;
		}
		$label = sprintf( /* translators: %d: number of comments. */ _n( 'Show %d comment', 'Show %d comments', $count, 'joo-core' ), $count );
		$label = 0 === $count ? __( 'Leave a comment', 'joo-core' ) : $label;
		return str_replace( '</div><noscript>', '<button type="button" class="joo-lazy__load" data-joo-load>' . esc_html( $label ) . '</button></div><noscript>', $lazy );
	}
}
