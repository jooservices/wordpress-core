<?php
/**
 * Language switcher template. Variables: $data (languages, style, show), $wrapper.
 *
 * @package JOOservices\ThemeCore
 */

if ( empty( $data['show'] ) || array() === (array) ( $data['languages'] ?? array() ) ) {
	return;
}
$items = '';
foreach ( (array) $data['languages'] as $lang ) {
	$items .= '<li><a href="' . esc_url( $lang->url ) . '" hreflang="' . esc_attr( $lang->code ) . '" lang="' . esc_attr( $lang->code ) . '"' . ( $lang->isCurrent ? ' aria-current="true"' : '' ) . '>' . esc_html( $lang->name ) . '</a></li>';
}
if ( 'dropdown' === ( $data['style'] ?? 'list' ) ) {
	$current = '';
	foreach ( (array) $data['languages'] as $lang ) {
		if ( $lang->isCurrent ) {
			$current = $lang->name;
		}
	}
	echo '<details ' . $wrapper . '><summary>' . esc_html( '' !== $current ? $current : __( 'Language', 'joo-core' ) ) . '</summary><ul>' . $items . '</ul></details>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every part is escaped above.
	return;
}
echo '<nav ' . $wrapper . ' aria-label="' . esc_attr__( 'Language', 'joo-core' ) . '"><ul>' . $items . '</ul></nav>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every part is escaped above.
