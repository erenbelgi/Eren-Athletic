<?php
/** Stage 4: calendar-month fitness access, independent of reservation entitlements. */
if (!defined('ABSPATH')) exit;
define('EA_FITNESS_VERSION', '1.0.0');

function ea_fitness_ready() { return get_option('ea_fitness_version') === EA_FITNESS_VERSION; }
function ea_fitness_offer($id) {
    return 'ea_membership' === get_post_type($id) && 'fitness' === get_post_meta($id, '_ea_offer_kind', true);
}
function ea_fitness_row($m) { return ($m['membership_type'] ?? '') === 'time_based'; }

/** Additive, retryable DDL; marker is written only after the catalog transaction succeeds. */
function ea_fitness_migrate() {
    global $wpdb;
    try { ea_member_admin(); } catch (Throwable $e) { return new WP_Error('forbidden', $e->getMessage()); }
    if (ea_fitness_ready()) return true;
    if (get_option('ea_membership_schema_version') !== EA_MEMBERSHIP_SCHEMA_VERSION) return new WP_Error('schema','Önce mevcut üyelik altyapısı hazır olmalı.');
    $lock='ea_fitness_schema_'.md5(DB_NAME.$wpdb->prefix);
    if (1 !== (int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,10)', $lock))) return new WP_Error('migration','Fitness kurulumu kilitlenemedi.');
    try {
        if (ea_fitness_ready()) return true;
        $table=ea_member_table('user_memberships');
        foreach (array('membership_type'=>"varchar(24) NOT NULL DEFAULT 'session_based'",'access_scope'=>"varchar(32) NOT NULL DEFAULT 'reservation_resources'",'access_policy'=>"varchar(40) NOT NULL DEFAULT 'session_entitlement'",'renewal_of'=>'bigint unsigned NULL') as $column=>$definition) {
            if (!$wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM `$table` LIKE %s",$column))) ea_member_sql("ALTER TABLE `$table` ADD `$column` $definition");
        }
        // Time-based audit events have no entitlement. Preserve the existing foreign key for non-NULL IDs.
        $events=ea_member_table('entitlement_events');
        ea_member_sql("ALTER TABLE `$events` MODIFY entitlement_id bigint unsigned NULL");
        $result=ea_member_transaction(function() use($wpdb) {
            foreach (array(1,3,6,12) as $months) {
                $slug='fitness-'.$months.'-ay';
                $existing=get_posts(array('post_type'=>'ea_membership','post_status'=>'any','name'=>$slug,'numberposts'=>2));
                ea_member_require(count($existing)<=1,'Fitness katalog anahtarı mükerrer.');
                if ($existing) {
                    ea_member_require(ea_fitness_offer($existing[0]->ID) && (int)get_post_meta($existing[0]->ID,'_ea_calendar_months',true)===$months,'Mevcut katalog anahtarı farklı bir pakete ait.');
                    continue;
                }
                $id=wp_insert_post(array('post_type'=>'ea_membership','post_status'=>'publish','post_name'=>$slug,'post_title'=>$months.' Aylık Fitness Üyeliği','post_content'=>'Tanımlı fitness çalışma saatlerinde sınırsız bireysel fitness alanı erişimi. PT eğitimi ve saha kiralama dahil değildir.','meta_input'=>array('_ea_offer_kind'=>'fitness','_ea_calendar_months'=>$months,'_ea_duration'=>$months.' takvim ayı','_ea_terms'=>'Sınırsız fitness erişimi. PT, antrenör rezervasyonu ve saha kiralama dahil değildir.')),true);
                ea_member_require(!is_wp_error($id) && $id,'Fitness paketi oluşturulamadı.');
                // Deliberately no price and no session_count metadata.
            }
            ea_member_sql($wpdb->prepare("INSERT INTO {$wpdb->options} (option_name,option_value,autoload) VALUES (%s,%s,'off') ON DUPLICATE KEY UPDATE option_value=VALUES(option_value)",'ea_fitness_version',EA_FITNESS_VERSION));
            return true;
        });
        wp_cache_delete('ea_fitness_version','options');wp_cache_delete('notoptions','options');
        return $result;
    } catch (Throwable $e) { return new WP_Error('migration',$e->getMessage()); }
    finally { $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock)); }
}
add_action('admin_init',function(){
    if (!current_user_can('manage_options')) return;
    $r=ea_fitness_migrate();
    if(is_wp_error($r))add_action('admin_notices',function()use($r){echo '<div class="notice notice-error"><p>'.esc_html($r->get_error_message()).'</p></div>';});
},20);

