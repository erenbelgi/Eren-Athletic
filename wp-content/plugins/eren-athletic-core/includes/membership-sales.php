<?php
/** Stage 5: catalog pricing, immutable application quotes and explicit administrator review. */
if (!defined('ABSPATH')) exit;
define('EA_SALES_VERSION','1.0.0');
function ea_sales_ready(){return get_option('ea_sales_version')===EA_SALES_VERSION;}
function ea_sales_decimal($value,$allow_blank=false){
    ea_member_require(is_scalar($value),'Fiyat sayısal olmalı.');$value=trim((string)$value);
    if($allow_blank && ''===$value)return '';
    ea_member_require((bool)preg_match('/^\d{1,9}(?:\.\d{1,2})?$/D',$value),'Fiyatı TL cinsinden, binlik ayırıcı olmadan girin; örnek: 2400.00.');
    $parts=explode('.',$value);$cents=(int)$parts[0]*100+(int)str_pad($parts[1]??'',2,'0');
    ea_member_require($cents>0 && $cents<=99999999999,'Fiyat sıfırdan büyük olmalı.');
    return intdiv($cents,100).'.'.str_pad((string)($cents%100),2,'0',STR_PAD_LEFT);
}
function ea_sales_cents($value){$v=ea_sales_decimal($value);return (int)str_replace('.','',$v);}
function ea_sales_money($value,$decimals=0){
    try{$cents=ea_sales_cents($value);return number_format($cents/100,$decimals,',','.').' TL';}
    catch(Throwable $e){return 'Fiyat bilgisi için iletişime geçin';}
}
function ea_sales_price_label($value){
    try{$c=ea_sales_cents($value);return ea_sales_money($value,$c%100?2:0);}catch(Throwable $e){return 'Fiyat bilgisi için iletişime geçin';}
}
function ea_sales_average($price,$months){
    ea_member_require(in_array((int)$months,array(1,3,6,12),true),'Süre geçersiz.');
    $cents=(int)round(ea_sales_cents($price)/(int)$months,0,PHP_ROUND_HALF_UP);
    return intdiv($cents,100).'.'.str_pad((string)($cents%100),2,'0',STR_PAD_LEFT);
}
function ea_sales_offers(){
    return get_posts(array('post_type'=>'ea_membership','post_status'=>'publish','numberposts'=>-1,'meta_key'=>'_ea_offer_kind','meta_value'=>'fitness','orderby'=>'ID','order'=>'ASC'));
}
/** Existing records only. One-time numeric TRY price migration never rewrites member snapshots. */
function ea_sales_migrate(){
    global $wpdb;
    try{ea_member_admin();}catch(Throwable $e){return new WP_Error('forbidden',$e->getMessage());}
    if(ea_sales_ready())return true;
    if(!ea_fitness_ready())return new WP_Error('schema','Fitness altyapısı hazır değil.');
    $lock='ea_sales_schema_'.md5(DB_NAME.$wpdb->prefix);
    if(1!==(int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,10)',$lock)))return new WP_Error('migration','Fiyatlandırma kurulumu kilitlenemedi.');
    $ids=array();
    try{
        if(ea_sales_ready())return true;
        $table=ea_member_table('application_events');$charset=$wpdb->get_charset_collate();
        ea_member_sql("CREATE TABLE IF NOT EXISTS `$table` (id bigint unsigned NOT NULL AUTO_INCREMENT, application_id bigint unsigned NOT NULL, event_type varchar(32) NOT NULL, actor_id bigint unsigned NOT NULL, created_at datetime NOT NULL, reason text NOT NULL, details longtext NOT NULL, operation_key char(64) NOT NULL, PRIMARY KEY(id), UNIQUE KEY operation_key(operation_key), KEY application_history(application_id,id)) ENGINE=InnoDB $charset");
        $result=ea_member_transaction(function()use($wpdb,&$ids){
            $offers=ea_sales_offers();$by_month=array();
            foreach($offers as$p){$m=(int)get_post_meta($p->ID,'_ea_calendar_months',true);ea_member_require(!isset($by_month[$m]),'Aynı sürede mükerrer fitness teklifi var.');$by_month[$m]=$p->ID;}
            foreach(array(1=>'2400.00',3=>'6500.00',6=>'13500.00',12=>'25000.00')as$months=>$price){
                ea_member_require(isset($by_month[$months]),'Mevcut fitness paketi bulunamadı; yeni kayıt oluşturulmadı.');$id=$by_month[$months];$ids[]=$id;
                ea_member_sql($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE ID=%d FOR UPDATE",$id));
                foreach(array('_ea_price'=>$price,'_ea_currency'=>'TRY')as$key=>$value){
                    $count=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id=%d AND meta_key=%s",$id,$key));ea_member_require($count<=1,'Paket fiyat alanı mükerrer.');
                    if($count)ea_member_sql($wpdb->prepare("UPDATE {$wpdb->postmeta} SET meta_value=%s WHERE post_id=%d AND meta_key=%s",$value,$id,$key));
                    else ea_member_require(false!==$wpdb->insert($wpdb->postmeta,array('post_id'=>$id,'meta_key'=>$key,'meta_value'=>$value)),'Fiyat yazılamadı.');
                }
            }
            ea_member_sql($wpdb->prepare("INSERT INTO {$wpdb->options} (option_name,option_value,autoload) VALUES (%s,%s,'off') ON DUPLICATE KEY UPDATE option_value=VALUES(option_value)",'ea_sales_version',EA_SALES_VERSION));return true;
        });
        foreach($ids as$id)clean_post_cache($id);wp_cache_delete('ea_sales_version','options');wp_cache_delete('notoptions','options');return $result;
    }catch(Throwable $e){return new WP_Error('migration',$e->getMessage());}
    finally{$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock));}
}
add_action('admin_init',function(){if(!current_user_can('manage_options'))return;$r=ea_sales_migrate();if(is_wp_error($r))add_action('admin_notices',function()use($r){echo '<div class="notice notice-error"><p>'.esc_html($r->get_error_message()).'</p></div>';});},25);

