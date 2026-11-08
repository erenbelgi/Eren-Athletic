<?php
/** Atomic member bookings. All transactional writes avoid WP hooks/cache until commit. UTC policy dates. */
if ( ! defined( 'ABSPATH' ) ) exit;

function ea_booking_integrated($id){
    global $wpdb;
    return (bool)$wpdb->get_var($wpdb->prepare("SELECT meta_id FROM {$wpdb->postmeta} WHERE post_id=%d AND meta_key='_ea_integration_version' AND meta_value='1'",$id));
}
function ea_booking_meta($id){
    global $wpdb;$rows=$wpdb->get_results($wpdb->prepare("SELECT meta_key,meta_value FROM {$wpdb->postmeta} WHERE post_id=%d AND meta_key LIKE %s",$id,$wpdb->esc_like('_ea_').'%'),ARRAY_A);$meta=array();
    foreach($rows as$r){ea_member_require(!array_key_exists($r['meta_key'],$meta),'Rezervasyon alanları tutarsız.');$meta[$r['meta_key']]=$r['meta_value'];}return $meta;
}
function ea_booking_write_meta($id,$meta){
    global $wpdb;
    foreach($meta as$key=>$value){
        ea_member_sql($wpdb->prepare("DELETE FROM {$wpdb->postmeta} WHERE post_id=%d AND meta_key=%s",$id,$key));
        ea_member_require(false!==$wpdb->insert($wpdb->postmeta,array('post_id'=>$id,'meta_key'=>$key,'meta_value'=>(string)$value)),'Rezervasyon alanı yazılamadı.');
    }
}
function ea_booking_utc($date,$time){
    if(!is_string($date)||!is_string($time))return new WP_Error('invalid','Geçersiz rezervasyon zamanı.');
    $local=$date.' '.$time;$d=DateTimeImmutable::createFromFormat('!Y-m-d H:i',$local,wp_timezone());
    if(!$d||$d->format('Y-m-d H:i')!==$local)return new WP_Error('invalid','Geçersiz rezervasyon zamanı.');
    return $d->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
}
function ea_booking_request_digest($uid,$key){return ea_member_key('integrated_booking_'.$uid,$key);}
function ea_booking_request_exists($uid,$key){
    try{global $wpdb;$digest=ea_booking_request_digest($uid,$key);return (int)$wpdb->get_var($wpdb->prepare('SELECT booking_id FROM '.ea_member_table('booking_allocations').' WHERE request_key=%s AND user_id=%d',$digest,$uid));}catch(Throwable $e){return 0;}
}
function ea_booking_engine_guard(){
    global $wpdb;
    foreach(array($wpdb->posts,$wpdb->postmeta,ea_member_table('user_memberships'),ea_member_table('membership_entitlements'),ea_member_table('booking_allocations'),ea_member_table('entitlement_events'))as$table){
        ea_member_require('InnoDB'===$wpdb->get_var($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s',$table)),'Atomik rezervasyon için InnoDB tabloları gerekir.');
    }
}
function ea_booking_membership_locked($id,$uid,$resource,$at,$need_unit=true){
    global $wpdb;$m=ea_member_row('user_memberships',$id,true);
    ea_member_require((int)$m['user_id']===(int)$uid,'Bu paket hesabınıza ait değil.');
    ea_member_require(ea_membership_valid($m,$at),'Üyelik rezervasyon tarihinde aktif/geçerli değil.');
    $e=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.ea_member_table('membership_entitlements').' WHERE membership_id=%d ORDER BY id LIMIT 1 FOR UPDATE',$id),ARRAY_A);
    ea_member_require($e&&ea_member_scope($m,$e,$resource),'Bu hizmet için geçerli rezervasyon hakkınız bulunmuyor.');
    if($need_unit)ea_member_require(ea_membership_available($e)>=1,'Kullanılabilir seans hakkınız bulunmuyor.');return array($m,$e);
}
function ea_booking_contact($contact){
    ea_member_require(is_array($contact),'İletişim alanları geçersiz.');$out=array();
    foreach(array('full_name','phone','email','referee','note')as$key){$value=$contact['_ea_'.$key]??'';ea_member_require(is_scalar($value),'İletişim alanları geçersiz.');$out['_ea_'.$key]='note'===$key?sanitize_textarea_field($value):('email'===$key?sanitize_email($value):sanitize_text_field($value));}
    ea_member_require(''!==$out['_ea_full_name']&&strlen($out['_ea_full_name'])<=120&&''!==$out['_ea_phone']&&strlen($out['_ea_phone'])<=40&&strlen($out['_ea_note'])<=1000&&strlen($out['_ea_email'])<=190&&(''===$out['_ea_email']||is_email($out['_ea_email'])),'İletişim bilgileri geçersiz.');return $out;
}
function ea_booking_create_member($resource,$date,$start,$end,$contact,$membership_id,$request_key,$trainer_id=0){
    global $wpdb;$GLOBALS['ea_booking_replayed']=false;$id=0;$locks=array();
    try{
        $uid=get_current_user_id();ea_member_require($uid>0,'Rezervasyon yapabilmek için giriş yapmalısınız.');
        $digest=ea_booking_request_digest($uid,$request_key);$at=ea_booking_utc($date,$start);ea_member_require(!is_wp_error($at),'Geçersiz tarih veya saat.');
        ea_member_require($resource instanceof WP_Post&&ea_calendar_resource($resource->ID)&&ea_core_future_date($date),'Geçersiz kaynak veya tarih.');
        $context=ea_coach_context($resource->ID,$trainer_id);
        $contact=ea_booking_contact($contact);ea_booking_engine_guard();
        $legacy_fingerprint=hash('sha256',wp_json_encode(array($uid,(int)$membership_id,(int)$resource->ID,$date,$start,$end)));
        $fingerprint=hash('sha256',wp_json_encode(array($uid,(int)$membership_id,(int)$resource->ID,$date,$start,$end,$context)));
        $locks=ea_calendar_lock(array_merge(ea_coach_lock_names($resource->ID,$date,$context),array('ea_request_'.substr(hash('sha256',$wpdb->prefix.$digest),0,48))));
        if(is_wp_error($locks))return $locks;
        $result=ea_member_transaction(function()use($uid,$resource,$date,$start,$end,$contact,$membership_id,$digest,$at,$fingerprint,$legacy_fingerprint,$context,&$id){
            global $wpdb;
            $old=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.ea_member_table('booking_allocations').' WHERE request_key=%s',$digest),ARRAY_A);
            if($old){
                $meta=ea_booking_meta($old['booking_id']);
                ea_member_require((int)$old['user_id']===$uid&&isset($meta['_ea_request_fingerprint'])&&hash_equals($meta['_ea_request_fingerprint'],($meta['_ea_booking_policy_version']??'')==='2'?$fingerprint:$legacy_fingerprint),'İşlem anahtarı başka bir rezervasyona ait.');
                $GLOBALS['ea_booking_replayed']=true;return (int)$old['booking_id'];
            }
            ea_member_require($at>ea_member_now(),'Geçmiş saate rezervasyon yapılamaz.');
            list($m,$e)=ea_booking_membership_locked($membership_id,$uid,$resource->ID,$at);
            if('ea_program'===$resource->post_type)ea_coach_assert_context($resource->ID,$context);
            ea_member_require(ea_coach_available($resource->ID,$date,$start,$end,$context),'Seçilen program, antrenör veya fiziksel kaynak bu saatte uygun değil.');
            $now=ea_member_now();$local=current_time('mysql');
            $post=array('post_author'=>$uid,'post_date'=>$local,'post_date_gmt'=>$now,'post_content'=>'','post_title'=>$resource->post_title.' — '.$date.' '.$start,'post_excerpt'=>'','post_status'=>'private','comment_status'=>'closed','ping_status'=>'closed','post_password'=>'','post_name'=>'','to_ping'=>'','pinged'=>'','post_modified'=>$local,'post_modified_gmt'=>$now,'post_content_filtered'=>'','post_parent'=>0,'guid'=>'','menu_order'=>0,'post_type'=>'ea_booking','post_mime_type'=>'','comment_count'=>0);
            ea_member_require(false!==$wpdb->insert($wpdb->posts,$post),'Rezervasyon oluşturulamadı.');$id=(int)$wpdb->insert_id;
            $meta=array_merge($contact,array('_ea_status'=>'pending','_ea_resource'=>$resource->ID,'_ea_resource_type'=>$resource->post_type,'_ea_booking_date'=>$date,'_ea_start_time'=>$start,'_ea_end_time'=>$end,'_ea_user_id'=>$uid,'_ea_membership_id'=>$membership_id,'_ea_integration_version'=>'1','_ea_request_fingerprint'=>$fingerprint,'_ea_allocation_revision'=>'1'));
            $meta['_ea_booking_policy_version']='2';
            if('ea_program'===$resource->post_type){$meta['_ea_trainer_id']=$context['trainer'];$meta['_ea_physical_resources']=wp_json_encode($context['venues']);}
            ea_booking_write_meta($id,$meta);
            ea_member_require(1===ea_member_sql($wpdb->prepare('UPDATE '.ea_member_table('membership_entitlements').' SET reserved_units=reserved_units+1 WHERE id=%d AND total_units>reserved_units+consumed_units',$e['id'])),'Yeterli seans hakkı yok.');
            $allocation=ea_member_insert('booking_allocations',array('booking_id'=>$id,'user_id'=>$uid,'membership_id'=>$membership_id,'entitlement_id'=>$e['id'],'units'=>1,'state'=>'reserved','booking_start'=>$at,'request_key'=>$digest,'created_at'=>$now));
            ea_member_event($m['id'],$e['id'],$allocation,'reserve',0,1,0,'Üye rezervasyonu oluşturuldu | '.wp_json_encode($context),$digest);
            return $id;
        });
        if(!is_wp_error($result))clean_post_cache($result);elseif($id)clean_post_cache($id);
        return $result;
    }catch(Throwable $e){return new WP_Error('booking',$e->getMessage());}
    finally{if(is_array($locks))ea_calendar_unlock($locks);}
}

