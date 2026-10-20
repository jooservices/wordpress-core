<?php
/**
 * Featured slider template. Variables: $data (cards, count, autoplay, interval, showMeta, showExcerpt), $wrapper.
 *
 * @package JOOservices\ThemeCore
 */

use JOOservices\ThemeCore\Blocks\Html;

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Html::* helpers return escaped markup.

$cards = (array) ( $data['cards'] ?? array() );
if ( array() === $cards ) {
	return;
}
$count = count( $cards );
?>
<section <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built by get_block_wrapper_attributes(). ?> aria-roledescription="<?php echo esc_attr__( 'carousel', 'joo-core' ); ?>" aria-label="<?php echo esc_attr__( 'Featured articles', 'joo-core' ); ?>">
<joo-slider data-autoplay="<?php echo ! empty( $data['autoplay'] ) ? '1' : '0'; ?>" data-interval="<?php echo (int) ( $data['interval'] ?? 6500 ); ?>">
<div class="joo-slider__track" tabindex="0">
<?php foreach ( $cards as $i => $card ) : ?>
	<article class="joo-slide" role="group" aria-roledescription="<?php echo esc_attr__( 'slide', 'joo-core' ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: 1: slide number, 2: slide count. */ __( '%1$d of %2$d', 'joo-core' ), $i + 1, $count ) ); ?>">
		<?php if ( null !== $card->image ) : ?>
			<a class="joo-slide__media" href="<?php echo esc_url( $card->url ); ?>" tabindex="-1" aria-hidden="true">
			<?php
			echo Html::img(
				$card->image,
				array(
					'eager'    => 0 === $i,
					'priority' => 0 === $i,
				)
			); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped helper. 
			?>
												</a>
		<?php endif; ?>
		<div class="joo-slide__body">
			<?php
			if ( '' !== $card->category ) :
				?>
				<span class="joo-pill"><?php echo esc_html( $card->category ); ?></span><?php endif; ?>
			<?php if ( ! empty( $data['showMeta'] ) ) : ?>
				<p class="joo-slide__meta"><time datetime="<?php echo esc_attr( $card->date ); ?>"><?php echo esc_html( wp_date( (string) get_option( 'date_format' ), (int) strtotime( $card->date ) ) ); ?></time>
				<?php
				if ( $card->readingMinutes > 0 ) :
					?>
					· <?php echo esc_html( sprintf( /* translators: %d: minutes. */ _n( '%d min read', '%d min read', $card->readingMinutes, 'joo-core' ), $card->readingMinutes ) ); ?><?php endif; ?></p>
			<?php endif; ?>
			<h3 class="joo-slide__title"><a href="<?php echo esc_url( $card->url ); ?>"><?php echo esc_html( $card->title ); ?></a></h3>
			<?php
			if ( ! empty( $data['showExcerpt'] ) && '' !== $card->excerpt ) :
				?>
				<p class="joo-slide__excerpt"><?php echo esc_html( $card->excerpt ); ?></p><?php endif; ?>
		</div>
	</article>
<?php endforeach; ?>
</div>
<?php if ( $count > 1 ) : ?>
<div class="joo-slider__controls" hidden>
	<button type="button" class="joo-slider__prev" aria-label="<?php echo esc_attr__( 'Previous slide', 'joo-core' ); ?>">←</button>
	<span class="joo-slider__dots">
	<?php foreach ( $cards as $i => $card ) : ?>
		<button type="button" class="joo-slider__dot" aria-label="<?php echo esc_attr( sprintf( /* translators: %d: slide number. */ __( 'Go to slide %d', 'joo-core' ), $i + 1 ) ); ?>"></button>
	<?php endforeach; ?>
	</span>
	<button type="button" class="joo-slider__pause" aria-label="<?php echo esc_attr__( 'Pause autoplay', 'joo-core' ); ?>" hidden>❚❚</button>
	<button type="button" class="joo-slider__next" aria-label="<?php echo esc_attr__( 'Next slide', 'joo-core' ); ?>">→</button>
</div>
<?php endif; ?>
</joo-slider>
</section>
