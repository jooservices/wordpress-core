<?php
/**
 * Companies template. Variables: $data (pinned, companies, page, totalPages, per, title, pinnedLabel, listLabel, showDesc, endpoint), $wrapper.
 *
 * @package JOOservices\ThemeCore
 */

use JOOservices\ThemeCore\Blocks\Blocks\Companies;
use JOOservices\ThemeCore\Blocks\Html;

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Html::* helpers return escaped markup.
$pinned    = $data['pinned'] ?? null;
$companies = (array) ( $data['companies'] ?? array() );
if ( null === $pinned && array() === $companies ) {
	return;
}
$total        = (int) $data['totalPages'];
$current_page = (int) $data['page'];
$joo_url      = static fn( int $n ): string => '?' . Companies::QUERY_VAR . '=' . $n . '#companies';
?>
<section <?php echo $wrapper; ?> id="companies">
	<h2 class="joo-block-title"><?php echo esc_html( (string) $data['title'] ); ?></h2>
	<?php if ( null !== $pinned ) : ?>
		<article class="joo-company joo-company--pinned">
			<p class="joo-company__label">★ <?php echo esc_html( (string) $data['pinnedLabel'] ); ?></p>
			<h3 class="joo-company__name"><?php echo '' !== $pinned->url ? Html::link( $pinned->url, $pinned->name, array( 'rel' => 'noopener' ) ) : esc_html( $pinned->name ); ?></h3>
			<p class="joo-company__role"><?php echo esc_html( $pinned->role ); ?></p>
			<?php
			if ( ! empty( $data['showDesc'] ) && '' !== $pinned->description ) :
				?>
				<p class="joo-company__desc"><?php echo esc_html( $pinned->description ); ?></p><?php endif; ?>
		</article>
	<?php endif; ?>
	<?php if ( array() !== $companies ) : ?>
	<p class="joo-companies__sub"><?php echo esc_html( (string) $data['listLabel'] ); ?></p>
	<joo-companies data-per-page="<?php echo (int) $data['per']; ?>" data-page="<?php echo $current_page; ?>" data-total-pages="<?php echo $total; ?>" data-endpoint="<?php echo esc_url( (string) $data['endpoint'] ); ?>">
		<ol class="joo-companies__list" aria-live="polite">
		<?php foreach ( $companies as $company ) : ?>
			<li class="joo-company">
				<div class="joo-company__row"><h3 class="joo-company__name"><?php echo '' !== $company->url ? Html::link( $company->url, $company->name, array( 'rel' => 'noopener' ) ) : esc_html( $company->name ); ?></h3><span class="joo-company__role"><?php echo esc_html( $company->role ); ?></span></div>
				<p class="joo-company__range"><?php echo esc_html( Companies::range( $company->from, $company->to ) ); ?></p>
				<?php
				if ( ! empty( $data['showDesc'] ) && '' !== $company->description ) :
					?>
					<p class="joo-company__desc"><?php echo esc_html( $company->description ); ?></p><?php endif; ?>
			</li>
		<?php endforeach; ?>
		</ol>
		<?php if ( $total > 1 ) : ?>
		<nav class="joo-companies__pager" aria-label="<?php echo esc_attr__( 'Companies pages', 'joo-core' ); ?>">
			<?php
			if ( $current_page > 1 ) :
				?>
				<a rel="prev" href="<?php echo esc_attr( $joo_url( $current_page - 1 ) ); ?>" aria-label="<?php echo esc_attr__( 'Previous page', 'joo-core' ); ?>">←</a><?php endif; ?>
			<?php for ( $n = 1; $n <= $total; $n++ ) : ?>
				<a href="<?php echo esc_attr( $joo_url( $n ) ); ?>"<?php echo $n === $current_page ? ' aria-current="page"' : ''; ?>><?php echo $n; ?></a>
			<?php endfor; ?>
			<?php
			if ( $current_page < $total ) :
				?>
				<a rel="next" href="<?php echo esc_attr( $joo_url( $current_page + 1 ) ); ?>" aria-label="<?php echo esc_attr__( 'Next page', 'joo-core' ); ?>">→</a><?php endif; ?>
		</nav>
		<?php endif; ?>
	</joo-companies>
	<?php endif; ?>
</section>
