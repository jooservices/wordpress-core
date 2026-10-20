<?php
/**
 * Title: JOOservices footer
 * Slug: jooservices/site-footer
 * Categories: jooservices
 * Inserter: no
 */
?>
<!-- wp:group {"tagName":"footer","className":"joo-footer"} -->
<footer class="wp-block-group joo-footer">
	<!-- wp:group {"className":"joo-footer__about"} -->
	<div class="wp-block-group joo-footer__about">
		<!-- wp:heading {"level":4} -->
		<h4 class="wp-block-heading"><?php echo esc_html__( 'joo services', 'jooservices' ); ?></h4>
		<!-- /wp:heading -->
		<!-- wp:paragraph -->
		<p><?php echo esc_html__( 'Technical notes on AI, engineering and R&D.', 'jooservices' ); ?></p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->
	<!-- wp:group {"className":"joo-footer__col"} -->
	<div class="wp-block-group joo-footer__col">
		<!-- wp:heading {"level":4} -->
		<h4 class="wp-block-heading"><?php echo esc_html__( 'Explore', 'jooservices' ); ?></h4>
		<!-- /wp:heading -->
		<!-- wp:navigation {"overlayMenu":"never","className":"joo-footer__list","layout":{"type":"flex","orientation":"vertical"}} -->
		<!-- wp:navigation-link {"label":"<?php echo esc_attr__( 'AI', 'jooservices' ); ?>","url":"/category/ai/","kind":"custom","isTopLevelLink":true} /-->
		<!-- wp:navigation-link {"label":"<?php echo esc_attr__( 'Homelab', 'jooservices' ); ?>","url":"/category/research-and-development/homelab/","kind":"custom","isTopLevelLink":true} /-->
		<!-- wp:navigation-link {"label":"<?php echo esc_attr__( 'Study cases', 'jooservices' ); ?>","url":"/category/research-and-development/study-cases/","kind":"custom","isTopLevelLink":true} /-->
		<!-- /wp:navigation -->
	</div>
	<!-- /wp:group -->
	<!-- wp:group {"className":"joo-footer__col"} -->
	<div class="wp-block-group joo-footer__col">
		<!-- wp:heading {"level":4} -->
		<h4 class="wp-block-heading"><?php echo esc_html__( 'Connect', 'jooservices' ); ?></h4>
		<!-- /wp:heading -->
		<!-- wp:joo/profile-card {"showBio":false,"showSocials":true,"className":"joo-footer__socials"} /-->
	</div>
	<!-- /wp:group -->
	<!-- wp:group {"className":"joo-footer__col"} -->
	<div class="wp-block-group joo-footer__col">
		<!-- wp:heading {"level":4} -->
		<h4 class="wp-block-heading"><?php echo esc_html__( 'Legal', 'jooservices' ); ?></h4>
		<!-- /wp:heading -->
		<!-- wp:navigation {"overlayMenu":"never","className":"joo-footer__list","layout":{"type":"flex","orientation":"vertical"}} -->
		<!-- wp:navigation-link {"label":"<?php echo esc_attr__( 'Privacy', 'jooservices' ); ?>","url":"/privacy/","kind":"custom","isTopLevelLink":true} /-->
		<!-- wp:navigation-link {"label":"<?php echo esc_attr__( 'Contact', 'jooservices' ); ?>","url":"/contact/","kind":"custom","isTopLevelLink":true} /-->
		<!-- wp:navigation-link {"label":"<?php echo esc_attr__( 'Sitemap', 'jooservices' ); ?>","url":"/wp-sitemap.xml","kind":"custom","isTopLevelLink":true} /-->
		<!-- /wp:navigation -->
	</div>
	<!-- /wp:group -->
	<!-- wp:group {"className":"joo-footer__copy"} -->
	<div class="wp-block-group joo-footer__copy">
		<!-- wp:paragraph -->
		<p><?php echo esc_html__( '© 2026 JOOservices Ltd', 'jooservices' ); ?></p>
		<!-- /wp:paragraph -->
		<!-- wp:paragraph -->
		<p><?php echo esc_html__( 'Block theme · built for speed and SEO', 'jooservices' ); ?></p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->
</footer>
<!-- /wp:group -->
