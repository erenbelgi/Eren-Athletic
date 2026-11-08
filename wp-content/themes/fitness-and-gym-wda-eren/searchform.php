<?php $field=wp_unique_id('ea-search-field-'); ?>
<form role="search" aria-label="Sayfa araması" method="get" class="searchform" action="<?php echo esc_url(home_url('/')); ?>">
<div class="wpdevart-search-form"><label for="<?php echo esc_attr($field); ?>">Aranacak kelime</label>
<input name="s" id="<?php echo esc_attr($field); ?>" type="search" required value="<?php echo esc_attr(get_search_query()); ?>" class="wpdevart-search-sbtxt">
<button type="submit" class="wpdevart_fitness_and_gym_wda_hover_button_small">Ara</button></div></form>
