<?php
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();
?>
<main id="content_navigator" class="ea-account-main" tabindex="-1">
<?php while(have_posts()):the_post();echo do_shortcode(get_the_content());endwhile; ?>
</main>
<?php get_footer();
