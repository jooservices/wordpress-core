<?php
/**
 * Search trigger template. Variables: $data (label, placeholder, action, fallback), $wrapper.
 *
 * @package JOOservices\ThemeCore
 */

?>
<joo-search <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built by get_block_wrapper_attributes(). ?>>
<a class="joo-search__link" href="<?php echo esc_url( (string) $data['fallback'] ); ?>"><?php echo esc_html( (string) $data['label'] ); ?></a>
<dialog class="joo-search__dialog" aria-label="<?php echo esc_attr( (string) $data['label'] ); ?>">
<form role="search" method="get" action="<?php echo esc_url( (string) $data['action'] ); ?>">
<label class="screen-reader-text" for="joo-search-input"><?php echo esc_html( (string) $data['label'] ); ?></label>
<input id="joo-search-input" type="search" name="s" placeholder="<?php echo esc_attr( (string) $data['placeholder'] ); ?>" autocomplete="off" required>
<button type="submit"><?php echo esc_html( (string) $data['label'] ); ?></button>
<button type="button" class="joo-search__close" aria-label="<?php echo esc_attr__( 'Close', 'joo-core' ); ?>">×</button>
</form>
</dialog>
</joo-search>