function ea_sales_quote_token($offer_id,$renewal=0){
    $offer=ea_fitness_snapshot($offer_id);
    return hash_hmac('sha256',wp_json_encode(array(get_current_user_id(),wp_get_session_token(),(int)$offer_id,(int)$renewal,$offer['calendar_months'],$offer['price'])),wp_salt('nonce'));
}
function ea_sales_quote($offer_id){
    $offer=ea_fitness_snapshot($offer_id);$price=ea_sales_decimal($offer['price']);
    ea_member_require(get_post_meta($offer_id,'_ea_currency',true)==='TRY','Paket para birimi geçersiz.');
    return array('version'=>1,'offer_id'=>(int)$offer_id,'offer_type'=>'ea_membership','title'=>$offer['title'],'total_price'=>$price,'currency'=>'TRY','calendar_months'=>$offer['calendar_months'],'access_scope'=>'fitness_area','access_policy'=>'unlimited_during_validity','includes_pt'=>false,'includes_court'=>false,'quoted_at'=>ea_member_now());
}
function ea_sales_application($id){return 'ea_application'===get_post_type($id) && ''!==get_post_meta($id,'_ea_application_snapshot',true);}
function ea_sales_application_snapshot($id){
    $s=json_decode(get_post_meta($id,'_ea_application_snapshot',true),true);return is_array($s)?$s:null;
}
function ea_sales_event($id,$type,$reason,$details,$key){
    return ea_member_insert('application_events',array('application_id'=>$id,'event_type'=>$type,'actor_id'=>get_current_user_id(),'created_at'=>ea_member_now(),'reason'=>sanitize_textarea_field($reason),'details'=>wp_json_encode($details),'operation_key'=>$key));
}
function ea_sales_activation_check($id,$meta,$offer,$payment_verified){
    if(!ea_sales_ready())return;
    ea_member_require(true===$payment_verified,'Üyeliği aktifleştirmeden önce ödemeyi tesis kayıtlarından manuel doğrulayın.');
    if(empty($meta['_ea_application_snapshot']))return;
    $quote=json_decode($meta['_ea_application_snapshot'],true);
    ea_member_require(is_array($quote) && (int)$quote['offer_id']===(int)$offer['offer_id'] && $quote['currency']==='TRY' && $quote['access_scope']==='fitness_area' && $quote['access_policy']==='unlimited_during_validity' && (int)$quote['calendar_months']===(int)$offer['calendar_months'],'Başvuru kapsamı veya süresi katalogla uyuşmuyor.');
    ea_sales_decimal($quote['total_price']);ea_sales_quote($offer['offer_id']);
}
function ea_sales_activation_record($id,$membership_id,$meta,$offer,$key,$payment_verified){
    if(!ea_sales_ready() || !$payment_verified)return;
    $quote=isset($meta['_ea_application_snapshot'])?json_decode($meta['_ea_application_snapshot'],true):null;
    $verification=array('status'=>'manually_verified','actor_id'=>get_current_user_id(),'verified_at'=>ea_member_now());
    ea_booking_write_meta($id,array('_ea_payment_verification'=>wp_json_encode($verification)));
    ea_sales_event($id,'approved','Yönetici ödemeyi manuel doğrulayarak üyeliği aktifleştirdi.',array('membership_id'=>$membership_id,'quoted_price'=>$quote['total_price']??null,'catalog_price'=>$offer['price'],'currency'=>'TRY','payment_verification'=>$verification),hash('sha256','sales_approve|'.$key));
}
/** Reject/leave pending under the same user -> application lock order as activation. */
function ea_sales_review($id,$status,$reason,$request_key){
    $result=ea_member_transaction(function()use($id,$status,$reason,$request_key){
        global $wpdb;ea_member_admin();ea_member_require(ea_sales_ready(),'Başvuru yönetimi hazır değil.');ea_member_require(in_array($status,array('pending','rejected'),true),'Geçersiz inceleme işlemi.');
        ea_member_require('ea_application'===get_post_type($id) && get_post_meta($id,'_ea_fitness_application',true)==='1','Fitness başvurusu bulunamadı.');
        $uid=(int)get_post_meta($id,'_ea_user_id',true);ea_member_require($uid>0,'Başvuru hesabı doğrulanmamış.');
        ea_member_sql($wpdb->prepare("SELECT ID FROM {$wpdb->users} WHERE ID=%d FOR UPDATE",$uid));ea_member_sql($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE ID=%d FOR UPDATE",$id));
        $meta=ea_booking_meta($id);$key=ea_member_key('sales_review',$request_key);$reason=sanitize_textarea_field($reason);ea_member_require(''!==trim($reason),'İnceleme gerekçesi gerekir.');
        $old=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.ea_member_table('application_events').' WHERE operation_key=%s',$key),ARRAY_A);
        if($old){ea_member_require((int)$old['application_id']===(int)$id && $old['event_type']===$status && $old['reason']===$reason,'İşlem anahtarı farklı karara ait.');return true;}
        ea_member_require((int)($meta['_ea_user_id']??0)===$uid && 'trash'!==get_post_status($id),'Başvuru hesabı tutarsız.');
        ea_member_require(!$wpdb->get_var($wpdb->prepare('SELECT id FROM '.ea_member_table('user_memberships').' WHERE application_id=%d',$id)),'Aktifleştirilmiş başvurunun kararını değiştiremezsiniz.');
        ea_member_require(in_array($meta['_ea_status']??'',array('pending','contacted'),true),'Kararı verilmiş başvuru yeniden değiştirilemez.');
        ea_booking_write_meta($id,array('_ea_status'=>$status));ea_sales_event($id,$status,$reason,array('previous_status'=>$meta['_ea_status']),$key);return true;
    });clean_post_cache($id);return $result;
}
function ea_sales_legacy_review($id,$values){
    if(!is_array($values)||!isset($values['status'])||!is_scalar($values['status']))return new WP_Error('review','Başvuru Yönetimi bölümünü kullanın.');
    $status=sanitize_key($values['status']);$existing=get_post_meta($id,'_ea_status',true);
    if($status===$existing)return true;
    if(!in_array($status,array('pending','rejected'),true))return new WP_Error('review','Aktivasyon için Üye Yönetimi → Başvurular bölümünde ödemeyi manuel doğrulayarak onaylayın.');
    return ea_sales_review($id,$status,'Başvuru düzenleyicisinden yönetici incelemesi',wp_generate_uuid4());
}
// Historical quote, account, scope and payment state cannot be edited through generic metadata APIs.
foreach(array('add_post_metadata','update_post_metadata','delete_post_metadata')as$hook)add_filter($hook,function($check,$id,$key){
    if(ea_sales_application($id) && str_starts_with((string)$key,'_ea_'))return false;return $check;
},10,3);
add_filter('pre_delete_post',function($delete,$post){return ea_sales_application($post->ID)?false:$delete;},10,2);

