<?php
/**
 * Table of contents template. Variables: $data (items, collapsed), $wrapper.
 *
 * @package JOOservices\ThemeCore
 */

$items = (array) ( $data['items'] ?? array() );
if ( array() === $items ) {
	return;
}
?>
<joo-toc>
<details <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built by get_block_wrapper_attributes(). ?><?php
if ( empty( $data['collapsed'] ) ) :
	?> open<?php
endif;
?>>
<summary><?php echo esc_html__( 'On this page', 'joo-core' ); ?></summary>
<nav aria-label="<?php echo esc_attr__( 'Table of contents', 'joo-core' ); ?>"><ol>
<?php foreach ( $items as $item ) : ?>
	<li class="joo-toc__l<?php echo (int) $item['level']; ?>"><a href="#<?php echo esc_attr( (string) $item['id'] ); ?>"><?php echo esc_html( (string) $item['text'] ); ?></a></li>
<?php endforeach; ?>
</ol></nav>
</details>
</joo-toc>
