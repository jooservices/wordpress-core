<?php
/**
 * Related posts template. Variables: $data (cards, showImage, showExcerpt), $wrapper.
 *
 * @package JOOservices\ThemeCore
 */

use JOOservices\ThemeCore\Blocks\Html;

$cards = (array) ( $data['cards'] ?? array() );
if ( array() === $cards ) {
	return;
}
?>
<aside <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built by get_block_wrapper_attributes(). ?> aria-label="<?php echo esc_attr__( 'Related posts', 'joo-core' ); ?>">
<h2 class="joo-related__title"><?php echo esc_html__( 'Related posts', 'joo-core' ); ?></h2>
<ul class="joo-related__list">
<?php foreach ( $cards as $card ) : ?>
	<li class="joo-card">
		<?php
		if ( ! empty( $data['showImage'] ) && null !== $card->image ) :
			?>
			<a class="joo-card__media" href="<?php echo esc_url( $card->url ); ?>" tabindex="-1" aria-hidden="true"><?php echo Html::img( $card->image ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped helper. ?></a><?php endif; ?>
		<div class="joo-card__body">
			<h3 class="joo-card__title"><a href="<?php echo esc_url( $card->url ); ?>"><?php echo esc_html( $card->title ); ?></a></h3>
			<?php
			if ( ! empty( $data['showExcerpt'] ) && '' !== $card->excerpt ) :
				?>
				<p><?php echo esc_html( $card->excerpt ); ?></p><?php endif; ?>
		</div>
	</li>
<?php endforeach; ?>
</ul>
</aside>
