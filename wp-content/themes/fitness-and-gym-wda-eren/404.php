<?php get_header(); ?>
<main class="wpdevart-not-found-page-content" id="content_navigator" tabindex="-1">
<div class="wpdevart-not-found-page-image-container"><?php if(get_theme_mod('wpdevart_fitness_and_gym_wda_not_found_image')): ?><div class="wpdevart-not-found-page-image"><img src="<?php echo esc_url(get_theme_mod('wpdevart_fitness_and_gym_wda_not_found_image')); ?>" alt=""></div><?php endif; ?></div>
<div class="wpdevart-not-found-content-elements"><h1 class="wpdevart-not-found-titile"><?php echo esc_html(get_theme_mod('wpdevart_fitness_and_gym_wda_not_found_page_title')?:'Sayfa bulunamadı'); ?></h1></div>
<div class="wpdevart-not-found-content-elements"><p class="wpdevart-not-found-desc"><?php echo esc_html(get_theme_mod('wpdevart_fitness_and_gym_wda_not_found_page_description')); ?></p></div>
<div class="wpdevart-not-found-content-elements"><a href="<?php echo esc_url(home_url('/')); ?>" class="ea-button">Ana Sayfaya Dön</a></div>
<div class="wpdevart-not-found-content-elements"><div class="wpdevart-search-page-form"><?php get_search_form(); ?></div></div>
</main><?php get_footer(); ?>
