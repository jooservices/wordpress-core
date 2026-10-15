<?php
/**
 * Terminal dock template. Variables: $data (enabled, openDefault), $wrapper.
 *
 * @package JOOservices\ThemeCore
 */

if ( empty( $data['enabled'] ) ) {
	return;
}
?>
<joo-terminal <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built by get_block_wrapper_attributes(). ?> data-state="closed"<?php echo ! empty( $data['openDefault'] ) ? ' data-open-default="1"' : ''; ?>>
	<button type="button" class="joo-term__tab" aria-expanded="false" aria-controls="joo-term-panel" title="<?php echo esc_attr__( 'Open terminal ( ` )', 'joo-core' ); ?>" hidden><span aria-hidden="true">$_</span><em><?php echo esc_html__( 'terminal', 'joo-core' ); ?></em></button>
	<div class="joo-term__panel" id="joo-term-panel" role="region" aria-label="<?php echo esc_attr__( 'Read-only terminal', 'joo-core' ); ?>" hidden></div>
</joo-terminal>
