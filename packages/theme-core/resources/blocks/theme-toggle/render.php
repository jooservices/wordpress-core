<?php
/**
 * Theme toggle template. Variables: $data (enabled, default), $wrapper.
 *
 * @package JOOservices\ThemeCore
 */

if ( empty( $data['enabled'] ) ) {
	return;
}
?>
<joo-theme-toggle data-schemes="<?php echo esc_attr( implode( ',', (array) ( $data['schemes'] ?? array( 'dark', 'light' ) ) ) ); ?>" <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built by get_block_wrapper_attributes(). ?>>
<button type="button" class="joo-theme-toggle__button" aria-label="<?php echo esc_attr__( 'Change colour theme', 'joo-core' ); ?>" hidden>
<svg class="joo-theme-toggle__moon" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z" fill="currentColor"/></svg>
<svg class="joo-theme-toggle__sun" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="4" fill="currentColor"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M5 5l2 2M17 17l2 2M19 5l-2 2M7 17l-2 2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
</button>
</joo-theme-toggle>