/** The advisory booking lock serializes changes; membership -> entitlement -> allocation row order is fixed. */
function ea_booking_change($id,$changes,$origin,$reason,$operation='edit'){
    global $wpdb;$locks=array();$slotlocks=array();
    try{
        ea_member_require(get_current_user_id()>0,'Giriş yapmalısınız.');
        ea_member_require(in_array($origin,array('member','facility'),true)&&is_array($changes),'Geçersiz işlem.');
        if('facility'===$origin||'edit'===$operation)ea_member_admin();
        foreach($changes as$value)ea_member_require(is_scalar($value),'Geçersiz rezervasyon alanı.');
        $locks=ea_calendar_lock(array('ea_booking_'.md5($wpdb->prefix.'|'.$id)));if(is_wp_error($locks))return $locks;
        ea_member_require(ea_booking_integrated($id),'Bu rezervasyon yeni üyelik sistemine bağlı değil.');
        $old=ea_booking_meta($id);$next=$old;
        foreach(array('status','resource','booking_date','start_time','end_time','full_name','phone','email','referee','note','trainer_id')as$key)if(array_key_exists($key,$changes))$next['_ea_'.$key]=trim((string)$changes[$key]);
        $a=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.ea_member_table('booking_allocations').' WHERE booking_id=%d',$id),ARRAY_A);ea_member_require($a,'Rezervasyon hak kaydı eksik.');
        $uid=(int)$a['user_id'];ea_member_require((int)$old['_ea_user_id']===$uid,'Rezervasyon sahipliği tutarsız.');
        if(!current_user_can('manage_options'))ea_member_require(get_current_user_id()===$uid&&'cancel'===$operation&&array('status')===array_keys($changes)&&'cancelled'===$changes['status'],'Bu rezervasyona erişim yetkiniz yok.');
        ea_member_require(in_array($next['_ea_status'],array('pending','confirmed','cancelled'),true),'Geçersiz durum.');
        if('cancel'===$operation && !current_user_can('manage_options'))ea_member_require($a['booking_start']>ea_member_now(),'Geçmiş rezervasyon iptal edilemez.');
        $old_context=ea_coach_record_context($old);
        $selection_changed=isset($changes['trainer_id'])&&(int)$changes['trainer_id']!==$old_context['trainer'];
        foreach(array('resource','booking_date','start_time','end_time')as$key)$selection_changed=$selection_changed||$old['_ea_'.$key]!==$next['_ea_'.$key];
        $next_context=$selection_changed?ea_coach_context((int)$next['_ea_resource'],isset($next['_ea_trainer_id'])?(int)$next['_ea_trainer_id']:((int)$next['_ea_resource']===(int)$old['_ea_resource']?$old_context['trainer']:0)):$old_context;
        $moved=$old_context!==$next_context;foreach(array('resource','booking_date','start_time','end_time')as$key)$moved=$moved||$old['_ea_'.$key]!==$next['_ea_'.$key];
        if('cancelled'===$next['_ea_status'])ea_member_require(!$moved,'İptal ile tarih/kaynak değişikliği aynı işlemde yapılamaz.');
        $poststatus=$wpdb->get_var($wpdb->prepare("SELECT post_status FROM {$wpdb->posts} WHERE ID=%d",$id));
        ea_member_require('trash'!==$poststatus||'restore'===$operation,'Çöp kutusundaki rezervasyonu önce geri yükleyin.');
        $names=array_merge(ea_coach_lock_names((int)$old['_ea_resource'],$old['_ea_booking_date'],$old_context),ea_coach_lock_names((int)$next['_ea_resource'],$next['_ea_booking_date'],$next_context));
        $slotlocks=ea_calendar_lock($names);if(is_wp_error($slotlocks))return $slotlocks;
        ea_booking_engine_guard();
        $result=ea_member_transaction(function()use($id,$a,$uid,$old,$next,$moved,$origin,$reason,$operation,$poststatus,$old_context,$next_context){
            global $wpdb;
            $m=ea_member_row('user_memberships',$a['membership_id'],true);$e=ea_member_row('membership_entitlements',$a['entitlement_id'],true);$allocation=ea_member_row('booking_allocations',$a['id'],true);
            ea_member_sql($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE ID=%d FOR UPDATE",$id));
            ea_member_require((int)$m['user_id']===$uid&&(int)$e['membership_id']===(int)$m['id'],'Paket sahipliği tutarsız.');
            $state=$allocation['state'];$target=$next['_ea_status'];$revision=(int)$old['_ea_allocation_revision'];$rd=0;$cd=0;$newstate=$state;$at=$allocation['booking_start'];
            ea_member_require((int)$allocation['units']===1&&$revision>=1&&(int)$old['_ea_membership_id']===(int)$m['id'],'Rezervasyon hak bağlantısı tutarsız.');
            $states=array('pending'=>array('reserved'),'confirmed'=>array('consumed'),'cancelled'=>array('refunded','cancelled_consumed'));
            ea_member_require(isset($states[$old['_ea_status']])&&in_array($state,$states[$old['_ea_status']],true),'Rezervasyon ve hak durumları tutarsız; işlem uygulanmadı.');
            $terminal=in_array($state,array('refunded','cancelled_consumed'),true);
            $reactivate=$terminal&&in_array($target,array('pending','confirmed'),true);
            if('cancelled'!==$target && ($moved||$reactivate||'restore'===$operation||$target!==$old['_ea_status'])){
                $at=ea_booking_utc($next['_ea_booking_date'],$next['_ea_start_time']);ea_member_require(!is_wp_error($at)&&$at>ea_member_now(),'Yeni rezervasyon zamanı geçersiz veya geçmiş.');
                ea_member_require(ea_membership_valid($m,$at)&&ea_member_scope($m,$e,(int)$next['_ea_resource']),'Paket yeni kaynak veya tarihte geçerli değil.');
                $resource=ea_calendar_resource((int)$next['_ea_resource']);ea_member_require($resource,'Geçerli bir kaynak seçin.');
                if('ea_program'===$resource->post_type)ea_coach_assert_context($resource->ID,$next_context);
                ea_member_require(ea_coach_available($resource->ID,$next['_ea_booking_date'],$next['_ea_start_time'],$next['_ea_end_time'],$next_context,$id),'Yeni saat program, antrenör veya fiziksel kaynakta dolu ya da takvime uygun değil.');
                if($reactivate){ea_member_require(ea_membership_available($e)>=1,'Yeniden etkinleştirmek için yeterli hak yok.');$revision++;}
                $newstate='confirmed'===$target?'consumed':'reserved';
                if(!$terminal){if('reserved'===$state)$rd--;elseif('consumed'===$state)$cd--;}
                if('reserved'===$newstate)$rd++;else $cd++;
            }elseif('cancelled'===$target&&!$terminal){
                ea_member_require(trim($reason)!=='','İptal gerekçesi gerekir.');
                $refund='facility'===$origin||strtotime($allocation['booking_start'].' UTC')-time()>=24*HOUR_IN_SECONDS;
                if('reserved'===$state){$rd--;if(!$refund)$cd++;}elseif('consumed'===$state&&$refund)$cd--;
                $newstate=$refund?'refunded':'cancelled_consumed';
            }
            ea_member_require((int)$e['reserved_units']+$rd>=0&&(int)$e['consumed_units']+$cd>=0&&ea_membership_available($e)-$rd-$cd>=0,'Hak bakiyesi tutarsız.');
            $meta=$next;$meta['_ea_allocation_revision']=(string)$revision;
            if($moved && 'ea_program'===get_post_type((int)$next['_ea_resource'])){$meta['_ea_trainer_id']=(string)$next_context['trainer'];$meta['_ea_physical_resources']=wp_json_encode($next_context['venues']);}
            if($moved && 'ea_program'!==get_post_type((int)$next['_ea_resource']))unset($meta['_ea_trainer_id'],$meta['_ea_physical_resources']);
            if(!isset($old['_ea_trainer_id'])&&!$moved)unset($meta['_ea_trainer_id']);
            if('cancelled'===$target&&!$terminal){$meta['_ea_cancellation_origin']=$origin;$meta['_ea_cancellation_reason']=sanitize_textarea_field($reason);}
            $contact=ea_booking_contact($meta);$meta=array_merge($meta,$contact);$meta['_ea_resource_type']=get_post_type((int)$meta['_ea_resource']);
            $newpoststatus=$poststatus;
            if('trash'===$operation){$meta['_ea_before_trash_status']=$old['_ea_status'];$newpoststatus='trash';}
            elseif('restore'===$operation)$newpoststatus='private';
            $changed=$meta!==$old||$newstate!==$state||$newpoststatus!==$poststatus;
            if(!$changed)return $id;
            ea_member_sql($wpdb->prepare('UPDATE '.ea_member_table('membership_entitlements').' SET reserved_units=reserved_units+%d,consumed_units=consumed_units+%d WHERE id=%d',$rd,$cd,$e['id']));
            ea_member_sql($wpdb->prepare('UPDATE '.ea_member_table('booking_allocations').' SET state=%s,booking_start=%s WHERE id=%d',$newstate,$at,$a['id']));
            ea_booking_write_meta($id,$meta);
            $title=get_the_title((int)$meta['_ea_resource']).' — '.$meta['_ea_booking_date'].' '.$meta['_ea_start_time'];
            ea_member_sql($wpdb->prepare("UPDATE {$wpdb->posts} SET post_status=%s,post_title=%s,post_modified=%s,post_modified_gmt=%s WHERE ID=%d",$newpoststatus,$title,current_time('mysql'),ea_member_now(),$id));
            $event=$reactivate?'reactivate':('cancelled'===$target&&!$terminal?'cancel_'.$origin:('confirmed'===$target&&'reserved'===$state?'confirm':$operation));
            $audit=sanitize_textarea_field($reason).' | '.wp_json_encode(array('from'=>array_intersect_key($old,array_flip(array('_ea_status','_ea_resource','_ea_booking_date','_ea_start_time','_ea_end_time','_ea_trainer_id','_ea_physical_resources'))),'to'=>array_intersect_key($meta,array_flip(array('_ea_status','_ea_resource','_ea_booking_date','_ea_start_time','_ea_end_time','_ea_trainer_id','_ea_physical_resources')))));
            ea_member_event($m['id'],$e['id'],$a['id'],$event,0,$rd,$cd,$audit,hash('sha256','booking_'.$id.'_'.$revision.'_'.wp_generate_uuid4()));return $id;
        });
        clean_post_cache($id);return $result;
    }catch(Throwable $e){return new WP_Error('booking',$e->getMessage());}
    finally{if(is_array($slotlocks))ea_calendar_unlock($slotlocks);if(is_array($locks))ea_calendar_unlock($locks);}
}