/** Clamp once in local calendar time. Store UTC. Start inclusive; end exclusive. */
function ea_fitness_end($start_utc,$months) {
    ea_member_require(in_array((int)$months,array(1,3,6,12),true),'Fitness süresi geçersiz.');
    $start=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$start_utc,new DateTimeZone('UTC'));
    ea_member_require($start && $start->format('Y-m-d H:i:s')===$start_utc,'Başlangıç zamanı geçersiz.');
    $local=$start->setTimezone(wp_timezone());
    $target=$local->modify('first day of this month')->modify('+'.(int)$months.' months');
    $end=$target->setDate((int)$target->format('Y'),(int)$target->format('m'),min((int)$local->format('d'),(int)$target->format('t')));
    return $end->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
}
function ea_fitness_snapshot($id) {
    ea_member_require(ea_fitness_ready() && ea_fitness_offer($id) && 'publish'===get_post_status($id),'Fitness paketi bulunamadı.');
    $months=(int)get_post_meta($id,'_ea_calendar_months',true);
    ea_member_require(in_array($months,array(1,3,6,12),true),'Fitness süresi geçersiz.');
    return array('title'=>get_the_title($id),'offer_type'=>'ea_membership','offer_id'=>(int)$id,'membership_type'=>'time_based','access_scope'=>'fitness_area','access_policy'=>'unlimited_during_validity','calendar_months'=>$months,'timezone'=>wp_timezone_string(),'boundary'=>'start_inclusive_end_exclusive','price'=>get_post_meta($id,'_ea_price',true),'policy_version'=>1);
}
function ea_fitness_metrics($m,$now=null) {
    $at=$now??ea_member_now();$start=strtotime($m['starts_at'].' UTC');$end=strtotime($m['ends_at'].' UTC');$ts=strtotime($at.' UTC');
    $status=$m['status'];if('active'===$status)$status=$at<$m['starts_at']?'scheduled':($at>=$m['ends_at']?'expired':'active');
    return array('status'=>$status,'remaining_days'=>max(0,(int)ceil(($end-max($ts,$start))/DAY_IN_SECONDS)),'progress'=>round(min(100,max(0,100*($ts-$start)/max(1,$end-$start))),2));
}
function ea_fitness_activate($uid,$offer_id,$request_key,$application_id=0,$payment_verified=false) {
    $result=ea_member_transaction(function()use($uid,$offer_id,$request_key,$application_id,$payment_verified){
        global $wpdb;ea_member_admin();ea_booking_engine_guard();$offer=ea_fitness_snapshot($offer_id);$key=ea_member_key('fitness_activate',$request_key);
        ea_member_require($wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->users} WHERE ID=%d FOR UPDATE",$uid)),'Kullanıcı bulunamadı.');
        ea_member_sql($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE ID=%d FOR UPDATE",$offer_id));
        clean_post_cache($offer_id);$offer=ea_fitness_snapshot($offer_id);
        $old=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.ea_member_table('user_memberships').' WHERE activation_key=%s FOR UPDATE',$key),ARRAY_A);
        if($old){ea_member_require((int)$old['user_id']===(int)$uid && (int)$old['offer_id']===(int)$offer_id && (int)$old['application_id']===(int)$application_id,'İşlem anahtarı farklı aktivasyona ait.');return (int)$old['id'];}
        $start=ea_member_now();$renewal=0;
        if($application_id){
            ea_member_sql($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE ID=%d FOR UPDATE",$application_id));
            $meta=ea_booking_meta($application_id);
            ea_member_require('ea_application'===get_post_type($application_id) && 'trash'!==get_post_status($application_id) && (int)($meta['_ea_user_id']??0)===(int)$uid && ($meta['_ea_offer_type']??'')==='ea_membership' && (int)($meta['_ea_offer_id']??0)===(int)$offer_id && ($meta['_ea_fitness_application']??'')==='1','Başvuru veya hesap bağlantısı geçersiz.');
            $approved=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.ea_member_table('user_memberships').' WHERE application_id=%d',$application_id),ARRAY_A);
            if($approved){ea_member_require((int)$approved['user_id']===(int)$uid && (int)$approved['offer_id']===(int)$offer_id,'Başvuru başka üyeliğe bağlı.');return (int)$approved['id'];}
            if(function_exists('ea_sales_activation_check'))ea_sales_activation_check($application_id,$meta,$offer,$payment_verified);
            if(!empty($meta['_ea_application_snapshot']))$offer['application_quote']=json_decode($meta['_ea_application_snapshot'],true);
            ea_member_require(in_array($meta['_ea_status']??'',array('pending','contacted'),true),'Başvuru onaylanabilir durumda değil.');
            $renewal=(int)($meta['_ea_renewal_of']??0);
            if($renewal){
                $source=ea_member_row('user_memberships',$renewal,true);
                ea_member_require(ea_fitness_row($source) && (int)$source['user_id']===(int)$uid && (int)$source['offer_id']===(int)$offer_id,'Yenilenecek üyelik geçersiz.');
                // New row preserves old audit history; future starts queue behind ALL approved same-offer periods.
                $latest=$wpdb->get_var($wpdb->prepare('SELECT MAX(ends_at) FROM '.ea_member_table('user_memberships')." WHERE user_id=%d AND offer_id=%d AND membership_type='time_based' AND status='active'",$uid,$offer_id));
                if($latest && $latest>$start)$start=$latest;
            }
        }
        $id=ea_member_insert('user_memberships',array('user_id'=>$uid,'offer_type'=>'ea_membership','offer_id'=>$offer_id,'application_id'=>$application_id?:null,'status'=>'active','starts_at'=>$start,'ends_at'=>ea_fitness_end($start,$offer['calendar_months']),'approved_by'=>get_current_user_id(),'approved_at'=>ea_member_now(),'snapshot'=>wp_json_encode($offer),'activation_key'=>$key,'membership_type'=>'time_based','access_scope'=>'fitness_area','access_policy'=>'unlimited_during_validity','renewal_of'=>$renewal?:null));
        ea_member_event($id,null,0,'fitness_activate',0,0,0,$renewal?'Yenileme onayı: yeni üyelik; önceki #'.$renewal:'Yönetici fitness aktivasyonu',$key);
        if($application_id){
            ea_booking_write_meta($application_id,array('_ea_status'=>'accepted','_ea_approved_membership'=>$id));
            if(function_exists('ea_sales_activation_record'))ea_sales_activation_record($application_id,$id,$meta,$offer,$key,$payment_verified);
        }
        return $id;
    });
    if($application_id)clean_post_cache($application_id);
    return $result;
}

