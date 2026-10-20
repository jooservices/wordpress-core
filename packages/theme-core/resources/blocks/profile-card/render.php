<?php
/**
 * Profile card template. Variables: $data (profile, url, showBio, showSocials, size, title, level), $wrapper.
 *
 * @package JOOservices\ThemeCore
 */

use JOOservices\ThemeCore\Blocks\Html;

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Html::* helpers return escaped markup.
$profile = $data['profile'] ?? null;
if ( null === $profile ) {
	return;
}
$level = (int) ( $data['level'] ?? 2 );
$size  = (int) ( $data['size'] ?? 64 );
?>
<section <?php echo $wrapper; ?>>
	<?php
	if ( '' !== (string) ( $data['title'] ?? '' ) ) :
		?>
		<h<?php echo $level; ?> class="joo-block-title"><?php echo esc_html( (string) $data['title'] ); ?></h<?php echo $level; ?>><?php endif; ?>
	<a class="joo-profile__who" href="<?php echo esc_url( (string) $data['url'] ); ?>">
		<?php
		if ( '' !== $profile->avatarUrl ) :
			?>
			<img class="joo-profile__avatar" src="<?php echo esc_url( $profile->avatarUrl ); ?>" alt="<?php echo esc_attr( $profile->name ); ?>" width="<?php echo $size; ?>" height="<?php echo $size; ?>" loading="lazy" decoding="async"><?php endif; ?>
		<span class="joo-profile__name"><?php echo esc_html( $profile->name ); ?></span>
		<?php
		if ( '' !== $profile->title ) :
			?>
			<span class="joo-profile__title"><?php echo esc_html( $profile->title ); ?></span><?php endif; ?>
	</a>
	<?php
	if ( ! empty( $data['showBio'] ) && '' !== $profile->shortBio ) :
		?>
		<p class="joo-profile__bio"><?php echo esc_html( $profile->shortBio ); ?></p><?php endif; ?>
	<?php if ( ! empty( $data['showSocials'] ) && array() !== $profile->socials ) : ?>
		<ul class="joo-profile__socials">
		<?php foreach ( $profile->socials as $social ) : ?>
			<li>
			<?php
			echo Html::link(
				(string) ( $social['url'] ?? '' ),
				(string) ( $social['label'] ?? '' ),
				array(
					'rel'    => 'me noopener',
					'target' => '_blank',
				)
			);
			?>
				</li>
		<?php endforeach; ?>
		</ul>
	<?php endif; ?>
	<a class="joo-profile__more" href="<?php echo esc_url( (string) $data['url'] ); ?>"><?php echo esc_html__( 'View full profile', 'joo-core' ); ?> <span class="joo-arrow" aria-hidden="true">→</span></a>
</section>
