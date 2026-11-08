<?php if (has_post_thumbnail() ){ ?>
    <div class="wpdevart-images-hover-effect">
        <a href="<?php the_permalink(); ?>" aria-label="<?php echo esc_attr(get_the_title().' ayrıntıları'); ?>"> 
            <div class="<?php echo esc_attr( get_theme_mod( 'wpdevart_fitness_and_gym_wda_posts_list_images_hover_effect' ) ); ?>"> 
                <?php $photo=get_post_thumbnail_id();echo wp_get_attachment_image($photo,"large",false,array("alt"=>get_post_meta($photo,"_wp_attachment_image_alt",true)?:get_the_title(),"loading"=>"lazy")); ?>
            </div>
        </a>
    </div>
<?php } else if ( get_theme_mod( 'wpdevart_fitness_and_gym_wda_blog_archive_display_default_featured_image' ) == '1' ) { ?> 
    <div class="wpdevart-images-hover-effect">
        <a href="<?php the_permalink(); ?>" aria-label="<?php echo esc_attr(get_the_title().' ayrıntıları'); ?>"> 
            <div class="<?php echo esc_attr( get_theme_mod( 'wpdevart_fitness_and_gym_wda_posts_list_images_hover_effect' ) ); ?>"> 
                <img src="<?php echo esc_url(get_theme_file_uri('/images/wpdevart-no-image.jpg')); ?>" alt="" loading="lazy" />
            </div>
        </a>
    </div>
    <?php } ?>
