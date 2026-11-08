<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function ea_booking_member_options(){
    global $wpdb;$uid=get_current_user_id();if(!$uid)return array();
    $now=ea_member_now();$rows=$wpdb->get_results($wpdb->prepare('SELECT m.*,e.total_units,e.reserved_units,e.consumed_units FROM '.ea_member_table('user_memberships').' m JOIN '.ea_member_table('membership_entitlements').' e ON e.membership_id=m.id WHERE m.user_id=%d AND m.status=%s AND m.starts_at<=%s AND m.ends_at>%s ORDER BY m.ends_at,m.id',$uid,'active',$now,$now),ARRAY_A);
    $out=array();foreach($rows as$m){$s=json_decode($m['snapshot'],true);if(!$s||!isset($s['resources'],$s['resource_type']))continue;$out[]=array('id'=>(int)$m['id'],'title'=>$s['title']??'Paket','remaining'=>ea_membership_available($m),'ends_at'=>$m['ends_at'],'end_label'=>get_date_from_gmt($m['ends_at'],'d.m.Y H:i'),'resources'=>array_map('intval',$s['resources']),'resource_type'=>$s['resource_type']);}return $out;
}
// Keep the existing resource/date/slot DOM contract so both visual and fallback calendars still work.
add_filter('do_shortcode_tag',function($html,$tag){
    if('ea_booking_form'!==$tag)return $html;
    $options=ea_booking_member_options();$message='';
    if(!is_user_logged_in())$message='Rezervasyon yapabilmek için giriş yapmalısınız. <a href="'.esc_url(ea_core_page_url('giris-yap')).'">Giriş Yap</a> · <a href="'.esc_url(ea_core_page_url('kayit-ol')).'">Kayıt Ol</a>';
    elseif(!$options)$message='Rezervasyon yapabilmek için aktif bir üyelik paketine sahip olmalısınız. <a href="'.esc_url(ea_core_page_url('pt-egitimleri')).'">PT Paketlerini İncele</a>';
    $notice='<div class="ea-membership-booking-notice" role="status">'.$message.'</div>';
    $fields='<section class="ea-membership-booking-rights"><p><label for="ea-booking-membership">Kullanılacak paket</label><select id="ea-booking-membership" name="membership_id" required><option value="">Kaynak ve tarih seçin</option></select></p><p class="ea-membership-booking-detail"></p></section><input type="hidden" name="request_key" value="'.esc_attr(wp_generate_uuid4()).'">';
    // Wrap only personal submission fields; the public calendar remains outside the disabled fieldset.
    $html=str_replace('<p><label for="ea-name">',$notice.'<fieldset class="ea-member-booking-fields" '.($message?'disabled':'').'><legend>Üye ve paket bilgileri</legend>'.$fields.'<p><label for="ea-name">',$html);
    $html=str_replace('</form>','</fieldset></form>',$html);
    $data=wp_json_encode(array('authenticated'=>is_user_logged_in(),'memberships'=>$options),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
    return $html.'<script type="application/json" class="ea-booking-member-data">'.$data.'</script><noscript><p>Takvim ve paket seçimi için JavaScript gereklidir.</p></noscript>';
},30,2);

add_action('wp_enqueue_scripts',function(){
    $post=get_queried_object();if(!$post instanceof WP_Post||!has_shortcode($post->post_content,'ea_booking_form'))return;
    ea_account_no_cache();
    wp_enqueue_style('ea-booking-membership',EA_CORE_URL.'assets/booking-membership.css',array('ea-booking-calendar'),(string)filemtime(EA_CORE_DIR.'assets/booking-membership.css'));
    wp_enqueue_script('ea-booking-membership',EA_CORE_URL.'assets/booking-membership.js',array('ea-booking-calendar'),(string)filemtime(EA_CORE_DIR.'assets/booking-membership.js'),true);
},120);
add_action('template_redirect',function(){
    $post=get_queried_object();if($post instanceof WP_Post&&has_shortcode($post->post_content,'ea_booking_form'))ea_account_no_cache();
},1);

add_filter('do_shortcode_tag',function($html,$tag){
    if('ea_booking_form'!==$tag)return $html;
    $key=isset($_GET['ea_result'])&&is_scalar($_GET['ea_result'])?sanitize_key($_GET['ea_result']):'';
    $messages=array('login_required'=>'Rezervasyon yapabilmek için giriş yapmalısınız.','membership_required'=>'Bu hizmet veya tarih için yeterli, aktif rezervasyon hakkınız bulunmuyor. Lütfen paket ve takvim seçiminizi kontrol edin.');
    if(isset($messages[$key]))$html='<div class="ea-notice ea-notice-error" role="alert">'.esc_html($messages[$key]).'</div>'.$html;return $html;
},40,2);

function ea_booking_panel_controls($id){
    global $wpdb;$uid=get_current_user_id();if(!$uid||!ea_booking_integrated($id))return '';
    $a=$wpdb->get_row($wpdb->prepare('SELECT a.*,m.snapshot FROM '.ea_member_table('booking_allocations').' a JOIN '.ea_member_table('user_memberships').' m ON m.id=a.membership_id WHERE a.booking_id=%d AND a.user_id=%d AND m.user_id=%d',$id,$uid,$uid),ARRAY_A);if(!$a)return '';
    $s=json_decode($a['snapshot'],true);$html='<div class="ea-booking-panel-controls"><span>Paket: '.esc_html($s['title']??'Üyelik paketi').'</span>';
    $context=ea_coach_record_context(ea_booking_meta($id));
    if($context['trainer'])$html.='<span>Antrenör: '.esc_html(get_the_title($context['trainer'])).'</span>';
    if(in_array($a['state'],array('reserved','consumed'),true)&&$a['booking_start']>ea_member_now()){
        $refund=strtotime($a['booking_start'].' UTC')-time()>=24*HOUR_IN_SECONDS;
        $html.='<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="ea_member_cancel_booking"><input type="hidden" name="booking_id" value="'.esc_attr($id).'">'.wp_nonce_field('ea_member_cancel_'.$id,'_wpnonce',true,false).'<p>'.($refund?'İptal edersen seans hakkın iade edilir.':'Başlangıca 24 saatten az kaldığı için seans hakkın iade edilmez.').'</p><button class="ea-account-outline" type="submit">Rezervasyonu iptal et</button></form>';
    }return $html.'</div>';
}
add_filter('do_shortcode_tag',function($html,$tag){
    if('ea_member_dashboard'!==$tag||!is_user_logged_in()||!isset($_GET['ea_cancel'])||!is_string($_GET['ea_cancel']))return $html;
    if('success'===$_GET['ea_cancel'])return '<p class="ea-account-notice" role="status">Rezervasyon iptal edildi. Seans hakkınız iptal politikasına göre güncellendi.</p>'.$html;
    if('error'===$_GET['ea_cancel'])return '<p class="ea-account-notice ea-account-error" role="alert">Rezervasyon iptal edilemedi. Oturumunuzu ve rezervasyon durumunu kontrol edin.</p>'.$html;
    return $html;
},30,2);