/** Weekly hours are explicit facility configuration; missing configuration denies access. */
function ea_fitness_parse_hours($text) {
    $rows=array();
    foreach(preg_split('/\R/u',trim($text))as$line){
        if(''===trim($line))continue;
        ea_member_require((bool)preg_match('/^(mon|tue|wed|thu|fri|sat|sun)\s+((?:[01]\d|2[0-3]):[0-5]\d)\s*-\s*((?:[01]\d|2[0-3]):[0-5]\d)$/',trim($line),$m),'Saat biçimi: mon 09:00-21:00. Her aralık ayrı satır olmalı.');
        ea_member_require($m[2]<$m[3],'Kapanış açılıştan sonra olmalı.');$rows[]=array($m[1],$m[2],$m[3]);
    }
    return $rows;
}
function ea_fitness_access($membership_id,$at=null) {
    try {
        $m=ea_member_row('user_memberships',$membership_id);ea_member_actor($m['user_id']);$at=$at??ea_member_now();
        if(!ea_fitness_row($m) || $m['access_scope']!=='fitness_area' || $m['access_policy']!=='unlimited_during_validity' || !ea_membership_valid($m,$at))return false;
        $date=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$at,new DateTimeZone('UTC'));
        if(!$date || $date->format('Y-m-d H:i:s')!==$at)return false;
        $local=$date->setTimezone(wp_timezone());
        foreach(ea_fitness_parse_hours((string)get_option('ea_fitness_hours',''))as$h)if(strtolower($local->format('D'))===$h[0] && $h[1].':00'<=$local->format('H:i:s') && $local->format('H:i:s')<$h[2].':00')return true;
        return false;
    }catch(Throwable $e){return new WP_Error('fitness',$e->getMessage());}
}

