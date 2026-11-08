<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head><meta charset="<?php bloginfo('charset'); ?>"><meta name="viewport" content="width=device-width, initial-scale=1"><?php wp_head(); ?></head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link screen-reader-text" href="#content_navigator">Ana içeriğe geç</a>
<?php
if(!get_theme_mod('wpdevart_fitness_and_gym_wda_enable_top_header','1'))get_template_part('template-parts/header/top-header');
get_template_part('template-parts/header/main-header');