function ea_booking_admin_save($id,$values){
    if(!current_user_can('manage_options'))return new WP_Error('forbidden','Yönetici yetkisi gerekir.');
    $origin=isset($_POST['ea_cancel_origin'])&&is_scalar($_POST['ea_cancel_origin'])?sanitize_key(wp_unslash($_POST['ea_cancel_origin'])):'facility';
    $reason=isset($_POST['ea_cancel_reason'])&&is_scalar($_POST['ea_cancel_reason'])?sanitize_textarea_field(wp_unslash($_POST['ea_cancel_reason'])):'';
    if(isset($_POST['ea_booking_trainer_id'])){if(!is_scalar($_POST['ea_booking_trainer_id']))return new WP_Error('invalid','Geçersiz antrenör.');$values['trainer_id']=absint($_POST['ea_booking_trainer_id']);}
    return ea_booking_change($id,$values,$origin,$reason,'edit');
}

// Direct metadata edits must never bypass allocation policy. Only the transaction service writes these fields.
foreach(array('add_post_metadata','update_post_metadata','delete_post_metadata')as$filter)add_filter($filter,function($check,$id,$key){if(null===$check&&strpos($key,'_ea_')===0&&ea_booking_integrated($id))return false;return $check;},10,3);
add_filter('wp_insert_post_data',function($data,$postarr){
    $id=(int)($postarr['ID']??0);if(!$id||!ea_booking_integrated($id))return $data;
    $old=get_post($id);
    if(!current_user_can('manage_options')||$data['post_status']!==$old->post_status)wp_die('Üyelikli rezervasyon durumu yalnızca güvenli rezervasyon işlemiyle değiştirilebilir.',403);
    // WP's edit handler writes the post before save_post. Keep that write inert;
    // authoritative booking changes (including title/timestamps) are committed by our service.
    foreach(array('post_title','post_content','post_excerpt','post_status','post_author','post_date','post_date_gmt','post_modified','post_modified_gmt','post_name','post_parent','post_password')as$key)$data[$key]=wp_slash($old->$key);
    return $data;
},10,2);
add_filter('pre_trash_post',function($override,$post){
    if(null!==$override||!ea_booking_integrated($post->ID))return $override;
    if(!current_user_can('delete_post',$post->ID)||!current_user_can('manage_options'))wp_die('Yetkisiz işlem.',403);
    $result=ea_booking_change($post->ID,array('status'=>'cancelled'),'facility','Yönetici çöp kutusuna taşıdı.','trash');
    if(is_wp_error($result))wp_die(esc_html($result->get_error_message()),409);return get_post($post->ID);
},1,2);
add_filter('pre_untrash_post',function($override,$post){
    if(null!==$override||!ea_booking_integrated($post->ID))return $override;
    if(!current_user_can('manage_options'))wp_die('Yetkisiz işlem.',403);
    $meta=ea_booking_meta($post->ID);$status=$meta['_ea_before_trash_status']??'cancelled';
    $result=ea_booking_change($post->ID,array('status'=>$status),'facility','Yönetici çöp kutusundan geri yükledi.','restore');
    if(is_wp_error($result))wp_die(esc_html($result->get_error_message()),409);return get_post($post->ID);
},1,2);
add_filter('pre_delete_post',function($override,$post){if(ea_booking_integrated($post->ID))wp_die('Hak geçmişi bulunan rezervasyon kalıcı silinemez; iptal veya çöp kutusunu kullanın.',409);return $override;},10,2);

