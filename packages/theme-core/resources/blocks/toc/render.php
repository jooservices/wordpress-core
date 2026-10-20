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
$open_attribute = empty( $data['collapsed'] ) ? ' open' : '';
?>
<joo-toc>
<details <?php echo $wrapper . $open_attribute; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wrapper attributes are pre-escaped and open is a fixed attribute. ?>>
<summary><?php echo esc_html__( 'On this page', 'joo-core' ); ?></summary>
<nav aria-label="<?php echo esc_attr__( 'Table of contents', 'joo-core' ); ?>"><ol>
<?php foreach ( $items as $item ) : ?>
	<li class="joo-toc__l<?php echo (int) $item['level']; ?>"><a href="#<?php echo esc_attr( (string) $item['id'] ); ?>"><?php echo esc_html( (string) $item['text'] ); ?></a></li>
<?php endforeach; ?>
</ol></nav>
</details>
</joo-toc>
