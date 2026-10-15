<?php
/**
 * Tag list template. Variables: $data (tags, showCounts, cloud, title), $wrapper.
 *
 * @package JOOservices\ThemeCore
 */

$joo_items = (array) ( $data['tags'] ?? array() );
if ( array() === $joo_items ) {
	return;
}
?>
<section <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built by get_block_wrapper_attributes(). ?>>
	<h2 class="joo-block-title"><?php echo esc_html( (string) $data['title'] ); ?></h2>
	<div class="joo-tags__items">
	<?php foreach ( $joo_items as $joo_tag ) : ?>
		<a rel="tag" href="<?php echo esc_url( (string) $joo_tag['url'] ); ?>"<?php echo ! empty( $data['cloud'] ) ? ' style="--joo-tag-weight:' . esc_attr( (string) $joo_tag['weight'] ) . '"' : ''; ?>>#<?php echo esc_html( (string) $joo_tag['name'] ); ?>
		<?php
		if ( ! empty( $data['showCounts'] ) ) :
			?>
			<sup><?php echo (int) $joo_tag['count']; ?></sup><?php endif; ?></a>
	<?php endforeach; ?>
	</div>
</section>
