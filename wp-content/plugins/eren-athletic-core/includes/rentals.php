<?php
/** Independent court applications; no membership entitlement or allocation is used. */
if(!defined('ABSPATH'))exit;
define('EA_RENTAL_VERSION','1.0.0');
function ea_rental_ready(){return get_option('ea_rental_version')===EA_RENTAL_VERSION;}
function ea_rental_venue($id){return in_array((int)$id,array(21,22,23),true)&&'ea_venue'===get_post_type($id)&&'publish'===get_post_status($id);}
function ea_rental_migrate(){
 global $wpdb;try{ea_member_admin();}catch(Throwable $e){return new WP_Error('forbidden',$e->getMessage());}if(ea_rental_ready())return true;
 $locks=ea_calendar_lock(array('ea_rental_schema_'.md5(DB_NAME.$wpdb->prefix)));if(is_wp_error($locks))return $locks;
 try{
  if(ea_rental_ready())return true;
  foreach(array(21,22,23)as$id)ea_member_require(ea_rental_venue($id),'Mevcut üç saha doğrulanamadı; kurulum durduruldu.');
  $charset=$wpdb->get_charset_collate();$requests=ea_member_table('rental_requests');$events=ea_member_table('rental_events');
  ea_member_sql("CREATE TABLE IF NOT EXISTS `$requests` (id bigint unsigned NOT NULL AUTO_INCREMENT,user_id bigint unsigned NOT NULL,venue_id bigint unsigned NOT NULL,venue_title varchar(255) NOT NULL,booking_date date NOT NULL,start_time char(5) NOT NULL,end_time char(5) NOT NULL,starts_at datetime NOT NULL,ends_at datetime NOT NULL,timezone varchar(100) NOT NULL,price decimal(11,2) NOT NULL,currency char(3) NOT NULL,status varchar(16) NOT NULL,payment_status varchar(16) NOT NULL,request_key char(64) NOT NULL,created_at datetime NOT NULL,updated_at datetime NOT NULL,PRIMARY KEY(id),UNIQUE KEY request_key(request_key),KEY own_history(user_id,id),KEY occupancy(venue_id,status,booking_date,start_time)) ENGINE=InnoDB $charset");
  ea_member_sql("CREATE TABLE IF NOT EXISTS `$events` (id bigint unsigned NOT NULL AUTO_INCREMENT,request_id bigint unsigned NOT NULL,actor_id bigint unsigned NOT NULL,event_type varchar(24) NOT NULL,reason text NOT NULL,details longtext NOT NULL,operation_key char(64) NOT NULL,created_at datetime NOT NULL,PRIMARY KEY(id),UNIQUE KEY operation_key(operation_key),KEY history(request_id,id),FOREIGN KEY(request_id) REFERENCES `$requests` (id) ON DELETE RESTRICT) ENGINE=InnoDB $charset");
  foreach(array($requests,$events,$wpdb->posts,$wpdb->postmeta)as$table)ea_member_require('InnoDB'===$wpdb->get_var($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s',$table)),'InnoDB gerekli.');
  $r=ea_member_transaction(function()use($wpdb){
   $hours="pzt 09:00-23:00\nsal 09:00-23:00\ncar 09:00-23:00\nper 09:00-23:00\ncum 09:00-23:00\ncmt 09:00-23:00\npaz 09:00-23:00";
   foreach(array(21=>'900.00',22=>'900.00',23=>'750.00')as$id=>$price)ea_booking_write_meta($id,array('_ea_price'=>$price,'_ea_currency'=>'TRY','_ea_hours'=>$hours,'_ea_duration'=>60,'_ea_slot_step'=>30));
   ea_member_sql($wpdb->prepare("INSERT INTO {$wpdb->options}(option_name,option_value,autoload) VALUES('ea_rental_version',%s,'off') ON DUPLICATE KEY UPDATE option_value=VALUES(option_value)",EA_RENTAL_VERSION));return true;
  });
  foreach(array(21,22,23)as$id)clean_post_cache($id);wp_cache_delete('ea_rental_version','options');wp_cache_delete('notoptions','options');return $r;
 }catch(Throwable $e){return new WP_Error('migration',$e->getMessage());}finally{ea_calendar_unlock($locks);}
}
add_action('admin_init',function(){if(!current_user_can('manage_options'))return;$r=ea_rental_migrate();if(is_wp_error($r))add_action('admin_notices',function()use($r){echo '<div class="notice notice-error"><p>'.esc_html($r->get_error_message()).'</p></div>';});},35);
function ea_rental_row($id,$lock=false){global $wpdb;return $wpdb->get_row($wpdb->prepare('SELECT * FROM '.ea_member_table('rental_requests').' WHERE id=%d'.($lock?' FOR UPDATE':''),$id),ARRAY_A);}
function ea_rental_owned($page=1){global $wpdb;if(!get_current_user_id()||!ea_rental_ready())return array();return $wpdb->get_results($wpdb->prepare('SELECT * FROM '.ea_member_table('rental_requests').' WHERE user_id=%d ORDER BY id DESC LIMIT 21 OFFSET %d',get_current_user_id(),(max(1,(int)$page)-1)*20),ARRAY_A);}
function ea_rental_busy($venue,$from,$to){global $wpdb;if(!ea_rental_ready())return array();return $wpdb->get_results($wpdb->prepare('SELECT booking_date day,start_time start,end_time end FROM '.ea_member_table('rental_requests')." WHERE venue_id=%d AND status='confirmed' AND booking_date BETWEEN %s AND %s",$venue,$from,$to),ARRAY_A);}
function ea_rental_config($venue){
 ea_member_require(ea_rental_ready()&&ea_rental_venue($venue),'Saha kiralama şu anda kullanılamıyor.');clean_post_cache($venue);
 ea_member_require((int)get_post_meta($venue,'_ea_duration',true)===60&&(int)get_post_meta($venue,'_ea_slot_step',true)===30,'Saha süresi/başlangıç aralığı yönetici tarafından doğrulanmalı.');
 ea_member_require(get_post_meta($venue,'_ea_currency',true)==='TRY','Saha para birimi doğrulanamadı.');return ea_sales_decimal(get_post_meta($venue,'_ea_price',true));
}
function ea_rental_set_price($venue,$value){
 $locks=array();try{ea_member_admin();ea_member_require(ea_rental_venue($venue),'Geçersiz saha.');$price=ea_sales_decimal($value);
  $locks=ea_calendar_lock(array('ea_rental_catalog_'.md5($GLOBALS['wpdb']->prefix.'|'.$venue)));if(is_wp_error($locks))return $locks;
  return ea_member_transaction(function()use($venue,$price){ea_booking_write_meta($venue,array('_ea_price'=>$price,'_ea_currency'=>'TRY'));return true;});
 }catch(Throwable $e){return new WP_Error('price',$e->getMessage());}finally{clean_post_cache($venue);ea_calendar_unlock($locks);}
}
function ea_rental_quote($venue){try{return hash_hmac('sha256',$venue.'|'.ea_rental_config($venue).'|TRY|60',wp_salt('nonce'));}catch(Throwable $e){return '';}}
function ea_rental_time($venue,$date,$start){
 ea_member_require(is_string($date)&&is_string($start)&&ea_core_future_date($date),'Geçmiş veya geçersiz tarih.');
 $at=ea_booking_utc($date,$start);ea_member_require(!is_wp_error($at)&&$at>ea_member_now(),'Geçmiş veya geçersiz saat.');
 foreach(ea_core_schedule($venue,$date)as$slot)if($slot['start']===$start&&strtotime($slot['end'])-strtotime($start)===3600)return array($slot['end'],$at,ea_booking_utc($date,$slot['end']));
 throw new RuntimeException('Saat çalışma aralığına uygun değil; son kiralama 23:00’te bitmelidir.');
}
function ea_rental_event($id,$type,$reason,$details,$key){return ea_member_insert('rental_events',array('request_id'=>$id,'actor_id'=>get_current_user_id(),'event_type'=>$type,'reason'=>sanitize_textarea_field($reason),'details'=>wp_json_encode($details),'operation_key'=>$key,'created_at'=>ea_member_now()));}
function ea_rental_apply($input){
 global $wpdb;$locks=array();try{
  $uid=get_current_user_id();ea_member_require($uid>0,'Başvuru için giriş yapın.');ea_member_require(is_array($input),'Geçersiz form.');foreach(array('venue_id','booking_date','start_time','request_key','quote','_wpnonce')as$k)ea_member_require(isset($input[$k])&&is_scalar($input[$k]),'Eksik veya geçersiz alan.');
  ea_member_require(wp_verify_nonce((string)$input['_wpnonce'],'ea_rental_apply'),'Form doğrulanamadı; sayfayı yenileyin.');
  $venue=absint($input['venue_id']);$date=sanitize_text_field($input['booking_date']);$start=sanitize_text_field($input['start_time']);$key=ea_member_key('rental_apply_'.$uid,(string)$input['request_key']);
  $locks=ea_calendar_lock(array('ea_rental_apply_'.md5($wpdb->prefix.'|'.$uid.'|'.$venue.'|'.$date.'|'.$start),'ea_rental_key_'.substr($key,0,48),ea_core_lock_name($venue,$date),'ea_rental_catalog_'.md5($wpdb->prefix.'|'.$venue)));if(is_wp_error($locks))return $locks;
  return ea_member_transaction(function()use($wpdb,$uid,$venue,$date,$start,$key,$input){
   $table=ea_member_table('rental_requests');$old=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE request_key=%s",$key),ARRAY_A);
   if(!$old){$alias=$wpdb->get_var($wpdb->prepare('SELECT request_id FROM '.ea_member_table('rental_events').' WHERE operation_key=%s',$key));if($alias)$old=ea_rental_row((int)$alias);}
   if($old){ea_member_require((int)$old['user_id']===$uid&&(int)$old['venue_id']===$venue&&$old['booking_date']===$date&&$old['start_time']===$start,'İşlem anahtarı başka başvuruya ait.');return (int)$old['id'];}
   $price=ea_rental_config($venue);ea_member_require(hash_equals(ea_rental_quote($venue),(string)$input['quote']),'Fiyat değişti veya teklif doğrulanamadı; sayfayı yenileyin.');
   list($end,$at,$until)=ea_rental_time($venue,$date,$start);
   $old=$wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE user_id=%d AND venue_id=%d AND booking_date=%s AND start_time=%s AND status='pending' FOR UPDATE",$uid,$venue,$date,$start));if($old){ea_rental_event((int)$old,'deduplicated','Aynı bekleyen başvuruya bağlı tekrar gönderim.',array(),$key);return (int)$old;}
   ea_member_require(!ea_calendar_overlaps(ea_coach_busy($venue,$date,$date),$date,$start,$end),'Bu saat artık dolu; başka bir saat seçin.');
   $now=ea_member_now();$id=ea_member_insert('rental_requests',array('user_id'=>$uid,'venue_id'=>$venue,'venue_title'=>get_the_title($venue),'booking_date'=>$date,'start_time'=>$start,'end_time'=>$end,'starts_at'=>$at,'ends_at'=>$until,'timezone'=>wp_timezone_string(),'price'=>$price,'currency'=>'TRY','status'=>'pending','payment_status'=>'unverified','request_key'=>$key,'created_at'=>$now,'updated_at'=>$now));
   ea_rental_event($id,'submitted','Kiralama başvurusu alındı; saat henüz ayrılmadı.',array('price'=>$price,'currency'=>'TRY','venue'=>$venue,'date'=>$date,'start'=>$start,'end'=>$end),$key);return $id;
  });
 }catch(Throwable $e){return new WP_Error('rental',$e->getMessage());}finally{ea_calendar_unlock($locks);}
}
function ea_rental_review($id,$action,$reason,$token){
 global $wpdb;$locks=array();try{
  ea_member_require(get_current_user_id()>0&&ea_rental_ready(),'Giriş yapın veya sistemin hazır olmasını bekleyin.');ea_member_require(in_array($action,array('verify','approve','reject','cancel'),true),'Geçersiz işlem.');
  if('cancel'!==$action)ea_member_admin();$key=ea_member_key('rental_'.$action.'_'.$id.'|'.get_current_user_id(),$token);
  $id=(int)$id;$r=ea_rental_row($id);ea_member_require($r,'Başvuru bulunamadı.');if(!current_user_can('manage_options'))ea_member_require((int)$r['user_id']===get_current_user_id()&&in_array($r['status'],array('pending','cancelled'),true)&&$action==='cancel','Yalnızca kendi bekleyen başvurunuzu iptal edebilirsiniz.');
  $locks=ea_calendar_lock(array('ea_rental_record_'.md5($wpdb->prefix.'|'.$id),ea_core_lock_name((int)$r['venue_id'],$r['booking_date'])));if(is_wp_error($locks))return $locks;
  return ea_member_transaction(function()use($wpdb,$id,$action,$reason,$key){
   $r=ea_rental_row($id,true);ea_member_require($r,'Başvuru bulunamadı.');
   if(!current_user_can('manage_options'))ea_member_require(in_array($r['status'],array('pending','cancelled'),true)&&(int)$r['user_id']===get_current_user_id()&&$action==='cancel','Bu kiralama için tesisle iletişime geçin.');
   $event=$wpdb->get_row($wpdb->prepare('SELECT request_id,event_type FROM '.ea_member_table('rental_events').' WHERE operation_key=%s',$key),ARRAY_A);if($event){ea_member_require((int)$event['request_id']===$id&&$event['event_type']===$action,'İşlem anahtarı başka işleme ait.');return $id;}
   $status=$r['status'];$payment=$r['payment_status'];
   if('verify'===$action){ea_member_require('pending'===$status,'Yalnızca bekleyen başvurunun ödemesi doğrulanabilir.');if('verified'===$payment)return $id;ea_member_require(trim($reason)!=='','Manuel ödeme doğrulaması için kayıt açıklaması girin.');$payment='verified';}
   elseif('approve'===$action){if('confirmed'===$status)return $id;ea_member_require('pending'===$status&&'verified'===$payment,'Önce ayrı işlemle ödemeyi manuel doğrulayın.');
    ea_member_require(ea_rental_venue($r['venue_id'])&&$r['timezone']===wp_timezone_string(),'Saha veya zaman dilimi değişti; alternatif saat/iade değerlendirmesi gerekir.');
    try{list($end,$at,$until)=ea_rental_time((int)$r['venue_id'],$r['booking_date'],$r['start_time']);}catch(Throwable $e){throw new RuntimeException($e->getMessage().' Alternatif saat veya manuel iade değerlendirmesi gerekir.');}ea_member_require($end===$r['end_time']&&$at===$r['starts_at']&&$until===$r['ends_at'],'Saat bilgisi değişti; alternatif saat/iade değerlendirmesi gerekir.');
    ea_member_require(!ea_calendar_overlaps(ea_coach_busy((int)$r['venue_id'],$r['booking_date'],$r['booking_date']),$r['booking_date'],$r['start_time'],$r['end_time']),'Saat dolu. Onay uygulanmadı; alternatif saat veya manuel iade değerlendirmesi gerekir.');$status='confirmed';
   }elseif('reject'===$action){if('rejected'===$status)return $id;ea_member_require('pending'===$status,'Yalnızca bekleyen başvuru reddedilebilir.');ea_member_require(trim($reason)!=='','Ret gerekçesi girin.');$status='rejected';}
   else{if('cancelled'===$status)return $id;ea_member_require(in_array($status,array('pending','confirmed'),true),'Bu kayıt iptal edilemez.');ea_member_require(trim($reason)!=='','İptal gerekçesi girin.');$status='cancelled';}
   ea_member_sql($wpdb->prepare('UPDATE '.ea_member_table('rental_requests').' SET status=%s,payment_status=%s,updated_at=%s WHERE id=%d',$status,$payment,ea_member_now(),$id));
   ea_rental_event($id,$action,$reason,array('from'=>array('status'=>$r['status'],'payment'=>$r['payment_status']),'to'=>array('status'=>$status,'payment'=>$payment),'automatic_refund'=>false),$key);return $id;
  });
 }catch(Throwable $e){return new WP_Error('rental',$e->getMessage());}finally{ea_calendar_unlock($locks);}
}
require_once __DIR__.'/rentals-gallery.php';
require_once __DIR__.'/rentals-ui.php';
