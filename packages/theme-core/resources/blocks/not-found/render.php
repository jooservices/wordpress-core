<?php
/**
 * Not found template. Variables: $data (path, suggestions, index, home), $wrapper.
 *
 * @package JOOservices\ThemeCore
 */

$joo_path    = (string) $data['path'];
$suggestions = (array) $data['suggestions'];
$joo_index   = array_map(
	static fn( array $row ) => array(
		's' => $row['slug'],
		't' => $row['title'],
		'u' => $row['url'],
	),
	(array) $data['index']
);
$joo_json    = wp_json_encode( $joo_index, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
?>
<joo-404 <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built by get_block_wrapper_attributes(). ?> data-home="<?php echo esc_url( (string) $data['home'] ); ?>">
	<h1 class="joo-404__title">404 <?php echo esc_html__( 'Not Found', 'joo-core' ); ?></h1>
	<div class="joo-404__transcript" role="group" aria-label="<?php echo esc_attr__( 'Request transcript', 'joo-core' ); ?>">
		<div><span class="joo-404__ps">$</span> curl -i <?php echo esc_html( $joo_path ); ?></div>
		<div class="joo-404__err">HTTP/2 404</div>
		<div>cache-control: no-store</div>
		<div>x-robots-tag: noindex</div>
		<div>{"error":"not_found","path":<?php echo esc_html( (string) wp_json_encode( $joo_path, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ); ?>}</div>
	</div>
	<form class="joo-404__search" role="search" method="get" action="<?php echo esc_url( (string) $data['home'] ); ?>">
		<label for="joo-404-search"><?php echo esc_html__( 'Search the site', 'joo-core' ); ?></label>
		<input id="joo-404-search" type="search" name="s" required>
		<button type="submit"><?php echo esc_html__( 'Search', 'joo-core' ); ?></button>
	</form>
	<?php if ( array() !== $suggestions ) : ?>
	<section class="joo-404__suggest" aria-labelledby="joo-404-suggest">
		<h2 id="joo-404-suggest"><?php echo esc_html__( 'Did you mean', 'joo-core' ); ?></h2>
		<ul>
		<?php foreach ( $suggestions as $joo_suggestion ) : ?>
			<li><a href="<?php echo esc_url( (string) $joo_suggestion['url'] ); ?>"><?php echo esc_html( (string) $joo_suggestion['title'] ); ?></a></li>
		<?php endforeach; ?>
		</ul>
	</section>
	<?php endif; ?>
	<form class="joo-404__retry" hidden>
		<label for="joo-404-path"><?php echo esc_html__( 'Try another path', 'joo-core' ); ?></label>
		<input id="joo-404-path" name="path" autocomplete="off" placeholder="<?php echo esc_attr( (string) $data['placeholder'] ); ?>">
		<button type="submit">curl -i</button>
		<p class="joo-404__result" role="status" aria-live="polite" aria-atomic="true"></p>
	</form>
	<script type="application/json" class="joo-404__index"><?php echo $joo_json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON encoded with HEX_TAG, HEX_AMP and HEX_APOS. ?></script>
</joo-404>
