<?php
/**
 * Reading progress template. Variables: $data (show), $wrapper.
 *
 * @package JOOservices\ThemeCore
 */

if ( empty( $data['show'] ) ) {
	return;
}
?>
<div <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built by get_block_wrapper_attributes(). ?> role="progressbar" aria-label="<?php echo esc_attr__( 'Reading progress', 'joo-core' ); ?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><span class="joo-progress__bar"></span></div>
