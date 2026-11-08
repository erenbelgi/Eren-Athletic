<?php
/** Program entitlement remains authoritative; coaching adds assignment and shared resource occupancy. */
if(!defined('ABSPATH'))exit;
define('EA_COACHING_VERSION','1.0.0');
function ea_coach_ids($value){
    if(is_string($value))$value=json_decode($value,true);
    $ids=is_array($value)?array_values(array_unique(array_filter(array_map('absint',$value)))):array();sort($ids,SORT_NUMERIC);return $ids;
}
function ea_coach_trainers($program){
    if('ea_program'!==get_post_type($program)||'publish'!==get_post_status($program))return array();
    $ids=ea_coach_ids(get_post_meta($program,'_ea_trainer_ids',true));$primary=(int)get_post_meta($program,'_ea_trainer',true);if($primary)$ids[]=$primary;
    return array_values(array_filter(array_unique($ids),function($id){return 'ea_trainer'===get_post_type($id)&&'publish'===get_post_status($id);}));
}
function ea_coach_programs($trainer){return array_values(array_filter(ea_pt_posts('ea_program'),function($p)use($trainer){return in_array((int)$trainer,ea_coach_trainers($p->ID),true);}));}
function ea_coach_context($program,$trainer=0){
    if('ea_program'!==get_post_type($program))return array('trainer'=>0,'venues'=>array());
    $trainer=(int)$trainer?: (int)get_post_meta($program,'_ea_trainer',true);
    ea_member_require($trainer&&in_array($trainer,ea_coach_trainers($program),true),'Seçilen antrenör bu eğitime yönetici tarafından eşleştirilmemiş.');
    $venues=ea_coach_ids(get_post_meta($program,'_ea_required_venues',true));
    foreach($venues as$id)ea_member_require('ea_venue'===get_post_type($id)&&'publish'===get_post_status($id),'Eğitimin fiziksel kaynak eşleştirmesi geçersiz.');
    return array('trainer'=>$trainer,'venues'=>$venues);
}
function ea_coach_legacy_map(){
    $map=get_option('ea_coaching_legacy_map',null);if(is_array($map))return $map;
    $map=array();foreach(ea_pt_posts('ea_program')as$p)$map[$p->ID]=array('trainer'=>(int)get_post_meta($p->ID,'_ea_trainer',true),'venues'=>ea_coach_ids(get_post_meta($p->ID,'_ea_required_venues',true)));return $map;
}
function ea_coach_migrate(){
    try{ea_member_admin();}catch(Throwable $e){return new WP_Error('forbidden',$e->getMessage());}
    if(get_option('ea_coaching_version')===EA_COACHING_VERSION)return true;
    global $wpdb;$lock='ea_coach_schema_'.md5(DB_NAME.$wpdb->prefix);
    if(1!==(int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,10)',$lock)))return new WP_Error('migration','Antrenör kurulumu kilitlenemedi.');
    try{
        $map=ea_coach_legacy_map();
        $result=ea_member_transaction(function()use($map,$wpdb){
            foreach(array('ea_coaching_legacy_map'=>maybe_serialize($map),'ea_coaching_version'=>EA_COACHING_VERSION)as$key=>$value)ea_member_sql($wpdb->prepare("INSERT INTO {$wpdb->options} (option_name,option_value,autoload) VALUES (%s,%s,'off') ON DUPLICATE KEY UPDATE option_value=option_value",$key,$value));return true;
        });
        foreach(array('ea_coaching_legacy_map','ea_coaching_version','notoptions')as$key)wp_cache_delete($key,'options');return $result;
    }finally{$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock));}
}
add_action('admin_init',function(){if(!current_user_can('manage_options'))return;$r=ea_coach_migrate();if(is_wp_error($r))add_action('admin_notices',function()use($r){echo '<div class="notice notice-error"><p>'.esc_html($r->get_error_message()).'</p></div>';});},30);
function ea_coach_record_context($meta){
    $program=(int)($meta['_ea_resource']??0);$map=ea_coach_legacy_map();$legacy=$map[$program]??array('trainer'=>0,'venues'=>array());
    return array('trainer'=>isset($meta['_ea_trainer_id'])?(int)$meta['_ea_trainer_id']:(int)$legacy['trainer'],'venues'=>isset($meta['_ea_physical_resources'])?ea_coach_ids($meta['_ea_physical_resources']):ea_coach_ids($legacy['venues']));
}
function ea_coach_lock_names($program,$date,$context){
    $ids=array_merge(array((int)$program,(int)$context['trainer']),$context['venues']);$names=array();foreach(array_filter($ids)as$id)$names[]=ea_core_lock_name($id,$date);
    if('ea_program'===get_post_type($program))$names[]='ea_coach_cfg_'.md5($GLOBALS['wpdb']->prefix.'|'.$program);return $names;
}
/** Includes explicit coaching resources and conservative, frozen inference for legacy program bookings. */
function ea_coach_busy($id,$from,$to,$exclude=0){
    global $wpdb;$legacy=array();foreach(ea_coach_legacy_map()as$program=>$context)if((int)$context['trainer']===(int)$id||in_array((int)$id,ea_coach_ids($context['venues']),true))$legacy[]=(int)$program;
    $where="r.meta_value=%s OR EXISTS(SELECT 1 FROM {$wpdb->postmeta} tr WHERE tr.post_id=p.ID AND tr.meta_key='_ea_trainer_id' AND tr.meta_value=%s) OR EXISTS(SELECT 1 FROM {$wpdb->postmeta} ph WHERE ph.post_id=p.ID AND ph.meta_key='_ea_physical_resources' AND JSON_CONTAINS(IF(JSON_VALID(ph.meta_value),ph.meta_value,'[]'),%s))";
    if($legacy)$where.=" OR (CAST(r.meta_value AS UNSIGNED) IN (".implode(',',$legacy).") AND NOT EXISTS(SELECT 1 FROM {$wpdb->postmeta} explicit_tr WHERE explicit_tr.post_id=p.ID AND explicit_tr.meta_key='_ea_trainer_id'))";
    $rows=$wpdb->get_results($wpdb->prepare("SELECT DISTINCT d.meta_value day,s.meta_value start,e.meta_value end FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} r ON r.post_id=p.ID AND r.meta_key='_ea_resource' JOIN {$wpdb->postmeta} d ON d.post_id=p.ID AND d.meta_key='_ea_booking_date' JOIN {$wpdb->postmeta} s ON s.post_id=p.ID AND s.meta_key='_ea_start_time' JOIN {$wpdb->postmeta} e ON e.post_id=p.ID AND e.meta_key='_ea_end_time' JOIN {$wpdb->postmeta} st ON st.post_id=p.ID AND st.meta_key='_ea_status' WHERE p.post_type='ea_booking' AND p.post_status IN ('private','publish','draft','pending') AND st.meta_value IN ('pending','confirmed') AND p.ID<>%d AND d.meta_value BETWEEN %s AND %s AND ($where)",$exclude,$from,$to,(string)$id,(string)$id,wp_json_encode((int)$id)),ARRAY_A);
    return array_merge($rows,function_exists('ea_rental_busy')?ea_rental_busy($id,$from,$to):array());
}
/** Opening intervals, independent of a trainer/venue's default slot duration. */
function ea_coach_within_hours($id,$date,$start,$end){
    if(!ea_core_valid_date($date)||!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D',$start)||!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D',$end)||$start>=$end)return false;
    $aliases=array('mon'=>'mon','pzt'=>'mon','tue'=>'tue','sal'=>'tue','wed'=>'wed','car'=>'wed','çar'=>'wed','thu'=>'thu','per'=>'thu','fri'=>'fri','cum'=>'fri','sat'=>'sat','cmt'=>'sat','sun'=>'sun','paz'=>'sun');
    $day=strtolower((new DateTimeImmutable($date,wp_timezone()))->format('D'));
    foreach(preg_split('/\R/u',(string)get_post_meta($id,'_ea_hours',true))as$line)if(preg_match('/^\s*([a-zç]{3})\s+((?:[01]\d|2[0-3]):[0-5]\d)\s*-\s*((?:[01]\d|2[0-3]):[0-5]\d)\s*$/iu',$line,$m)){
        $name=function_exists('mb_strtolower')?mb_strtolower($m[1],'UTF-8'):strtolower($m[1]);if(($aliases[$name]??'')===$day&&$m[2]<=$start&&$end<=$m[3])return true;
    }return false;
}
function ea_coach_available($program,$date,$start,$end,$context,$exclude=0,$busy=null){
    $valid=false;foreach(ea_core_schedule($program,$date)as$slot)if($slot['start']===$start&&$slot['end']===$end)$valid=true;if(!$valid)return false;
    foreach(array_merge(array($program,$context['trainer']),$context['venues'])as$id){
        if(!$id)continue;if($id!==$program&&!ea_coach_within_hours($id,$date,$start,$end))return false;
        if(ea_calendar_overlaps(null===$busy?ea_coach_busy($id,$date,$date,$exclude):($busy[$id]??array()),$date,$start,$end))return false;
    }return true;
}
function ea_coach_assert_context($program,$context){
    clean_post_cache($program);ea_member_require(ea_coach_context($program,$context['trainer'])===$context,'Antrenör veya fiziksel kaynak eşleştirmesi değişti; tekrar seçin.');
}
function ea_coach_calendar_request(){
    $id=isset($_POST['resource'])&&is_scalar($_POST['resource'])?absint($_POST['resource']):0;if('ea_program'!==get_post_type($id))return false;
    try{
        ea_member_require(!isset($_POST['trainer_id'])||is_scalar($_POST['trainer_id']),'Geçersiz antrenör.');$context=ea_coach_context($id,absint($_POST['trainer_id']??0));
        $range=isset($_POST['date'])&&is_scalar($_POST['date'])?(string)$_POST['date']:((isset($_POST['month'])&&is_scalar($_POST['month']))?(string)$_POST['month'].'-01':'');
        ea_member_require(ea_core_valid_date($range),'Geçersiz tarih.');$last=(new DateTimeImmutable($range,wp_timezone()))->format('Y-m-t');
        $busy=array();foreach(array_filter(array_merge(array($id,$context['trainer']),$context['venues']))as$resource)$busy[$resource]=ea_coach_busy($resource,$range,$last);
        $day=function($date)use($id,$context,$busy){
            if($date<wp_date('Y-m-d'))return array('date'=>$date,'trainer_id'=>$context['trainer'],'status'=>'past','slots'=>array());
            if(!ea_calendar_configured($id))return array('date'=>$date,'trainer_id'=>$context['trainer'],'status'=>'undefined','slots'=>array());
            $slots=array();$free=0;foreach(ea_core_schedule($id,$date)as$slot){$slot['available']=ea_coach_available($id,$date,$slot['start'],$slot['end'],$context,0,$busy);if($slot['available'])$free++;$slots[]=$slot;}
            return array('date'=>$date,'trainer_id'=>$context['trainer'],'status'=>$date<wp_date('Y-m-d')?'past':(!$slots?'closed':(!$free?'full':($free===count($slots)?'available':'partial'))),'slots'=>$slots);
        };
        if(isset($_POST['date'])){ea_member_require(is_scalar($_POST['date'])&&ea_core_future_date((string)$_POST['date']),'Geçersiz tarih.');wp_send_json_success($day((string)$_POST['date']));}
        $month=isset($_POST['month'])&&is_scalar($_POST['month'])?(string)$_POST['month']:'';ea_member_require((bool)preg_match('/^(20\d{2})-(0[1-9]|1[0-2])$/D',$month),'Geçersiz ay.');
        $first=new DateTimeImmutable($month.'-01',wp_timezone());$days=array();for($date=$first;$date->format('Y-m')===$month;$date=$date->modify('+1 day')){$row=$day($date->format('Y-m-d'));unset($row['slots']);$days[]=$row;}
        wp_send_json_success(array('today'=>wp_date('Y-m-d'),'trainer_id'=>$context['trainer'],'days'=>$days));
    }catch(Throwable $e){wp_send_json_error(array('message'=>$e->getMessage()),400);}return true;
}