function ea_sales_return_url($offer_id,$renewal=0){return add_query_arg(array('fitness_offer'=>(int)$offer_id,'renewal_of'=>(int)$renewal),ea_core_page_url('uyelik')).'#ea-fitness-apply';}
function ea_sales_auth_url($slug,$offer_id,$renewal=0){return add_query_arg(array('fitness_offer'=>(int)$offer_id,'renewal_of'=>(int)$renewal),ea_core_page_url($slug));}
function ea_sales_auth_selection(){
    $id=isset($_REQUEST['fitness_offer'])&&is_scalar($_REQUEST['fitness_offer'])?absint($_REQUEST['fitness_offer']):0;
    $renewal=isset($_REQUEST['renewal_of'])&&is_scalar($_REQUEST['renewal_of'])?absint($_REQUEST['renewal_of']):0;
    if(!$id || !ea_fitness_offer($id)||'publish'!==get_post_status($id))return array(0,0);return array($id,$renewal);
}
function ea_sales_auth_destination($slug){
    list($id,$renewal)=ea_sales_auth_selection();return apply_filters('ea_account_destination',$id?ea_sales_auth_url($slug,$id,$renewal):ea_core_page_url($slug),$slug);
}
function ea_sales_after_login(){list($id,$renewal)=ea_sales_auth_selection();return apply_filters('ea_account_after_login',$id?ea_sales_return_url($id,$renewal):ea_core_page_url('uye-panelim'));}
function ea_sales_auth_fields(){
    list($id,$renewal)=ea_sales_auth_selection();$html=$id?'<input type="hidden" name="fitness_offer" value="'.esc_attr($id).'"><input type="hidden" name="renewal_of" value="'.esc_attr($renewal).'">':'';return apply_filters('ea_account_context_fields',$html);
}

