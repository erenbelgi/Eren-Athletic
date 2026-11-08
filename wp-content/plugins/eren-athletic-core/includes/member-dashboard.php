<?php
/** Private, read-only account data. The caller cannot choose a user ID. */
if ( ! defined( 'ABSPATH' ) ) exit;

function ea_dashboard_owned_posts($type,$page=1){
    global $wpdb;$uid=get_current_user_id();if(!$uid)return array();
    if(!in_array($type,array('ea_booking','ea_application'),true))return array();
    $owner="EXISTS (SELECT 1 FROM {$wpdb->postmeta} own WHERE own.post_id=p.ID AND own.meta_key='_ea_user_id' AND own.meta_value=%s)";
    $params=array((string)$uid);
    if('ea_booking'===$type){
        $owner='('.$owner.' OR EXISTS (SELECT 1 FROM '.ea_member_table('booking_allocations').' a JOIN '.ea_member_table('user_memberships').' m ON m.id=a.membership_id WHERE a.booking_id=p.ID AND a.user_id=%d AND m.user_id=%d))';
        $params[]=$uid;$params[]=$uid;
    }
    // Conflicting explicit ownership fails closed, even if another source appears to match.
    $where=" AND NOT EXISTS (SELECT 1 FROM {$wpdb->postmeta} other WHERE other.post_id=p.ID AND other.meta_key='_ea_user_id' AND other.meta_value<>%s)";
    $params[]=(string)$uid;
    if('ea_booking'===$type){
        $where.=' AND NOT EXISTS (SELECT 1 FROM '.ea_member_table('booking_allocations').' other_a JOIN '.ea_member_table('user_memberships').' other_m ON other_m.id=other_a.membership_id WHERE other_a.booking_id=p.ID AND (other_a.user_id<>%d OR other_m.user_id<>%d))';
        $params[]=$uid;$params[]=$uid;
    }else{
        $where.=" AND EXISTS (SELECT 1 FROM {$wpdb->postmeta} state WHERE state.post_id=p.ID AND state.meta_key='_ea_status' AND state.meta_value IN ('pending','contacted','accepted','rejected'))";
    }
    $params[]=$type;$params[]=max(0,((int)$page-1)*20);
    return $wpdb->get_results($wpdb->prepare("SELECT p.* FROM {$wpdb->posts} p WHERE $owner $where AND p.post_type=%s AND p.post_status IN ('private','publish','pending','draft') ORDER BY p.ID DESC LIMIT 21 OFFSET %d",$params));
}
function ea_dashboard_page_number($key){return isset($_GET[$key])&&is_scalar($_GET[$key])?min(100000,max(1,absint($_GET[$key]))):1;}
function ea_dashboard_data(){
    global $wpdb;$uid=get_current_user_id();
    if(!$uid)return new WP_Error('forbidden','Üye panelinizi görmek için giriş yapın.');
    if(get_option('ea_membership_schema_version')!==EA_MEMBERSHIP_SCHEMA_VERSION)return new WP_Error('schema','Üyelik bilgileri şu anda görüntülenemiyor. Lütfen daha sonra tekrar deneyin.');
    $page=ea_dashboard_page_number('ea_membership_page');
    $memberships=$wpdb->get_results($wpdb->prepare('SELECT m.*,e.id entitlement_id,e.total_units,e.reserved_units,e.consumed_units FROM '.ea_member_table('user_memberships').' m LEFT JOIN '.ea_member_table('membership_entitlements').' e ON e.membership_id=m.id WHERE m.user_id=%d ORDER BY m.ends_at DESC,m.id DESC LIMIT 21 OFFSET %d',$uid,($page-1)*20),ARRAY_A);
    $active=ea_membership_active($uid);
    return array('user'=>wp_get_current_user(),'active_count'=>is_wp_error($active)?0:count($active),'memberships'=>$memberships,'applications'=>ea_dashboard_owned_posts('ea_application',ea_dashboard_page_number('ea_application_page')),'bookings'=>ea_dashboard_owned_posts('ea_booking',ea_dashboard_page_number('ea_booking_page')));
}
function ea_dashboard_pagination($key,$more,$anchor){
    $page=ea_dashboard_page_number($key);if(1===$page&&!$more)return '';
    $html='<nav class="ea-account-pagination" aria-label="'.esc_attr($anchor).' sayfaları">';
    if($page>1)$html.='<a href="'.esc_url(add_query_arg($key,$page-1,ea_core_page_url('uye-panelim')).'#'.$anchor).'">← Önceki</a>';
    $html.='<span>Sayfa '.esc_html($page).'</span>';
    if($more)$html.='<a href="'.esc_url(add_query_arg($key,$page+1,ea_core_page_url('uye-panelim')).'#'.$anchor).'">Sonraki →</a>';
    return $html.'</nav>';
}
function ea_member_dashboard_shortcode(){
    ea_account_no_cache();$data=ea_dashboard_data();
    if(is_wp_error($data))return '<section class="ea-account"><div class="ea-account-card"><h2>'.esc_html($data->get_error_message()).'</h2><a class="ea-account-button" href="'.esc_url(ea_core_page_url('giris-yap')).'">Giriş Yap →</a></div></section>';
    $fitness_notice=get_transient('ea_fitness_notice_'.get_current_user_id());
    if($fitness_notice)delete_transient('ea_fitness_notice_'.get_current_user_id());
    $user=$data['user'];$labels=array('active'=>'Aktif','suspended'=>'Askıda','cancelled'=>'İptal edildi','expired'=>'Süresi doldu','scheduled'=>'Henüz başlamadı','pending'=>'Beklemede','confirmed'=>'Onaylandı','contacted'=>'İletişime geçildi');
    $html='<section class="ea-account ea-account-dashboard"><header class="ea-account-dashboard-header"><div><p class="ea-account-eyebrow">EREN ATHLETIC / ÜYE PANELİM</p><h1>Merhaba, <span>'.esc_html($user->display_name).'.</span></h1><p>Paketlerin ve seans hakların, tek bir yerde.</p></div><a class="ea-account-outline" href="'.esc_url(wp_logout_url(ea_core_page_url('giris-yap'))).'">Çıkış Yap ↗</a></header><div class="ea-account-summary"><article class="ea-account-card"><p class="ea-account-eyebrow">HESABIN</p><h2>'.esc_html($user->display_name).'</h2><p>'.esc_html($user->user_email).'</p></article><article class="ea-account-card"><p class="ea-account-eyebrow">ÜYELİK DURUMU</p><h2>'.($data['active_count']?'Aktif üyeliğin var':'Aktif paketin bulunmuyor').'</h2><p>'.($data['active_count']?esc_html($data['active_count']).' aktif paket':'Paketlerin yönetici onayından sonra burada görünür.').'</p></article></div><section id="ea-packages" class="ea-account-section"><div class="ea-account-section-heading"><p class="ea-account-eyebrow">GELİŞİMİNE DEVAM ET</p><h2>Üyelik paketlerin</h2></div><div class="ea-account-packages">';
    if($fitness_notice)$html.='<p class="ea-account-notice" role="status">'.esc_html($fitness_notice).'</p>';
    if(!$data['memberships'])$html.='<div class="ea-account-empty"><h3>İlk paketine hazır mısın?</h3><p>Henüz hesabına atanmış bir paket yok. Sana uygun eğitimleri inceleyebilirsin.</p><a href="'.esc_url(ea_core_page_url('pt-egitimleri')).'">PT paketlerini keşfet →</a></div>';
    foreach(array_slice($data['memberships'],0,20)as$m){
        if (ea_fitness_row($m)) { $html.=ea_fitness_card($m); continue; }
        $snapshot=json_decode($m['snapshot'],true);$status=$m['status'];
        if('active'===$status&&!ea_membership_valid($m))$status=$m['ends_at']<=ea_member_now()?'expired':'scheduled';
        $html.='<article class="ea-account-card ea-account-package"><div class="ea-account-package-top"><p class="ea-account-eyebrow">'.('ea_package'===$m['offer_type']?'PT PAKETİ':'SALON PAKETİ').'</p><span class="ea-account-badge" data-status="'.esc_attr($status).'">'.esc_html($labels[$status]??$status).'</span></div><h3>'.esc_html($snapshot['title']??'Üyelik paketi').'</h3><p class="ea-account-dates">'.esc_html(get_date_from_gmt($m['starts_at'],'d.m.Y H:i').' — '.get_date_from_gmt($m['ends_at'],'d.m.Y H:i')).'</p><div class="ea-account-balance"><strong>'.esc_html(ea_membership_available($m)).'</strong><span>kullanılabilir seans</span></div><dl class="ea-account-stats"><div><dt>Toplam</dt><dd>'.esc_html($m['total_units']).'</dd></div><div><dt>Ayrılmış</dt><dd>'.esc_html($m['reserved_units']).'</dd></div><div><dt>Kullanılmış</dt><dd>'.esc_html($m['consumed_units']).'</dd></div></dl></article>';
    }
    $html.='</div>'.ea_dashboard_pagination('ea_membership_page',count($data['memberships'])>20,'ea-packages').'</section><section id="ea-applications" class="ea-account-section"><h2>Üyelik Başvurularım</h2>';
    if(!$data['applications'])$html.='<p class="ea-account-empty">Hesabına bağlı bir üyelik başvurusu bulunmuyor.</p>';
    foreach(array_slice($data['applications'],0,20)as$app)$html.=ea_sales_application_row($app);
    $html.=ea_dashboard_pagination('ea_application_page',count($data['applications'])>20,'ea-applications').'</section><section id="ea-bookings" class="ea-account-section"><h2>Rezervasyonlarım</h2>';
    if(!$data['bookings'])$html.='<p class="ea-account-empty">Hesabına güvenli biçimde bağlanmış bir rezervasyon bulunmuyor.</p>';
    foreach(array_slice($data['bookings'],0,20)as$booking){$status=get_post_meta($booking->ID,'_ea_status',true);$resource=absint(get_post_meta($booking->ID,'_ea_resource',true));$html.='<article class="ea-account-history-row"><strong>'.esc_html($resource?get_the_title($resource):'Rezervasyon').'</strong><span>'.esc_html(get_post_meta($booking->ID,'_ea_booking_date',true).' / '.get_post_meta($booking->ID,'_ea_start_time',true).'–'.get_post_meta($booking->ID,'_ea_end_time',true)).'</span><span class="ea-account-badge">'.esc_html($labels[$status]??'Beklemede').'</span>'.ea_booking_panel_controls($booking->ID).'</article>';}
    return $html.ea_dashboard_pagination('ea_booking_page',count($data['bookings'])>20,'ea-bookings').'</section></section>';
}
add_shortcode('ea_member_dashboard','ea_member_dashboard_shortcode');