function ea_coach_set_mapping($program,$trainers,$venues,$verified,$primary=null){
    $locks=ea_calendar_lock(array('ea_coach_cfg_'.md5($GLOBALS['wpdb']->prefix.'|'.$program)));if(is_wp_error($locks))return $locks;
    clean_post_cache($program);
    try{return ea_member_transaction(function()use($program,$trainers,$venues,$verified,$primary){
        global $wpdb;ea_member_admin();ea_member_require('ea_program'===get_post_type($program),'Eğitim bulunamadı.');ea_member_require(is_array($trainers)&&is_array($venues),'Eşleştirme listesi geçersiz.');
        foreach(array_merge($trainers,$venues)as$v)ea_member_require(is_scalar($v)&&ctype_digit((string)$v),'Geçersiz eşleştirme kimliği.');
        $trainers=ea_coach_ids($trainers);$venues=ea_coach_ids($venues);$old_primary=(int)get_post_meta($program,'_ea_trainer',true);$primary=null===$primary?$old_primary:(int)$primary;
        if($primary&&!in_array($primary,$trainers,true))$trainers[]=$primary;$trainers=ea_coach_ids($trainers);
        foreach($trainers as$id)ea_member_require('ea_trainer'===get_post_type($id)&&'publish'===get_post_status($id),'Yayımlanmış antrenör seçin.');foreach($venues as$id)ea_member_require('ea_venue'===get_post_type($id)&&'publish'===get_post_status($id),'Yayımlanmış fiziksel kaynak seçin.');
        $before=array('primary'=>$old_primary,'trainers'=>ea_coach_ids(ea_coach_trainers($program)),'venues'=>ea_coach_ids(get_post_meta($program,'_ea_required_venues',true)));$after=array('primary'=>$primary,'trainers'=>$trainers,'venues'=>$venues);
        if($before===$after)return true;ea_member_require(true===$verified,'Antrenör yetkinliğini ve fiziksel kaynak eşleştirmesini yönetici olarak doğrulayın.');
        ea_member_sql($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE ID=%d FOR UPDATE",$program));
        ea_booking_write_meta($program,array('_ea_trainer'=>$primary,'_ea_trainer_ids'=>wp_json_encode($trainers),'_ea_required_venues'=>wp_json_encode($venues)));
        $log=json_decode(get_post_meta($program,'_ea_coaching_mapping_log',true),true)?:array();$log[]=array('actor'=>get_current_user_id(),'at'=>ea_member_now(),'from'=>$before,'to'=>$after);
        ea_booking_write_meta($program,array('_ea_coaching_mapping_log'=>wp_json_encode($log)));return true;
    });}finally{clean_post_cache($program);ea_calendar_unlock($locks);}
}
function ea_coach_program_admin_save($id,$values){
    if(!isset($_POST['ea_coach_nonce'])){
        if(isset($values['trainer'])&&(int)$values['trainer']!==(int)get_post_meta($id,'_ea_trainer',true))return new WP_Error('mapping','Antrenör ilişkisini eğitim eşleştirme kutusundan doğrulayın.');return true;
    }
    if(!current_user_can('manage_options')||!is_scalar($_POST['ea_coach_nonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ea_coach_nonce'])),'ea_coach_mapping_'.$id))return new WP_Error('nonce','Eğitim eşleştirmesi doğrulanamadı.');
    return ea_coach_set_mapping($id,wp_unslash($_POST['ea_coach_trainers']??array()),wp_unslash($_POST['ea_coach_venues']??array()),($_POST['ea_coach_verified']??'')==='1',isset($values['trainer'])&&is_scalar($values['trainer'])?absint($values['trainer']):null);
}
add_action('add_meta_boxes',function(){
    add_meta_box('ea-coach-mapping','Yetkili antrenörler ve gerekli fiziksel kaynaklar',function($post){
        if(!current_user_can('manage_options')){echo '<p>Eşleştirme için yönetici yetkisi gerekir.</p>';return;}wp_nonce_field('ea_coach_mapping_'.$post->ID,'ea_coach_nonce');
        echo '<p>Varsayılan antrenör mevcut Eren Athletic Bilgileri alanındadır. Ek antrenörü yalnızca eğitimi verebildiğini doğruladıysanız seçin. Mert Aydın otomatik eşleştirilmez.</p>';
        $ids=ea_coach_trainers($post->ID);foreach(ea_pt_posts('ea_trainer')as$p)echo '<p><label><input type="checkbox" name="ea_coach_trainers[]" value="'.esc_attr($p->ID).'" '.checked(in_array($p->ID,$ids,true),true,false).'> '.esc_html($p->post_title).'</label></p>';
        echo '<p>Bu eğitimde kesin olarak gereken fiziksel kaynakları seçin. Seçilen kaynakların tamamı aynı anda müsait olmalıdır. Boş seçim yalnızca program ve antrenör takvimini denetler; saha tahmini yapılmaz.</p>';$venues=ea_coach_ids(get_post_meta($post->ID,'_ea_required_venues',true));
        foreach(ea_pt_posts('ea_venue')as$p)echo '<p><label><input type="checkbox" name="ea_coach_venues[]" value="'.esc_attr($p->ID).'" '.checked(in_array($p->ID,$venues,true),true,false).'> '.esc_html($p->post_title).'</label></p>';
        echo '<p><label><input type="checkbox" name="ea_coach_verified" value="1"> Değiştirdiğim yetkinlik ve kaynak eşleştirmelerini yönetici olarak doğruladım.</label></p><p>Geçmiş rezervasyonlara otomatik veri yazılmaz. Eşleştirme değişiklikleri denetim geçmişine kaydedilir.</p>';
    },'ea_program','normal','high');
    add_meta_box('ea-coach-truth','Profil içeriği kontrolü',function($post){
        echo '<p>Hakkında / özgeçmiş için ana içerik düzenleyicisini; eğitim ve sertifikalar için mevcut Eren Athletic alanlarını kullanın. Gerçek diploma, kurum veya deneyim bilgisi uydurmayın. Bu sürümde sertifika dosyası yükleme alanı eklenmez.</p><ul>';
        foreach(array('Özgeçmiş'=>trim(wp_strip_all_tags($post->post_content)),'Eğitim'=>get_post_meta($post->ID,'_ea_education',true),'Sertifikalar'=>get_post_meta($post->ID,'_ea_certificates',true))as$label=>$value)echo '<li>'.esc_html($label.(''===trim((string)$value)?': EKSİK — yönetici tarafından doldurulmalı.':': içerik mevcut; doğruluğunu kontrol edin.')).'</li>';echo '</ul>';
    },'ea_trainer','normal','high');
    add_meta_box('ea-coach-booking','Antrenör seçimi ve fiziksel kaynaklar',function($post){
        if(!ea_booking_integrated($post->ID)){echo '<p>Eski kayıt: ilişki yalnızca doluluk hesabında mevcut başlangıç haritasıyla değerlendirilir.</p>';return;}$meta=ea_booking_meta($post->ID);$context=ea_coach_record_context($meta);$program=(int)$meta['_ea_resource'];
        echo '<p><label for="ea-booking-trainer">Eğitime yetkili antrenör</label><select id="ea-booking-trainer" name="ea_booking_trainer_id">';foreach(ea_coach_trainers($program)as$id)echo '<option value="'.esc_attr($id).'" '.selected($context['trainer'],$id,false).'>'.esc_html(get_the_title($id)).'</option>';echo '</select></p><p>Kaynaklar: '.esc_html($context['venues']?implode(', ',array_map('get_the_title',$context['venues'])):'Ek fiziksel kaynak tanımlanmamış').'</p><p>Hoca değişikliği çalışma saatleri, paket kapsamı ve ortak doluluk denetiminden geçer.</p>';
    },'ea_booking','side','high');
});
require_once __DIR__.'/coaching-ui.php';
