<?php
/**
 * Category tree template. Variables: $data (nodes, showCounts, showDesc, expanded, title), $wrapper.
 *
 * @package JOOservices\ThemeCore
 */

$nodes = (array) ( $data['nodes'] ?? array() );
if ( array() === $nodes ) {
	return;
}
$joo_tree_walk = static function ( array $items ) use ( &$joo_tree_walk, $data ): void {
	echo '<ul>';
	foreach ( $items as $node ) {
		$has = array() !== $node['children'];
		$uid = 'joo-tree-' . (int) $node['id'];
		echo '<li class="joo-tree__item" data-id="' . (int) $node['id'] . '" data-depth="' . (int) $node['depth'] . '"' . ( $node['open'] ? ' data-open="1"' : '' ) . '>';
		echo '<div class="joo-tree__row">';
		if ( $has ) {
			echo '<button type="button" class="joo-tree__toggle" aria-expanded="true" aria-controls="' . esc_attr( $uid ) . '" aria-label="' . esc_attr( sprintf( /* translators: %s: category name. */ __( 'Toggle %s', 'joo-core' ), $node['name'] ) ) . '" hidden></button>';
		}
		echo '<a href="' . esc_url( $node['url'] ) . '"' . ( $node['current'] ? ' aria-current="page"' : '' ) . '>' . esc_html( $node['name'] ) . '</a>';
		if ( ! empty( $data['showCounts'] ) ) {
			echo '<span class="joo-tree__count">' . (int) $node['count'] . '</span>';
		}
		echo '</div>';
		if ( ! empty( $data['showDesc'] ) && '' !== $node['desc'] ) {
			echo '<p class="joo-tree__desc" title="' . esc_attr( $node['fullDesc'] ) . '">' . esc_html( $node['desc'] ) . '</p>';
		}
		if ( $has ) {
			echo '<div id="' . esc_attr( $uid ) . '" class="joo-tree__children">';
			$joo_tree_walk( $node['children'] );
			echo '</div>';
		}
		echo '</li>';
	}
	echo '</ul>';
};
?>
<joo-tree data-expanded-depth="<?php echo (int) $data['expanded']; ?>">
<nav <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built by get_block_wrapper_attributes(). ?> aria-label="<?php echo esc_attr( (string) $data['title'] ); ?>">
<?php $joo_tree_walk( $nodes ); ?>
</nav>
</joo-tree>
