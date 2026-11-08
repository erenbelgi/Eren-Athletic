<?php
if(!defined('ABSPATH'))exit;
function ea_coach_photo($id,$size='large'){
    $attachment=get_post_thumbnail_id($id);$file=$attachment?get_attached_file($attachment):'';
    if(!$attachment||!$file||!is_file($file))return '<div class="ea-coach-placeholder" role="img" aria-label="'.esc_attr(get_the_title($id).' için profil fotoğrafı henüz eklenmedi').'">EA</div>';
    $alt=get_post_meta($attachment,'_wp_attachment_image_alt',true)?:get_the_title($id).' — antrenör profil görseli';
    return wp_get_attachment_image($attachment,$size,false,array('alt'=>$alt,'loading'=>'lazy','class'=>'ea-coach-photo'));
}
function ea_coach_card($id,$program=0){
    $url=$program?add_query_arg(array('ea_pt_resource'=>$program,'ea_pt_trainer'=>$id),ea_core_page_url('rezervasyon')):get_permalink($id);
    return '<article class="ea-coach-card"><a href="'.esc_url(get_permalink($id)).'">'.ea_coach_photo($id,'medium').'</a><div><p class="ea-coach-eyebrow">ANTRENÖR</p><h3><a href="'.esc_url(get_permalink($id)).'">'.esc_html(get_the_title($id)).'</a></h3><p>'.esc_html(get_post_meta($id,'_ea_expertise',true)).'</p><a class="ea-coach-link" href="'.esc_url($url).'">'.($program?'Bu antrenörle eğitimi seç ↗':'Profili ve takvimi incele ↗').'</a></div></article>';
}
function ea_coach_calendar_markup($trainer){
    $programs=ea_coach_programs($trainer);$options='';foreach($programs as$p)$options.='<option value="'.esc_attr($p->ID).'">'.esc_html($p->post_title).'</option>';
    if(!$programs)$options='<option value="'.esc_attr($trainer).'">Yalnızca antrenör uygunluğu</option>';
    return '<section class="ea-coach-availability" aria-labelledby="ea-coach-calendar-title"><p class="ea-coach-eyebrow">CANLI UYGUNLUK</p><h2 id="ea-coach-calendar-title">'.esc_html(get_the_title($trainer)).' · Takvim</h2><p>Bu takvim uygunluk gösterir; rezervasyon yetkisi vermez. Seans süresi seçilen eğitimden alınır.</p><div class="ea-calendar-form ea-coach-readonly"><p><label for="ea-resource">Takvimde görüntülenecek eğitim</label><select id="ea-resource">'.$options.'</select><input type="hidden" id="ea-trainer" value="'.esc_attr($trainer).'"></p><section class="ea-booking-calendar" hidden aria-label="Antrenör uygunluk takvimi"><div class="ea-calendar-toolbar"><button type="button" class="ea-calendar-prev" aria-label="Önceki ay">←</button><h3 class="ea-calendar-title"></h3><button type="button" class="ea-calendar-next" aria-label="Sonraki ay">→</button></div><p class="ea-calendar-message" role="status" aria-live="polite"></p><div class="ea-calendar-week" aria-hidden="true"><span>Pzt</span><span>Sal</span><span>Çar</span><span>Per</span><span>Cum</span><span>Cmt</span><span>Paz</span></div><div class="ea-calendar-days" role="group" aria-label="Gün seçimi"></div><h3 class="ea-calendar-day-title">Saat seçimi</h3><div class="ea-calendar-times" role="group" aria-label="Saat seçimi"></div></section><p><label for="ea-date">Tarih</label><input id="ea-date" type="date" min="'.esc_attr(wp_date('Y-m-d')).'"></p><p><label for="ea-slot">Uygun saatler · yalnızca bilgi</label><select id="ea-slot" disabled><option value="">Önce gün seçin</option></select><span class="ea-slot-status" role="status" aria-live="polite"></span></p><div class="ea-referee-field" hidden><input type="checkbox" tabindex="-1" aria-label="Kullanılmayan saha seçeneği" disabled></div></div>'.(!$programs?'<p>Bu antrenöre eşleştirilmiş eğitim bulunmuyor. Yönetici doğrulamasından sonra eğitim seçilebilir.</p>':'').'</section>';
}
function ea_coach_booking_select(){
    $data=array();foreach(ea_pt_posts('ea_program')as$p){$data[$p->ID]=array();foreach(ea_coach_trainers($p->ID)as$id)$data[$p->ID][]=array('id'=>$id,'title'=>get_the_title($id));}
    $program=isset($_GET['ea_pt_resource'])&&is_scalar($_GET['ea_pt_resource'])?absint($_GET['ea_pt_resource']):0;$chosen=isset($_GET['ea_pt_trainer'])&&is_scalar($_GET['ea_pt_trainer'])?absint($_GET['ea_pt_trainer']):0;
    $ids=ea_coach_trainers($program);if(!in_array($chosen,$ids,true))$chosen=(int)get_post_meta($program,'_ea_trainer',true);
    $options='';foreach($ids as$id)$options.='<option value="'.esc_attr($id).'" '.selected($chosen,$id,false).'>'.esc_html(get_the_title($id)).'</option>';
    return '<div class="ea-coach-trainer-wrap"'.(!$ids?' hidden':'').'><p><label for="ea-trainer">2. Eğitime yetkili antrenör <span aria-hidden="true">*</span></label><select id="ea-trainer" name="trainer_id"'.($ids?' required':' disabled').'>'.$options.'</select><small>Yalnızca seçilen eğitime yönetici tarafından eşleştirilmiş antrenörler listelenir.</small></p></div><script type="application/json" class="ea-coaching-data">'.wp_json_encode($data,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).'</script>';
}
add_filter('do_shortcode_tag',function($html,$tag){
    if('ea_booking_form'!==$tag)return $html;
    return str_replace('<section class="ea-booking-calendar"',ea_coach_booking_select().'<section class="ea-booking-calendar"',$html);
},25,2);
add_action('wp_enqueue_scripts',function(){
    $post=get_queried_object();$profile=is_singular('ea_trainer');$booking=$post instanceof WP_Post&&has_shortcode($post->post_content,'ea_booking_form');
    if($profile||$booking){
        wp_enqueue_style('ea-booking-calendar',EA_CORE_URL.'assets/booking-calendar.css',array('ea-core'),(string)filemtime(EA_CORE_DIR.'assets/booking-calendar.css'));
        wp_enqueue_script('ea-booking-calendar',EA_CORE_URL.'assets/booking-calendar.js',array('ea-core'),(string)filemtime(EA_CORE_DIR.'assets/booking-calendar.js'),true);
        wp_localize_script('ea-booking-calendar','eaCalendar',array('ajaxUrl'=>admin_url('admin-ajax.php'),'nonce'=>wp_create_nonce('ea_calendar'),'today'=>wp_date('Y-m-d')));
        wp_enqueue_script('ea-coaching',EA_CORE_URL.'assets/coaching.js',array('ea-booking-calendar'),(string)filemtime(EA_CORE_DIR.'assets/coaching.js'),true);
    }
    if($profile||is_singular('ea_program')||$booking)wp_enqueue_style('ea-coaching',EA_CORE_URL.'assets/coaching.css',array('ea-core'),(string)filemtime(EA_CORE_DIR.'assets/coaching.css'));
},50);
add_filter('template_include',function($template){
    if(is_singular('ea_trainer'))return EA_CORE_DIR.'includes/coaching-profile-template.php';
    if(is_singular('ea_program'))return EA_CORE_DIR.'includes/coaching-program-template.php';return $template;
},130);