function ea_sales_application_row($app){
    $id=$app->ID;$snapshot=ea_sales_application_snapshot($id);$status=get_post_meta($id,'_ea_status',true);$labels=array('pending'=>'İnceleniyor','contacted'=>'İnceleniyor','accepted'=>'Onaylandı','rejected'=>'Reddedildi');
    $offer=(int)(get_post_meta($id,'_ea_offer_id',true)?:get_post_meta($id,'_ea_membership',true));
    $title=$snapshot['title']??($offer?get_the_title($offer):'Üyelik başvurusu');$price=$snapshot?ea_sales_price_label($snapshot['total_price']):'Başvuru anında fiyat kaydedilmemiş';
    return '<article class="ea-account-history-row ea-sales-history"><div><strong>'.esc_html($title).'</strong><small>'.(get_post_meta($id,'_ea_renewal_of',true)?'Yenileme başvurusu':'Üyelik başvurusu').'</small></div><span>'.esc_html(get_date_from_gmt($app->post_date_gmt,'d.m.Y H:i')).'</span><span>'.esc_html($price).'</span><span class="ea-account-badge" data-status="'.esc_attr($status).'">'.esc_html($labels[$status]??'İnceleniyor').'</span></article>';
}
function ea_sales_admin_section(){
    if(!current_user_can('manage_options')||!ea_sales_ready())return;
    echo '<h2>Fitness fiyatları (TL)</h2><p>Toplam paket fiyatı. Nokta ondalık ayırıcıdır; binlik ayırıcı kullanmayın. Eski üyelikler ve başvuru fiyatları değişmez.</p>';
    ea_sales_admin_form('prices');foreach(ea_sales_offers()as$p)echo '<p><label>'.esc_html($p->post_title).' <input type="number" name="prices['.esc_attr($p->ID).']" min="0.01" max="999999999.99" step="0.01" required value="'.esc_attr(get_post_meta($p->ID,'_ea_price',true)).'"> TL</label></p>';submit_button('Katalog fiyatlarını kaydet');echo '</form><h2>Üyelik başvuruları ve yenilemeler</h2><p>Onay, ödemeyi tesis kayıtlarından manuel doğrulayan yöneticinin açık aktivasyon işlemidir. Online ödeme alınmaz. Eski başvuruda tarihsel fiyat yoksa fiyat uydurulmaz.</p>';
    global $wpdb;$page=isset($_GET['sales_page'])&&is_scalar($_GET['sales_page'])?max(1,absint($_GET['sales_page'])):1;
    $apps=$wpdb->get_col($wpdb->prepare("SELECT p.ID FROM {$wpdb->posts} p WHERE p.post_type='ea_application' AND p.post_status<>'trash' AND EXISTS(SELECT 1 FROM {$wpdb->postmeta} f WHERE f.post_id=p.ID AND f.meta_key='_ea_fitness_application' AND f.meta_value='1') ORDER BY p.ID DESC LIMIT 21 OFFSET %d",($page-1)*20));
    foreach(array_slice($apps,0,20)as$id){
        $meta=ea_booking_meta($id);$uid=(int)($meta['_ea_user_id']??0);$offer=(int)($meta['_ea_offer_id']??0);$u=get_userdata($uid);$s=ea_sales_application_snapshot($id);$labels=array('pending'=>'İnceleniyor','contacted'=>'İnceleniyor','accepted'=>'Onaylandı','rejected'=>'Reddedildi');
        echo '<section class="card" style="max-width:900px"><h3>'.esc_html('#'.$id.' — '.($s['title']??get_the_title($offer))).'</h3><p>Hesap: <a href="'.esc_url(get_edit_user_link($uid)).'">'.esc_html(($u?$u->display_name:'Bilinmeyen kullanıcı').' (#'.$uid.')').'</a></p><p>Başvuru: '.esc_html(get_date_from_gmt(get_post_field('post_date_gmt',$id),'d.m.Y H:i')).' · '.esc_html($labels[$meta['_ea_status']??'']??'İnceleniyor').'</p><p>Başvuru anındaki fiyat: <strong>'.esc_html($s?ea_sales_price_label($s['total_price']):'Kaydedilmemiş').'</strong><br>Güncel katalog fiyatı: <strong>'.esc_html(ea_sales_price_label(get_post_meta($offer,'_ea_price',true))).'</strong></p>';
        if(!empty($meta['_ea_renewal_of']))echo '<p>Yenilenen üyelik: #'.esc_html($meta['_ea_renewal_of']).'. Onay yeni dönem oluşturur, eski dönem değişmez.</p>';
        if(in_array($meta['_ea_status']??'',array('pending','contacted'),true)){
            ea_sales_admin_form('approve');echo '<input type="hidden" name="application_id" value="'.esc_attr($id).'"><p><label><input type="checkbox" name="payment_verified" value="1" required> Ödemeyi tesis kayıtlarından manuel doğruladım; bu başvurunun üyeliğini aktifleştiriyorum.</label></p>';submit_button('Onayla ve üyeliği aktifleştir','primary');echo '</form>';
            ea_sales_admin_form('review');echo '<input type="hidden" name="application_id" value="'.esc_attr($id).'"><label for="ea-sales-review-status-'.esc_attr($id).'">Başvuru kararı</label> <select id="ea-sales-review-status-'.esc_attr($id).'" name="status"><option value="pending">İnceleniyor olarak bırak</option><option value="rejected">Reddet</option></select> <label for="ea-sales-review-reason-'.esc_attr($id).'">İnceleme gerekçesi</label> <input id="ea-sales-review-reason-'.esc_attr($id).'" name="reason" required maxlength="1000" placeholder="İnceleme gerekçesi">';submit_button('Kararı kaydet','secondary');echo '</form>';
        }
        $events=$wpdb->get_results($wpdb->prepare('SELECT * FROM '.ea_member_table('application_events').' WHERE application_id=%d ORDER BY id DESC LIMIT 50',$id),ARRAY_A);
        echo '<details><summary>Başvuru işlem geçmişi</summary><ul>';foreach($events as$e)echo '<li>'.esc_html(get_date_from_gmt($e['created_at'],'d.m.Y H:i:s').' / '.$e['event_type'].' / Yönetici #'.$e['actor_id'].' / '.$e['reason']).'</li>';echo '</ul></details></section>';
    }
    if($page>1)echo '<a class="button" href="'.esc_url(add_query_arg('sales_page',$page-1)).'">Önceki başvurular</a> ';if(count($apps)>20)echo '<a class="button" href="'.esc_url(add_query_arg('sales_page',$page+1)).'">Sonraki başvurular</a>';
}
function ea_sales_admin_form($op){echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="ea_sales_manage"><input type="hidden" name="operation" value="'.esc_attr($op).'"><input type="hidden" name="request_key" value="'.esc_attr(wp_generate_uuid4()).'">';wp_nonce_field('ea_sales_manage');}
function ea_sales_set_prices($prices){
    $result=ea_member_transaction(function()use($prices){
        global $wpdb;ea_member_admin();ea_member_require(is_array($prices)&&count($prices)===4,'Dört paket fiyatı gerekir.');$valid=array_column(ea_sales_offers(),'ID');
        foreach($prices as$id=>$price){$id=(int)$id;ea_member_require(in_array($id,array_map('intval',$valid),true),'Fitness paketi geçersiz.');$price=ea_sales_decimal($price);ea_member_sql($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE ID=%d FOR UPDATE",$id));ea_member_require(1===(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id=%d AND meta_key='_ea_price'",$id)),'Fiyat alanı tutarsız.');ea_member_sql($wpdb->prepare("UPDATE {$wpdb->postmeta} SET meta_value=%s WHERE post_id=%d AND meta_key='_ea_price'",$price,$id));}return true;
    });if(is_array($prices))foreach(array_keys($prices)as$id)clean_post_cache((int)$id);return $result;
}
add_action('admin_post_ea_sales_manage',function(){
    if(!current_user_can('manage_options'))wp_die('Yetkisiz işlem.',403);check_admin_referer('ea_sales_manage');
    foreach(array('operation','application_id','request_key','status','reason','payment_verified')as$f)if(isset($_POST[$f])&&!is_scalar($_POST[$f]))wp_die('Geçersiz alan.',400);
    $op=sanitize_key($_POST['operation']??'');$id=absint($_POST['application_id']??0);$key=sanitize_text_field(wp_unslash($_POST['request_key']??''));
    if('prices'===$op)$result=ea_sales_set_prices(wp_unslash($_POST['prices']??array()));
    elseif('review'===$op)$result=ea_sales_review($id,sanitize_key($_POST['status']??''),sanitize_textarea_field(wp_unslash($_POST['reason']??'')),$key);
    elseif('approve'===$op){
        if(($_POST['payment_verified']??'')!=='1')$result=new WP_Error('payment','Ödemeyi tesis kayıtlarından manuel doğrulamalısınız.');
        else{$uid=(int)get_post_meta($id,'_ea_user_id',true);$offer=(int)get_post_meta($id,'_ea_offer_id',true);$result=ea_fitness_activate($uid,$offer,$key,$id,true);}
    }else $result=new WP_Error('operation','Geçersiz işlem.');
    set_transient('ea_member_notice_'.get_current_user_id(),array('error'=>is_wp_error($result),'message'=>is_wp_error($result)?$result->get_error_message():'İşlem kaydedildi.'),60);wp_safe_redirect(admin_url('admin.php?page=ea-members'));exit;
});
add_action('add_meta_boxes_ea_application',function(){add_meta_box('ea-sales-review','Üyelik başvurusu: fiyat ve inceleme',function($post){
    if(!ea_sales_application($post->ID)){echo '<p>Bu eski başvuruda fiyat özeti kaydedilmemiş.</p>';return;}$s=ea_sales_application_snapshot($post->ID);
    echo '<p>Başvuru fiyatı: <strong>'.esc_html(ea_sales_price_label($s['total_price'])).'</strong><br>Güncel fiyat: '.esc_html(ea_sales_price_label(get_post_meta($s['offer_id'],'_ea_price',true))).'</p><p>Onay, ret ve ödeme doğrulaması için <a href="'.esc_url(admin_url('admin.php?page=ea-members')).'">Üye Yönetimi → Başvurular</a> bölümünü kullanın.</p>';
});});

