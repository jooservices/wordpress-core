<?php
/**
 * Author box template. Variables: $data (author), $wrapper.
 *
 * @package JOOservices\ThemeCore
 */

use JOOservices\ThemeCore\Blocks\Html;

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Html::* helpers return escaped markup.

$author = $data['author'] ?? null;
if ( ! is_array( $author ) || '' === (string) $author['name'] ) {
	return;
}
?>
<aside <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built by get_block_wrapper_attributes(). ?> aria-label="<?php echo esc_attr__( 'About the author', 'joo-core' ); ?>">
	<?php
	if ( '' !== (string) $author['avatar'] ) :
		?>
		<img class="joo-author__avatar" src="<?php echo esc_url( (string) $author['avatar'] ); ?>" alt="" width="64" height="64" loading="lazy" decoding="async"><?php endif; ?>
	<div class="joo-author__body">
		<p class="joo-author__name"><a href="<?php echo esc_url( (string) $author['url'] ); ?>" rel="author"><?php echo esc_html( (string) $author['name'] ); ?></a>
		<?php
		if ( '' !== (string) $author['title'] ) :
			?>
			<span class="joo-author__title"><?php echo esc_html( (string) $author['title'] ); ?></span><?php endif; ?></p>
		<?php
		if ( '' !== (string) $author['bio'] ) :
			?>
			<p class="joo-author__bio"><?php echo esc_html( (string) $author['bio'] ); ?></p><?php endif; ?>
		<?php if ( ! empty( $author['socials'] ) ) : ?>
			<ul class="joo-author__socials">
			<?php foreach ( (array) $author['socials'] as $social ) : ?>
				<li>
				<?php
				echo Html::link(
					(string) ( $social['url'] ?? '' ),
					(string) ( $social['label'] ?? '' ),
					array(
						'rel'    => 'me noopener',
						'target' => '_blank',
					)
				); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped helper. 
				?>
					</li>
			<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>
</aside>