<div class="wpdevart-posts-list-title">
<h2 class="post-list-title"><a href="<?php the_permalink(); ?>" aria-label="<?php echo esc_attr(get_the_title().' ayrıntıları'); ?>"><?php if( empty( $post->post_title ) ) { echo esc_html__( 'No Title', 'fitness-and-gym-wda'); } else { echo esc_html(wp_trim_words(get_the_title(), 4)); } ?></a></h2>
</div>
<div class="wpdevart-entry-meta">
	<svg width="24" height="23" class="wpdevart-icon-padding" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 150 140" style="enable-background:new 0 0 150 140">
		<path d="M81.61,4.73c0-2.61,2.58-4.73,5.77-4.73s5.77,2.12,5.77,4.73v20.72c0,2.61-2.58,4.73-5.77,4.73s-5.77-2.12-5.77-4.73V4.73 L81.61,4.73z M29.61,4.73c0-2.61,2.58-4.73,5.77-4.73s5.77,2.12,5.77,4.73v20.72c0,2.61-2.58,4.73-5.77,4.73s-5.77-2.12-5.77-4.73 V4.73L29.61,4.73z M6.4,45.32h110.08V21.47c0-0.8-0.33-1.53-0.86-2.07c-0.53-0.53-1.26-0.86-2.07-0.86H103 c-1.77,0-3.2-1.43-3.2-3.2c0-1.77,1.43-3.2,3.2-3.2h10.55c2.57,0,4.9,1.05,6.59,2.74c1.69,1.69,2.74,4.02,2.74,6.59v27.06v65.03 c0,2.57-1.05,4.9-2.74,6.59c-1.69,1.69-4.02,2.74-6.59,2.74H9.33c-2.57,0-4.9-1.05-6.59-2.74C1.05,118.45,0,116.12,0,113.55V48.53 V21.47c0-2.57,1.05-4.9,2.74-6.59c1.69-1.69,4.02-2.74,6.59-2.74H20.6c1.77,0,3.2,1.43,3.2,3.2c0,1.77-1.43,3.2-3.2,3.2H9.33 c-0.8,0-1.53,0.33-2.07,0.86c-0.53,0.53-0.86,1.26-0.86,2.07V45.32L6.4,45.32z M116.48,51.73H6.4v61.82c0,0.8,0.33,1.53,0.86,2.07 c0.53,0.53,1.26,0.86,2.07,0.86h104.22c0.8,0,1.53-0.33,2.07-0.86c0.53-0.53,0.86-1.26,0.86-2.07V51.73L116.48,51.73z M50.43,18.54 c-1.77,0-3.2-1.43-3.2-3.2c0-1.77,1.43-3.2,3.2-3.2h21.49c1.77,0,3.2,1.43,3.2,3.2c0,1.77-1.43,3.2-3.2,3.2H50.43L50.43,18.54z"/>
	</svg>
    <?php echo esc_html(get_the_date('F j, Y')); ?>
	<svg width="24" height="23" class="wpdevart-icon-padding" x="0px" y="0px" viewBox="0 0 24 27" enable-background="new 0 0 22 32" xmlns="http://www.w3.org/2000/svg" fill-rule="evenodd" clip-rule="evenodd">
		<path d="M12 0c6.623 0 12 5.377 12 12s-5.377 12-12 12-12-5.377-12-12 5.377-12 12-12zm8.127 19.41c-.282-.401-.772-.654-1.624-.85-3.848-.906-4.097-1.501-4.352-2.059-.259-.565-.19-1.23.205-1.977 1.726-3.257 2.09-6.024 1.027-7.79-.674-1.119-1.875-1.734-3.383-1.734-1.521 0-2.732.626-3.409 1.763-1.066 1.789-.693 4.544 1.049 7.757.402.742.476 1.406.22 1.974-.265.586-.611 1.19-4.365 2.066-.852.196-1.342.449-1.623.848 2.012 2.207 4.91 3.592 8.128 3.592s6.115-1.385 8.127-3.59zm.65-.782c1.395-1.844 2.223-4.14 2.223-6.628 0-6.071-4.929-11-11-11s-11 4.929-11 11c0 2.487.827 4.783 2.222 6.626.409-.452 1.049-.81 2.049-1.041 2.025-.462 3.376-.836 3.678-1.502.122-.272.061-.628-.188-1.087-1.917-3.535-2.282-6.641-1.03-8.745.853-1.431 2.408-2.251 4.269-2.251 1.845 0 3.391.808 4.24 2.218 1.251 2.079.896 5.195-1 8.774-.245.463-.304.821-.179 1.094.305.668 1.644 1.038 3.667 1.499 1 .23 1.64.59 2.049 1.043z"/>
	</svg>
	<?php the_author_posts_link(); ?>
	<svg width="24" height="23" class="wpdevart-icon-padding" x="0px" y="0px" viewBox="0 0 24 27" enable-background="new 0 0 24 27" xmlns="http://www.w3.org/2000/svg" fill-rule="evenodd" clip-rule="evenodd">
		<path d="M12 1c-6.338 0-12 4.226-12 10.007 0 2.05.739 4.063 2.047 5.625l-1.993 6.368 6.946-3c1.705.439 3.334.641 4.864.641 7.174 0 12.136-4.439 12.136-9.634 0-5.812-5.701-10.007-12-10.007zm0 1c6.065 0 11 4.041 11 9.007 0 4.922-4.787 8.634-11.136 8.634-1.881 0-3.401-.299-4.946-.695l-5.258 2.271 1.505-4.808c-1.308-1.564-2.165-3.128-2.165-5.402 0-4.966 4.935-9.007 11-9.007zm-5 7.5c.828 0 1.5.672 1.5 1.5s-.672 1.5-1.5 1.5-1.5-.672-1.5-1.5.672-1.5 1.5-1.5zm5 0c.828 0 1.5.672 1.5 1.5s-.672 1.5-1.5 1.5-1.5-.672-1.5-1.5.672-1.5 1.5-1.5zm5 0c.828 0 1.5.672 1.5 1.5s-.672 1.5-1.5 1.5-1.5-.672-1.5-1.5.672-1.5 1.5-1.5z"/>
	</svg>
    <a href="<?php comments_link(); ?>"><?php echo esc_html(get_comments_number()); ?></a>
</div>
<div class="wpdevart-post-text-content"><p><?php echo esc_html(wp_trim_words(get_the_content(), 20)); ?></p></div>
<?php if ( has_category() ) { ?>
    <div class="wpdevart-entry-meta-category">
        <?php echo wp_kses_post(get_the_category_list(' ')); ?>
    </div>
<?php } ?>