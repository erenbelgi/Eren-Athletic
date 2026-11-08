<?php
/** Front-end presentation only. No writes or changes to booking/entitlement policy. */
if(!defined('ABSPATH'))exit;
add_action('wp_enqueue_scripts',function(){
 wp_enqueue_style('ea-accessibility',EA_CORE_URL.'assets/accessibility.css',array('ea-core'),filemtime(EA_CORE_DIR.'assets/accessibility.css'));
 wp_enqueue_script('ea-accessibility',EA_CORE_URL.'assets/accessibility.js',array('ea-core'),filemtime(EA_CORE_DIR.'assets/accessibility.js'),true);
 wp_dequeue_script('wpdevart-search-js');
},1000);
// Correct genuinely missing content-image attributes without overwriting intentional alt="".
add_filter('the_content',function($html){
 if(is_admin()||!class_exists('WP_HTML_Tag_Processor'))return $html;$p=new WP_HTML_Tag_Processor($html);
 while($p->next_tag('img')){if(null!==$p->get_attribute('alt'))continue;$src=$p->get_attribute('src');$id=$src?attachment_url_to_postid($src):0;$alt=$id?get_post_meta($id,'_wp_attachment_image_alt',true):'';
  if(!$alt&&$id)$alt=get_the_title($id);$p->set_attribute('alt',$alt?:'Eren Athletic — '.wp_strip_all_tags(get_the_title()));
 }return $p->get_updated_html();
},90);
