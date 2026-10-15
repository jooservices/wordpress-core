<?php
/**
 * Heading ids and the outline used by the table of contents.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Blocks;

use JOOservices\ThemeCore\Seo\Text;

/**
 * Adds unique ids and a "link to section" anchor to h2..h4 headings of singular posts. The TOC computes the same
 * ids from the post content, so both always agree.
 *
 * @api
 */
final class HeadingAnchors {

	/**
	 * Ids used so far per post while rendering.
	 *
	 * @var array<int, array<string, int>>
	 */
	private static array $used = array();

	/**
	 * Whether post content is being rendered (headings of template parts, such as the footer, get no anchor).
	 *
	 * @var bool
	 */
	private static bool $inContent = false;

	/**
	 * Hooks WordPress.
	 */
	public function hook(): void {
		add_filter( 'render_block_core/heading', array( $this, 'filter' ), 10, 2 );
		add_filter( 'the_content', array( self::class, 'reset' ), 1 );
		add_filter( 'the_content', array( self::class, 'leave' ), 99999 );
	}

	/**
	 * End of a content render.
	 *
	 * @param mixed $content Content (passed through).
	 * @return mixed
	 */
	public static function leave( $content ) {
		self::$inContent = false;
		return $content;
	}

	/**
	 * Forgets used ids (start of a new content render).
	 *
	 * @param mixed $content Content (passed through).
	 * @return mixed
	 */
	public static function reset( $content ) {
		self::$used      = array();
		self::$inContent = true;
		return $content;
	}

	/**
	 * A unique, readable id for a heading text.
	 *
	 * @param string           $text Heading text.
	 * @param array<string,int> $used Ids used so far (updated).
	 */
	public static function slug( string $text, array &$used ): string {
		$base = sanitize_title( remove_accents( $text ) );
		$base = '' === $base ? 'section' : $base;
		$id   = $base;
		$n    = $used[ $base ] ?? 0;
		if ( $n > 0 ) {
			$id = $base . '-' . ( $n + 1 );
		}
		$used[ $base ] = $n + 1;
		return $id;
	}

	/**
	 * Headings of a post as a flat outline: level, text, id.
	 *
	 * @param string    $content Post content.
	 * @param list<int> $levels  Levels to keep.
	 * @return list<array{level: int, text: string, id: string}>
	 */
	public static function outline( string $content, array $levels = array( 2, 3, 4 ) ): array {
		$used  = array();
		$items = array();
		foreach ( self::headings( parse_blocks( $content ) ) as $heading ) {
			$id = '' !== $heading['anchor'] ? $heading['anchor'] : self::slug( $heading['text'], $used );
			if ( in_array( $heading['level'], $levels, true ) ) {
				$items[] = array(
					'level' => $heading['level'],
					'text'  => $heading['text'],
					'id'    => $id,
				);
			}
		}
		return $items;
	}

	/**
	 * Flattens heading blocks (also inside groups and columns).
	 *
	 * @param array<int|string, mixed> $blocks Parsed blocks.
	 * @return list<array{level: int, text: string, anchor: string}>
	 */
	private static function headings( array $blocks ): array {
		$out = array();
		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}
			if ( 'core/heading' === ( $block['blockName'] ?? '' ) ) {
				$out[] = array(
					'level'  => (int) ( $block['attrs']['level'] ?? 2 ),
					'text'   => trim( (string) preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( (string) ( $block['innerHTML'] ?? '' ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) ),
					'anchor' => (string) ( $block['attrs']['anchor'] ?? '' ),
				);
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				$out = array_merge( $out, self::headings( $block['innerBlocks'] ) );
			}
		}
		return $out;
	}

	/**
	 * Filter for render_block_core/heading.
	 *
	 * @param string               $html  Rendered heading.
	 * @param array<string, mixed> $block Parsed block.
	 */
	public function filter( $html, $block = array() ): string {
		$html = (string) $html;
		$post = get_the_ID();
		if ( ! self::$inContent || ! is_singular() || ! $post || 1 !== preg_match( '#^\s*<h([2-4])\b([^>]*)>(.*)</h\1>\s*$#is', $html, $m ) ) {
			return $html;
		}
		$level  = (int) $m[1];
		$text   = Text::plain( $m[3] );
		$used   = &self::$used[ (int) $post ];
		$used   = $used ?? array();
		$anchor = (string) ( $block['attrs']['anchor'] ?? '' );
		if ( 1 === preg_match( '/\sid="([^"]+)"/', $m[2], $idMatch ) ) {
			$id = $idMatch[1];
			if ( '' === $anchor ) {
				self::slug( $text, $used );
			}
		} else {
			$id    = '' !== $anchor ? $anchor : self::slug( $text, $used );
			$m[2] .= ' id="' . esc_attr( $id ) . '"';
		}
		$link = '<a class="joo-anchor" href="#' . esc_attr( $id ) . '" aria-label="' . esc_attr__( 'Link to section', 'joo-core' ) . '">#</a>';
		return '<h' . $level . $m[2] . '>' . $m[3] . ' ' . $link . '</h' . $level . ">\n";
	}
}
