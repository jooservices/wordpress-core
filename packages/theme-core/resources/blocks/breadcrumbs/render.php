<?php
/**
 * Breadcrumbs template. Variables: $data (items, _class), $wrapper.
 *
 * @package JOOservices\ThemeCore
 */

use JOOservices\ThemeCore\Blocks\Html;

$items = (array) ( $data['items'] ?? array() );
if ( array() === $items ) {
	return;
}
$last = count( $items ) - 1;
?>
<nav <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built by get_block_wrapper_attributes(). ?> aria-label="<?php echo esc_attr__( 'Breadcrumb', 'joo-core' ); ?>"><ol>
<?php foreach ( $items as $i => $item ) : ?>
	<li>
	<?php
	if ( $i === $last || empty( $item['url'] ) ) {
		echo '<span' . ( $i === $last ? ' aria-current="page"' : '' ) . ' title="' . esc_attr( (string) $item['title'] ) . '">' . Html::e( (string) $item['label'] ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped helpers.
	} else {
		echo Html::link( (string) $item['url'], (string) $item['label'], array( 'title' => (string) $item['title'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped helper.
	}
	?>
	</li>
<?php endforeach; ?>
</ol></nav>