add_action('add_meta_boxes_ea_booking',function($post){
    if(!ea_booking_integrated($post->ID))return;
    add_meta_box('ea-booking-membership','Üyelik ve hak işlemi',function($post){
        global $wpdb;$a=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.ea_member_table('booking_allocations').' WHERE booking_id=%d',$post->ID),ARRAY_A);if(!$a)return;
        $m=ea_member_row('user_memberships',$a['membership_id']);$s=json_decode($m['snapshot'],true);
        echo '<p>'.esc_html(($s['title']??'Paket').' / Kullanıcı #'.$a['user_id'].' / Hak durumu: '.$a['state']).'</p><p><label>İptal kaynağı <select name="ea_cancel_origin"><option value="facility">Tesis / yönetici (iade)</option><option value="member">Üye talebi (24 saat kuralı)</option></select></label></p><p><label>İptal / değişiklik gerekçesi<br><textarea name="ea_cancel_reason" maxlength="1000" rows="3" style="width:100%"></textarea></label></p><p>İptalde gerekçe zorunludur. Kaynak ve saat değişikliği paket ve doluluk kontrolünden geçer.</p><p><a href="'.esc_url(admin_url('admin.php?page=ea-members')).'">Üye Yönetimi ve hak hareketleri →</a></p>';
    },'ea_booking','side','high');
});

add_action('admin_post_ea_member_cancel_booking',function(){
    if(!is_user_logged_in())wp_die('Giriş yapmalısınız.',403);
    $id=isset($_POST['booking_id'])&&is_scalar($_POST['booking_id'])?absint($_POST['booking_id']):0;
    check_admin_referer('ea_member_cancel_'.$id);
    $result=ea_booking_change($id,array('status'=>'cancelled'),'member','Üye panelinden iptal talebi.','cancel');
    wp_safe_redirect(add_query_arg('ea_cancel',is_wp_error($result)?'error':'success',ea_core_page_url('uye-panelim')).'#ea-bookings');exit;
});

require_once __DIR__.'/booking-membership-ui.php';
