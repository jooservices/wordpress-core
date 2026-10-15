<?php
/**
 * Top topics template. Variables: $data (topics, showImage, showIntro, title), $wrapper.
 *
 * @package JOOservices\ThemeCore
 */

use JOOservices\ThemeCore\Blocks\Html;

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Html::* helpers return escaped markup.
$topics = (array) ( $data['topics'] ?? array() );
if ( array() === $topics ) {
	return;
}
?>
<section <?php echo $wrapper; ?>>
	<h2 class="joo-block-title"><?php echo esc_html( (string) $data['title'] ); ?></h2>
	<ul class="joo-topics__list">
	<?php foreach ( $topics as $topic ) : ?>
		<li><a class="joo-topic" href="<?php echo esc_url( $topic->url ); ?>">
			<?php if ( ! empty( $data['showImage'] ) ) : ?>
				<span class="joo-topic__img">
				<?php
				if ( null !== $topic->image ) {
					echo Html::img( new \JOOservices\ThemeCore\Content\Model\ImageRef( $topic->image->url, 44, 44, '', $topic->image->srcset ), array( 'sizes' => '44px' ) );
				} else {
					echo '<span class="joo-topic__ph" aria-hidden="true">' . esc_html( mb_strtoupper( mb_substr( $topic->name, 0, 2 ) ) ) . '</span>';
				}
				?>
				</span>
			<?php endif; ?>
			<span class="joo-topic__text">
				<strong><?php echo esc_html( $topic->name ); ?> <i><?php echo (int) $topic->count; ?></i></strong>
				<?php
				if ( ! empty( $data['showIntro'] ) && '' !== $topic->intro ) :
					?>
					<span class="joo-topic__intro"><?php echo esc_html( $topic->intro ); ?></span><?php endif; ?>
			</span>
		</a></li>
	<?php endforeach; ?>
	</ul>
</section>
