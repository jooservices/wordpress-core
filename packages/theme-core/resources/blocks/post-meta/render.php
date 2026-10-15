<?php
/**
 * Post meta template. Variables: $data (items, _class), $wrapper.
 *
 * @package JOOservices\ThemeCore
 */

use JOOservices\ThemeCore\Blocks\Html;

$items = (array) ( $data['items'] ?? array() );
if ( array() === $items ) {
	return;
}
?>
<div <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built by get_block_wrapper_attributes(). ?>>
<?php foreach ( $items as $item ) : ?>
	<?php
	$label = Html::e( (string) $item['label'] );
	if ( ! empty( $item['datetime'] ) ) {
		echo '<time class="joo-meta__' . esc_attr( (string) $item['type'] ) . '" datetime="' . esc_attr( (string) $item['datetime'] ) . '">' . $label . '</time>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped.
	} elseif ( ! empty( $item['url'] ) ) {
		echo Html::link( (string) $item['url'], (string) $item['label'], array( 'class' => 'joo-meta__' . $item['type'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped helper.
	} else {
		echo '<span class="joo-meta__' . esc_attr( (string) $item['type'] ) . '">' . $label . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped.
	}
	?>
<?php endforeach; ?>
</div>