/** Account is always taken from the session. Serialize on the user to prevent duplicate pending applications. */
function ea_fitness_apply($input) {
    $id=ea_member_transaction(function()use($input){
        global $wpdb;$uid=get_current_user_id();ea_member_require($uid>0,'Başvuru için giriş yapın.');ea_booking_engine_guard();
        foreach(array('ea_nonce','offer_id','renewal_of','request_key','phone','quote_token')as$f)ea_member_require(!isset($input[$f])||is_scalar($input[$f]),'Geçersiz alan.');
        ea_member_require(wp_verify_nonce($input['ea_nonce']??'','ea_fitness_application'),'Form doğrulanamadı.');
        $offer_id=absint($input['offer_id']??0);ea_fitness_snapshot($offer_id);$renewal=absint($input['renewal_of']??0);
        $key=ea_member_key('fitness_application_'.$uid,(string)($input['request_key']??''));
        ea_member_sql($wpdb->prepare("SELECT ID FROM {$wpdb->users} WHERE ID=%d FOR UPDATE",$uid));
        if($renewal){$m=ea_member_row('user_memberships',$renewal);ea_member_require(ea_fitness_row($m)&&(int)$m['user_id']===$uid&&(int)$m['offer_id']===$offer_id,'Üyelik hesabınıza ait değil.');}
        $old=$wpdb->get_var($wpdb->prepare("SELECT p.ID FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} k ON k.post_id=p.ID WHERE p.post_type='ea_application' AND k.meta_key='_ea_application_key' AND k.meta_value=%s",$key));
        if($old){$v=ea_booking_meta($old);ea_member_require((int)$v['_ea_user_id']===$uid&&(int)$v['_ea_offer_id']===$offer_id&&(int)$v['_ea_renewal_of']===$renewal,'Anahtar başka başvuruya ait.');return (int)$old;}
        $pending=$wpdb->get_var($wpdb->prepare("SELECT p.ID FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} u ON u.post_id=p.ID AND u.meta_key='_ea_user_id' JOIN {$wpdb->postmeta} o ON o.post_id=p.ID AND o.meta_key='_ea_offer_id' JOIN {$wpdb->postmeta} r ON r.post_id=p.ID AND r.meta_key='_ea_renewal_of' JOIN {$wpdb->postmeta} s ON s.post_id=p.ID AND s.meta_key='_ea_status' WHERE p.post_type='ea_application' AND p.post_status<>'trash' AND u.meta_value=%s AND o.meta_value=%s AND r.meta_value=%s AND s.meta_value IN ('pending','contacted') LIMIT 1",(string)$uid,(string)$offer_id,(string)$renewal));
        if($pending)return (int)$pending;
        $quote=null;
        if(function_exists('ea_sales_ready') && ea_sales_ready()){
            ea_member_sql($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE ID=%d FOR UPDATE",$offer_id));clean_post_cache($offer_id);
            ea_member_require(isset($input['quote_token']) && hash_equals(ea_sales_quote_token($offer_id,$renewal),(string)$input['quote_token']),'Paket fiyatı veya koşulları değişmiş olabilir. Sayfayı yenileyip bilgileri kontrol edin.');
            $quote=ea_sales_quote($offer_id);
        }
        $user=wp_get_current_user();$phone=sanitize_text_field(wp_unslash($input['phone']??''));ea_member_require(strlen($phone)<=40,'Telefon çok uzun.');
        $now=ea_member_now();$post=array('post_author'=>$uid,'post_date'=>current_time('mysql'),'post_date_gmt'=>$now,'post_modified'=>current_time('mysql'),'post_modified_gmt'=>$now,'post_title'=>$user->display_name.' — '.($renewal?'Fitness yenileme':'Fitness başvurusu'),'post_status'=>'private','post_type'=>'ea_application','comment_status'=>'closed','ping_status'=>'closed','post_content'=>'','post_excerpt'=>'','to_ping'=>'','pinged'=>'','post_content_filtered'=>'');
        ea_member_require(false!==$wpdb->insert($wpdb->posts,$post),'Başvuru kaydedilemedi.');$id=(int)$wpdb->insert_id;
        ea_booking_write_meta($id,array('_ea_user_id'=>$uid,'_ea_offer_type'=>'ea_membership','_ea_offer_id'=>$offer_id,'_ea_membership'=>$offer_id,'_ea_fitness_application'=>'1','_ea_renewal_of'=>$renewal,'_ea_application_key'=>$key,'_ea_status'=>'pending','_ea_full_name'=>$user->display_name,'_ea_email'=>$user->user_email,'_ea_phone'=>$phone));
        if($quote){ea_booking_write_meta($id,array('_ea_application_snapshot'=>wp_json_encode($quote)));ea_sales_event($id,'submitted','Üyelik başvurusu alındı; yönetici incelemesi bekleniyor.',array('quote'=>$quote),hash('sha256','sales_submit|'.$key));}
        return $id;
    });
    if(!is_wp_error($id))clean_post_cache($id);return $id;
}
add_action('admin_post_ea_fitness_application',function(){
    $result=ea_fitness_apply($_POST);
    set_transient('ea_fitness_notice_'.get_current_user_id(),is_wp_error($result)?$result->get_error_message():'Başvurunuz alındı. Yönetici onayı bekleniyor.',60);
    wp_safe_redirect(ea_core_page_url('uye-panelim'));exit;
});
add_action('admin_post_nopriv_ea_fitness_application',function(){wp_safe_redirect(ea_core_page_url('giris-yap'));exit;});

function ea_fitness_application_form($offer_id,$renewal=0) {
    if(function_exists('ea_sales_ready') && ea_sales_ready())return ea_sales_application_form($offer_id,$renewal);
    if(!is_user_logged_in())return '<p><a class="ea-button" href="'.esc_url(ea_core_page_url('giris-yap')).'">Giriş yap</a> <a href="'.esc_url(ea_core_page_url('kayit-ol')).'">Kayıt ol</a></p>';
    return '<form class="ea-fitness-application" method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="ea_fitness_application"><input type="hidden" name="offer_id" value="'.esc_attr($offer_id).'"><input type="hidden" name="renewal_of" value="'.esc_attr($renewal).'"><input type="hidden" name="request_key" value="'.esc_attr(wp_generate_uuid4()).'">'.wp_nonce_field('ea_fitness_application','ea_nonce',true,false).'<button class="ea-button" type="submit">'.($renewal?'Yenileme başvurusu gönder':'Üyelik başvurusu gönder').'</button><p>Yönetici onayı gerekir. Ödeme veya otomatik aktivasyon yapılmaz.</p></form>';
}
function ea_fitness_card($m) {
    $s=json_decode($m['snapshot'],true);$v=ea_fitness_metrics($m);$labels=array('active'=>'Aktif','suspended'=>'Askıda','cancelled'=>'İptal Edilmiş','expired'=>'Süresi Doldu','scheduled'=>'Henüz başlamadı');
    return '<article class="ea-account-card ea-account-package ea-fitness-card"><div class="ea-account-package-top"><p class="ea-account-eyebrow">FITNESS ÜYELİĞİ</p><span class="ea-account-badge" data-status="'.esc_attr($v['status']).'">'.esc_html($labels[$v['status']]??$v['status']).'</span></div><h3>'.esc_html($s['title']??'Fitness Üyeliği').'</h3><p>Başlangıç: '.esc_html(get_date_from_gmt($m['starts_at'],'d.m.Y H:i')).'<br>Bitiş: '.esc_html(get_date_from_gmt($m['ends_at'],'d.m.Y H:i')).'</p><div class="ea-fitness-days"><strong>'.esc_html($v['remaining_days']).'</strong> kalan gün</div><progress class="ea-fitness-progress" value="'.esc_attr($v['progress']).'" max="100" aria-label="Üyelik süresi ilerlemesi">'.esc_html($v['progress']).'%</progress><p>Fitness Alanı Erişimi: <strong>Sınırsız</strong></p><p>Aktif üyelik süresince, tanımlı fitness çalışma saatlerinde. PT ve saha kiralama dahil değildir.</p><a class="ea-account-outline" href="'.esc_url(add_query_arg(array('fitness_offer'=>$m['offer_id'],'renewal_of'=>$m['id']),ea_core_page_url('uyelik'))).'#ea-fitness-apply">Üyeliğimi Yenile →</a></article>';
}
function ea_fitness_catalog() {
    if(function_exists('ea_sales_ready') && ea_sales_ready())return ea_sales_fitness_catalog();
    if(!ea_fitness_ready())return '';
    $html='<section class="ea-fitness-catalog"><p class="ea-account-eyebrow">KENDİ TEMPONDA ANTRENMAN</p><h2>Fitness üyelikleri</h2><p>Takvim ayı esaslı üyelik. Tanımlı çalışma saatlerinde sınırsız fitness alanı kullanımı.</p><div class="ea-grid">';
    foreach(get_posts(array('post_type'=>'ea_membership','post_status'=>'publish','numberposts'=>-1,'meta_key'=>'_ea_offer_kind','meta_value'=>'fitness','orderby'=>'ID','order'=>'ASC'))as$p){
        $price=trim((string)get_post_meta($p->ID,'_ea_price',true));
        $html.='<article class="ea-card ea-fitness-card"><div class="ea-card-body"><p class="ea-account-eyebrow">FITNESS / '.esc_html(get_post_meta($p->ID,'_ea_calendar_months',true)).' TAKVİM AYI</p><h3>'.esc_html($p->post_title).'</h3><p class="ea-fitness-price">'.esc_html(''===$price?'Fiyat bilgisi için iletişime geçin':$price).'</p><ul><li>Sınırsız fitness erişimi</li><li>PT eğitimi ve antrenör rezervasyonu dahil değildir</li><li>Basketbol ve voleybol sahası kiralama dahil değildir</li></ul>'.(is_user_logged_in()?'<a class="ea-button" href="'.esc_url(add_query_arg('fitness_offer',$p->ID,ea_core_page_url('uyelik'))).'#ea-fitness-apply">Başvur →</a>':ea_fitness_application_form($p->ID)).'</div></article>';
    }
    $html.='</div><p><a class="ea-button" href="'.esc_url(ea_core_page_url('pt-egitimleri')).'">Antrenör eşliğinde eğitim için PT paketleri →</a></p>';
    $offer=isset($_GET['fitness_offer'])&&is_scalar($_GET['fitness_offer'])?absint($_GET['fitness_offer']):0;
    $renewal=isset($_GET['renewal_of'])&&is_scalar($_GET['renewal_of'])?absint($_GET['renewal_of']):0;
    if($offer && ea_fitness_offer($offer) && 'publish'===get_post_status($offer)){
        $allowed=true;if($renewal){try{$m=ea_member_row('user_memberships',$renewal);$allowed=(int)$m['user_id']===get_current_user_id()&&(int)$m['offer_id']===$offer&&ea_fitness_row($m);}catch(Throwable $e){$allowed=false;}}
        if($allowed)$html.='<div class="ea-card ea-fitness-apply" id="ea-fitness-apply"><h3>'.esc_html(get_the_title($offer)).($renewal?' — Yenileme':' — Başvuru').'</h3>'.ea_fitness_application_form($offer,$renewal).'</div>';
    }
    return $html.'</section>';
}
// Keep existing catalog and application data. Extend their existing shortcodes without rewriting page content.
add_filter('do_shortcode_tag',function($output,$tag,$attr){
    if('ea_list'===$tag && ($attr['type']??'')==='ea_membership')return ea_fitness_catalog().'<section class="ea-legacy-memberships"><h2>Diğer üyelik seçenekleri</h2>'.$output.'</section>';
    if('ea_membership_form'===$tag)return '<h2>Diğer üyelikler için bilgi talebi</h2>'.$output;
    return $output;
},10,3);
add_action('template_redirect',function(){if(is_page('uyelik'))ea_account_no_cache();},1);
add_action('wp_enqueue_scripts',function(){
    if(is_page('uyelik')||ea_account_context())wp_enqueue_style('ea-fitness',EA_CORE_URL.'assets/fitness-membership.css',array('ea-core'),(string)filemtime(EA_CORE_DIR.'assets/fitness-membership.css'));
},120);

add_action('admin_post_ea_fitness_manage',function(){
    if(!current_user_can('manage_options'))wp_die('Yetkisiz işlem.',403);
    check_admin_referer('ea_fitness_manage');
    foreach(array('operation','user_id','offer_id','application_id','request_key','hours','payment_verified')as$f)if(isset($_POST[$f])&&!is_scalar($_POST[$f]))wp_die('Geçersiz alan.',400);
    $op=sanitize_key($_POST['operation']??'');
    if('hours'===$op){try{$hours=sanitize_textarea_field(wp_unslash($_POST['hours']??''));ea_fitness_parse_hours($hours);update_option('ea_fitness_hours',$hours,false);$result=true;}catch(Throwable $e){$result=new WP_Error('hours',$e->getMessage());}}
    elseif('activate'===$op){
        if(function_exists('ea_sales_ready') && ea_sales_ready() && ($_POST['payment_verified']??'')!=='1')$result=new WP_Error('payment','Aktivasyondan önce ödemeyi manuel doğrulayın.');
        else $result=ea_fitness_activate(absint($_POST['user_id']??0),absint($_POST['offer_id']??0),sanitize_text_field(wp_unslash($_POST['request_key']??'')),absint($_POST['application_id']??0),true);
    }
    else $result=new WP_Error('operation','Geçersiz işlem.');
    set_transient('ea_member_notice_'.get_current_user_id(),array('error'=>is_wp_error($result),'message'=>is_wp_error($result)?$result->get_error_message():'Fitness işlemi tamamlandı.'),60);
    wp_safe_redirect(admin_url('admin.php?page=ea-members'));exit;
});
function ea_fitness_admin_form($operation){
    echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="ea_fitness_manage"><input type="hidden" name="operation" value="'.esc_attr($operation).'"><input type="hidden" name="request_key" value="'.esc_attr(wp_generate_uuid4()).'">';wp_nonce_field('ea_fitness_manage');
}
function ea_fitness_admin_section(){
    if(!current_user_can('manage_options')||!ea_fitness_ready())return;
    if(function_exists('ea_sales_admin_section'))ea_sales_admin_section();
    echo '<h2>Fitness üyeliği ata</h2><p>1 / 3 / 6 / 12 takvim ayı; sınırsız fitness alanı erişimi. Seans hakkı oluşturulmaz.</p>';
    ea_fitness_admin_form('activate');echo '<p><label>Kullanıcı <select name="user_id" required>';
    foreach(get_users(array('orderby'=>'display_name'))as$u)echo '<option value="'.esc_attr($u->ID).'">'.esc_html($u->display_name.' (#'.$u->ID.')').'</option>';
    echo '</select></label></p><p><label>Fitness paketi <select name="offer_id" required>';
    foreach(get_posts(array('post_type'=>'ea_membership','post_status'=>'publish','numberposts'=>-1,'meta_key'=>'_ea_offer_kind','meta_value'=>'fitness'))as$p)echo '<option value="'.esc_attr($p->ID).'">'.esc_html($p->post_title).'</option>';
    echo '</select></label></p>';if(function_exists('ea_sales_ready') && ea_sales_ready())echo '<p><label><input type="checkbox" name="payment_verified" value="1" required> Ödemeyi tesis kayıtlarından manuel doğruladım.</label></p>';submit_button('Fitness üyeliğini aktif et');echo '</form>';
    echo '<details><summary>Fitness çalışma saatleri</summary><p>Site saat dilimi: '.esc_html(wp_timezone_string()).'. Açılış dahil, kapanış hariç. Her aralık ayrı satır: mon 09:00-21:00. Günler: mon, tue, wed, thu, fri, sat, sun. Boş ayarda erişim kapalıdır; PT saatleri değişmez.</p>';
    ea_fitness_admin_form('hours');echo '<label for="ea-fitness-hours">Fitness çalışma saatleri</label><br><textarea id="ea-fitness-hours" name="hours" rows="8" cols="45">'.esc_textarea(get_option('ea_fitness_hours','')).'</textarea>';submit_button('Fitness saatlerini kaydet');echo '</form></details>';
    if(function_exists('ea_sales_ready') && ea_sales_ready())return;
    echo '<h2>Fitness başvuruları</h2><p>Onay yeni üyelik oluşturur. Yenileme, varsa aynı paketin aktif veya planlanmış son döneminin bitişinde başlar. Önceki üyelik değiştirilmez.</p>';
    global $wpdb;
    $apps=$wpdb->get_col("SELECT p.ID FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} f ON f.post_id=p.ID AND f.meta_key='_ea_fitness_application' AND f.meta_value='1' JOIN {$wpdb->postmeta} s ON s.post_id=p.ID AND s.meta_key='_ea_status' AND s.meta_value IN ('pending','contacted') WHERE p.post_type='ea_application' AND p.post_status='private' ORDER BY p.ID DESC LIMIT 100");
    foreach($apps as$id){$uid=(int)get_post_meta($id,'_ea_user_id',true);$offer=(int)get_post_meta($id,'_ea_offer_id',true);$u=get_userdata($uid);echo '<div class="card"><h3>'.esc_html('#'.$id.' — '.($u?$u->display_name:'Kullanıcı bulunamadı').' — '.get_the_title($offer)).'</h3><p>'.esc_html(get_post_meta($id,'_ea_renewal_of',true)?'Yenileme başvurusu':'Yeni başvuru').'</p>';ea_fitness_admin_form('activate');echo '<input type="hidden" name="user_id" value="'.esc_attr($uid).'"><input type="hidden" name="offer_id" value="'.esc_attr($offer).'"><input type="hidden" name="application_id" value="'.esc_attr($id).'">';submit_button('İncelendi: onayla ve üyeliği ata','secondary');echo '</form><a href="'.esc_url(get_edit_post_link($id)).'">Başvuruyu incele / reddet</a></div>';}
}
function ea_fitness_admin_card($m){
    $s=json_decode($m['snapshot'],true);$u=get_userdata($m['user_id']);$v=ea_fitness_metrics($m);
    $labels=array('active'=>'Aktif','scheduled'=>'Henüz başlamadı','suspended'=>'Askıda','cancelled'=>'İptal edilmiş','expired'=>'Süresi dolmuş');
    echo '<section style="background:#fff;padding:16px;margin:16px 0;border-left:4px solid #c3ca25"><h3>'.esc_html('Fitness #'.$m['id'].' / '.($u?$u->display_name:'Silinmiş kullanıcı').' / '.($s['title']??'Fitness')).'</h3><p>'.esc_html($labels[$v['status']]??$v['status']).' — '.esc_html(get_date_from_gmt($m['starts_at'],'d.m.Y H:i').' – '.get_date_from_gmt($m['ends_at'],'d.m.Y H:i')).'</p><p>Fitness Alanı Erişimi: Sınırsız · '.esc_html($v['remaining_days']).' kalan gün</p>';
    ea_member_admin_form_start('status');echo '<input type="hidden" name="membership_id" value="'.esc_attr($m['id']).'"><label for="ea-fitness-status-'.esc_attr($m['id']).'">Fitness üyelik durumu</label> <select id="ea-fitness-status-'.esc_attr($m['id']).'" name="status"><option value="active">Aktif (süre uzatılmaz)</option><option value="suspended">Askıda</option><option value="cancelled">İptal edilmiş</option></select> <label for="ea-fitness-status-reason-'.esc_attr($m['id']).'">Durum değişikliği gerekçesi</label> <input id="ea-fitness-status-reason-'.esc_attr($m['id']).'" name="reason" required maxlength="1000" placeholder="Gerekçe">';submit_button('Durumu kaydet','secondary','submit',false);echo '</form><details><summary>Üyelik hareketleri</summary><table class="widefat"><thead><tr><th>Tarih</th><th>İşlem</th><th>Yetkili</th><th>Gerekçe</th></tr></thead><tbody>';
    $events=ea_membership_history($m['id']);
    if(!is_wp_error($events))foreach($events as$e)echo '<tr><td>'.esc_html(get_date_from_gmt($e['created_at'],'d.m.Y H:i:s')).'</td><td>'.esc_html($e['event_type']).'</td><td>'.esc_html($e['actor_id']).'</td><td>'.esc_html($e['reason']).'</td></tr>';
    echo '</tbody></table></details></section>';
}