add_filter('template_include',function($template){return is_page('uyelik')?EA_CORE_DIR.'includes/membership-sales-template.php':$template;},120);
add_filter('body_class',function($classes){if(is_page('uyelik'))$classes[]='ea-sales-page';return $classes;});
add_action('wp_enqueue_scripts',function(){if(is_page('uyelik')||ea_account_context())wp_enqueue_style('ea-membership-sales',EA_CORE_URL.'assets/membership-sales.css',array('ea-core'),(string)filemtime(EA_CORE_DIR.'assets/membership-sales.css'));},130);

function ea_sales_page(){
    ob_start();include EA_CORE_DIR.'includes/membership-sales-content.php';return ob_get_clean();
}
function ea_sales_hero_image(){
    $uploads=wp_get_upload_dir();$relative='/2026/10/eren-athletic-battle-rope.webp';
    return is_file($uploads['basedir'].$relative)?$uploads['baseurl'].$relative:'';
}
function ea_sales_application_form($offer_id,$renewal=0){
    $offer=ea_fitness_snapshot($offer_id);$price=ea_sales_price_label($offer['price']);
    $html='<div class="ea-sales-confirm"><p class="ea-sales-kicker">'.($renewal?'YENİLEME BAŞVURUSU':'SEÇTİĞİN PAKET').'</p><h3>'.esc_html($offer['title']).'</h3><p class="ea-sales-confirm-price">'.esc_html($price).' <span>/ '.esc_html($offer['calendar_months']).' takvim ayı</span></p><p>Sınırsız fitness alanı erişimi · PT ve saha kiralama dahil değildir.</p>';
    if(!is_user_logged_in())return $html.'<p>Başvurunu hesabına bağlamak için giriş yap veya hesap oluştur.</p><div class="ea-sales-actions"><a class="ea-button" href="'.esc_url(ea_sales_auth_url('giris-yap',$offer_id,$renewal)).'">Giriş Yap</a><a class="ea-sales-secondary" href="'.esc_url(ea_sales_auth_url('kayit-ol',$offer_id,$renewal)).'">Kayıt Ol ↗</a></div></div>';
    try{ea_sales_quote($offer_id);}catch(Throwable $e){return $html.'<p>Bu paket için fiyat bilgisi güncelleniyor. Lütfen tesisle iletişime geçin.</p></div>';}
    return $html.'<p>Başvuru hesabın: <strong>'.esc_html(wp_get_current_user()->user_email).'</strong></p><form class="ea-fitness-application" method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="ea_fitness_application"><input type="hidden" name="offer_id" value="'.esc_attr($offer_id).'"><input type="hidden" name="renewal_of" value="'.esc_attr($renewal).'"><input type="hidden" name="quote_token" value="'.esc_attr(ea_sales_quote_token($offer_id,$renewal)).'"><input type="hidden" name="request_key" value="'.esc_attr(wp_generate_uuid4()).'">'.wp_nonce_field('ea_fitness_application','ea_nonce',true,false).'<button class="ea-button" type="submit">'.($renewal?'Yenileme başvurusu gönder':'Başvurumu Onayla ve Gönder').' <span aria-hidden="true">↗</span></button><p>Başvurun yönetici incelemesine iletilir. Burada ödeme alınmaz ve üyelik otomatik başlamaz.</p></form></div>';
}
function ea_sales_fitness_catalog(){
    $html='<section class="ea-fitness-catalog ea-sales-section" id="fitness-planlari" aria-labelledby="ea-fitness-title"><div class="ea-sales-section-heading"><div><p class="ea-sales-kicker">01 / KENDİ PROGRAMIN, KENDİ TEMPON</p><h2 id="ea-fitness-title">Fitness üyelikleri<span>.</span></h2></div><p>Bir plan seç. Alanını aç.<br>Tanımlı fitness çalışma saatlerinde sınırsız antrenman.</p></div><div class="ea-sales-fitness-grid ea-grid">';
    foreach(ea_sales_offers()as$p){
        $months=(int)get_post_meta($p->ID,'_ea_calendar_months',true);$price=get_post_meta($p->ID,'_ea_price',true);$average='';try{$average=ea_sales_money(ea_sales_average($price,$months),2);}catch(Throwable $e){}
        $html.='<article class="ea-sales-plan ea-card ea-fitness-card'.($months===6?' ea-sales-plan-featured':'').'" data-offer="'.esc_attr($p->ID).'"><div class="ea-sales-plan-top"><span>FITNESS / '.esc_html($months).' TAKVİM AYI</span>'.($months===6?'<span class="ea-sales-plan-tag">6 Aylık Plan</span>':'<span aria-hidden="true">↗</span>').'</div><h3>'.esc_html($p->post_title).'</h3><p class="ea-sales-price">'.esc_html(ea_sales_price_label($price)).'</p><p class="ea-sales-total-label">Toplam paket fiyatı</p><p class="ea-sales-average">'.($average?'<strong>'.esc_html($average).'</strong> / ay ortalama':'Fiyat bilgisi için iletişime geçin').'</p><ul><li class="ea-sales-included">Sınırsız fitness alanı erişimi</li><li>Fitness çalışma saatlerinde kullanım</li><li>'.esc_html($months).' takvim ayı üyelik</li><li>PT eğitimi ve antrenör rezervasyonu dahil değildir</li><li>Basketbol / voleybol saha kiralama dahil değildir</li></ul><a class="ea-button" href="'.esc_url(ea_sales_return_url($p->ID)).'" aria-label="'.esc_attr($p->post_title.' için Üyelik Başvurusu Yap').'">Üyelik Başvurusu Yap <span aria-hidden="true">↗</span></a></article>';
    }
    $html.='</div><p class="ea-sales-pricing-note">Aylık ortalama, toplam paket fiyatının ay sayısına bölünmesidir. Yalnızca karşılaştırma içindir; taksit veya aylık ödeme planı değildir.</p>';
    list($offer,$renewal)=ea_sales_auth_selection();
    if($offer){
        $allowed=true;if($renewal){try{$m=ea_member_row('user_memberships',$renewal);$allowed=(int)$m['user_id']===get_current_user_id()&&(int)$m['offer_id']===$offer&&ea_fitness_row($m);}catch(Throwable $e){$allowed=false;}}
        if($allowed)$html.='<section class="ea-fitness-apply" id="ea-fitness-apply" aria-label="Üyelik başvurusunu tamamla">'.ea_sales_application_form($offer,$renewal).'</section>';
        else $html.='<p class="ea-sales-pricing-note">Bu yenileme için üyeliğin sahibi olan hesapla giriş yapmalısın.</p>';
    }
    return $html.'</section>';
}
