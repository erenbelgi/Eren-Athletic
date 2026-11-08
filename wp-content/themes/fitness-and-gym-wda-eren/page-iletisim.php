<?php
/** Contact page layout; cards and map remain editable in the page editor. */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();
?>
<main id="content_navigator" class="erah-contact-page" tabindex="-1">
	<div class="erah-contact-shell">
		<header class="erah-contact-hero">
			<p class="erah-contact-kicker">EREN ATHLETIC / BİZE ULAŞIN</p>
			<h1><?php the_title(); ?></h1>
			<p class="erah-contact-intro">Üyelik, antrenman ve salon kullanımı hakkında sorularınız için iletişim bilgilerini ve örnek tesis konumunu inceleyin.</p>
		</header>
		<?php while ( have_posts() ) : the_post(); ?>
			<div class="erah-contact-content"><?php the_content(); ?></div>
		<?php endwhile; ?>
	</div>
</main>
<?php get_footer(); ?>
