<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();
?>
<div class="page-banner">
	<div class="wpdevart-page-banner__bg"></div>
	<div class="container"><div class="page-banner-content"><h1 class="page-banner__title">Antrenörler</h1></div></div>
</div>
<div class="wpdevart-main-container">
	<main class="container wpdevart-main-content" id="content_navigator">
		<div class="generic-content generic-content-full-width">
			<?php echo do_shortcode( '[erah_trainer_cards]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
	</main>
</div>
<?php get_footer();
