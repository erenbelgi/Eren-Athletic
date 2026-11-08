<?php
/** Internal services only: no public endpoints and no hooks into legacy bookings. Dates are UTC. */
if ( ! defined( 'ABSPATH' ) ) exit;

function ea_member_now() { return gmdate( 'Y-m-d H:i:s' ); }
function ea_member_require( $condition, $message ) { if ( ! $condition ) throw new RuntimeException( $message ); }
function ea_member_admin() { ea_member_require( current_user_can( 'manage_options' ), 'Bu işlem için yönetici yetkisi gerekir.' ); }
function ea_member_actor( $user_id ) { ea_member_require( get_current_user_id() && ( get_current_user_id() === (int) $user_id || current_user_can( 'manage_options' ) ), 'Bu kullanıcıya erişim yetkiniz yok.' ); }
function ea_member_key( $scope, $key ) {
    ea_member_require( is_string( $key ) && strlen( $key ) >= 16 && strlen( $key ) <= 200, 'Geçerli bir işlem anahtarı gerekir.' );
    return hash( 'sha256', $scope . '|' . $key );
}
function ea_member_sql( $sql ) {
    $result = $GLOBALS['wpdb']->query( $sql );
    ea_member_require( false !== $result, 'Veritabanı işlemi başarısız; değişiklikler geri alındı.' );
    return $result;
}
function ea_member_insert( $table, $data ) {
    global $wpdb;
    ea_member_require( false !== $wpdb->insert( ea_member_table( $table ), $data ), 'Kayıt oluşturulamadı; değişiklikler geri alındı.' );
    return (int) $wpdb->insert_id;
}
function ea_member_transaction( $callback ) {
    global $wpdb;
    static $running = false;
    if ( $running || 1 === (int) $wpdb->get_var( 'SELECT @@in_transaction' ) ) return new WP_Error( 'transaction', 'İç içe işlem desteklenmiyor.' );
    if ( get_option( 'ea_membership_schema_version' ) !== EA_MEMBERSHIP_SCHEMA_VERSION ) return new WP_Error( 'schema', 'Üyelik tabloları henüz hazır değil.' );
    $running = true;
    try {
        ea_member_sql( 'START TRANSACTION' );
        $result = $callback();
        ea_member_sql( 'COMMIT' );
        return $result;
    } catch ( Throwable $e ) {
        $wpdb->query( 'ROLLBACK' );
        return new WP_Error( 'membership', $e->getMessage() );
    } finally { $running = false; }
}
function ea_member_row( $table, $id, $lock = false ) {
    global $wpdb;
    $row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . ea_member_table( $table ) . ' WHERE id=%d' . ( $lock ? ' FOR UPDATE' : '' ), $id ), ARRAY_A );
    ea_member_require( $row, 'Üyelik veya hak kaydı bulunamadı.' );
    return $row;
}
function ea_member_event( $m, $e, $a, $type, $td, $rd, $cd, $reason, $key ) {
    return ea_member_insert( 'entitlement_events', array( 'membership_id'=>$m, 'entitlement_id'=>$e ?: null, 'allocation_id'=>$a ?: null, 'event_type'=>$type, 'total_delta'=>$td, 'reserved_delta'=>$rd, 'consumed_delta'=>$cd, 'actor_id'=>get_current_user_id(), 'created_at'=>ea_member_now(), 'reason'=>$reason, 'operation_key'=>$key ) );
}

/** Venue offers use ea_membership with _ea_offer_kind=venue, _ea_resource_ids and _ea_session_count.
 * No venue offer or price is seeded. Unmapped offers fail closed. */
function ea_member_offer( $type, $id ) {
    if ('ea_membership' === $type && function_exists('ea_fitness_offer') && ea_fitness_offer($id)) return ea_fitness_snapshot($id);
    $post = get_post( $id );
    ea_member_require( $post && $post->post_type === $type && 'publish' === $post->post_status, 'Yayımlanmış paket bulunamadı.' );
    $units = (int) get_post_meta( $id, '_ea_session_count', true );
    ea_member_require( $units > 0 && $units <= 100000, 'Paket seans hakkı geçersiz.' );
    if ( 'ea_package' === $type ) {
        $resources = array( (int) get_post_meta( $id, '_ea_program', true ) ); $resource_type = 'ea_program';
    } else {
        ea_member_require( 'ea_membership' === $type && 'venue' === get_post_meta( $id, '_ea_offer_kind', true ), 'Salon paketi türü tanımlanmamış.' );
        $resources = get_post_meta( $id, '_ea_resource_ids', true ); $resource_type = 'ea_venue';
        ea_member_require( is_array( $resources ) && $resources, 'Salon eşleştirmesi bulunamadı.' );
    }
    $resources = array_values( array_unique( array_map( 'absint', $resources ) ) );
    foreach ( $resources as $resource ) ea_member_require( $resource && $resource_type === get_post_type( $resource ) && 'publish' === get_post_status( $resource ), 'Paket kaynağı geçersiz.' );
    return array( 'title'=>$post->post_title, 'offer_type'=>$type, 'offer_id'=>(int)$id, 'resources'=>$resources, 'resource_type'=>$resource_type, 'units'=>$units, 'session_minutes'=>(int)get_post_meta($id,'_ea_duration',true), 'valid_days'=>60, 'member_refund_hours'=>24, 'policy_version'=>1, 'price'=>get_post_meta($id,'_ea_price',true) );
}

function ea_membership_activate( $user_id, $type, $offer_id, $request_key, $application_id = 0 ) {
    if ('ea_membership' === $type && function_exists('ea_fitness_offer') && ea_fitness_offer($offer_id)) return ea_fitness_activate($user_id,$offer_id,$request_key,$application_id);
    return ea_member_transaction( function () use ( $user_id, $type, $offer_id, $request_key, $application_id ) {
        global $wpdb; ea_member_admin();
        $key = ea_member_key( 'activate', $request_key );
        ea_member_require( $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->users} WHERE ID=%d FOR UPDATE", $user_id ) ), 'Kullanıcı bulunamadı.' );
        $old = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM '.ea_member_table('user_memberships').' WHERE activation_key=%s FOR UPDATE', $key ), ARRAY_A );
        if ( $old ) { ea_member_require( (int)$old['user_id']===(int)$user_id && $old['offer_type']===$type && (int)$old['offer_id']===(int)$offer_id && (int)$old['application_id']===(int)$application_id, 'İşlem anahtarı başka aktivasyona ait.' ); return (int)$old['id']; }
        if ( $application_id ) {
            ea_member_require( 'ea_application' === get_post_type( $application_id ) && (int)get_post_meta($application_id,'_ea_user_id',true)===(int)$user_id, 'Başvurunun doğrulanmış kullanıcı bağlantısı yok.' );
            $app_type = get_post_meta($application_id,'_ea_offer_type',true) ?: 'ea_membership';
            $app_offer = get_post_meta($application_id,'_ea_offer_id',true) ?: get_post_meta($application_id,'_ea_membership',true);
            ea_member_require( $app_type === $type && (int)$app_offer === (int)$offer_id, 'Başvuru paketi uyuşmuyor.' );
        }
        $offer=ea_member_offer($type,$offer_id); $start=ea_member_now(); $end=gmdate('Y-m-d H:i:s',strtotime($start.' UTC')+60*DAY_IN_SECONDS);
        $id=ea_member_insert('user_memberships',array('user_id'=>$user_id,'offer_type'=>$type,'offer_id'=>$offer_id,'application_id'=>$application_id?:null,'status'=>'active','starts_at'=>$start,'ends_at'=>$end,'approved_by'=>get_current_user_id(),'approved_at'=>$start,'snapshot'=>wp_json_encode($offer),'activation_key'=>$key));
        // One shared balance across permitted venues; PT always has exactly one program.
        $e=ea_member_insert('membership_entitlements',array('membership_id'=>$id,'resource_type'=>$offer['resource_type'],'resource_id'=>$offer['resources'][0],'total_units'=>$offer['units']));
        ea_member_event($id,$e,0,'activate',$offer['units'],0,0,'Yönetici paket aktivasyonu',$key);
        return $id;
    });
}

function ea_membership_valid( $m, $booking_at = null ) {
    $now=ea_member_now();
    return 'active'===$m['status'] && $m['starts_at'] <= $now && $now < $m['ends_at'] && ( null===$booking_at || ( $m['starts_at'] <= $booking_at && $booking_at < $m['ends_at'] ) );
}
function ea_membership_active( $user_id ) {
    try { ea_member_actor($user_id); } catch(Throwable $e){ return new WP_Error('forbidden',$e->getMessage()); }
    global $wpdb; $now=ea_member_now();
    return $wpdb->get_results($wpdb->prepare('SELECT * FROM '.ea_member_table('user_memberships').' WHERE user_id=%d AND status=%s AND starts_at<=%s AND ends_at>%s ORDER BY ends_at,id',$user_id,'active',$now,$now),ARRAY_A);
}
function ea_membership_available( $e ) { return (int)$e['total_units']-(int)$e['reserved_units']-(int)$e['consumed_units']; }
function ea_member_scope( $m, $e, $resource ) {
    if (function_exists('ea_fitness_row') && ea_fitness_row($m)) return false;
    $snapshot=json_decode($m['snapshot'],true);
    return $snapshot && in_array((int)$resource,$snapshot['resources'],true) && $e['resource_type']===get_post_type($resource) && 'publish'===get_post_status($resource);
}
function ea_membership_can_book( $membership_id, $resource, $booking_at ) {
    try {
        global $wpdb; $m=ea_member_row('user_memberships',$membership_id); ea_member_actor($m['user_id']);
        $e=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.ea_member_table('membership_entitlements').' WHERE membership_id=%d',$membership_id),ARRAY_A);
        return $e && ea_membership_valid($m,$booking_at) && ea_member_scope($m,$e,$resource) && ea_membership_available($e)>0;
    } catch(Throwable $e){return new WP_Error('membership',$e->getMessage());}
}

/** Only explicitly owned NEW bookings can be allocated. Legacy post_author is never ownership. */
function ea_membership_reserve( $membership_id, $booking_id, $request_key ) {
    return ea_member_transaction(function()use($membership_id,$booking_id,$request_key){
        global $wpdb; $m=ea_member_row('user_memberships',$membership_id,true); ea_member_actor($m['user_id']);
        $key=ea_member_key('reserve',$request_key);
        ea_member_sql($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE ID=%d FOR UPDATE",$booking_id));
        $meta=$wpdb->get_results($wpdb->prepare("SELECT meta_key,meta_value FROM {$wpdb->postmeta} WHERE post_id=%d AND meta_key IN ('_ea_user_id','_ea_resource','_ea_booking_date','_ea_start_time','_ea_status')",$booking_id),OBJECT_K);
        $v=function($k)use($meta){return isset($meta[$k])?$meta[$k]->meta_value:'';};
        ea_member_require('ea_booking'===get_post_type($booking_id) && 'trash'!==get_post_status($booking_id) && (int)$v('_ea_user_id')===(int)$m['user_id'],'Rezervasyonun doğrulanmış kullanıcı bağlantısı yok.');
        $old=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.ea_member_table('booking_allocations').' WHERE booking_id=%d FOR UPDATE',$booking_id),ARRAY_A);
        if($old){ea_member_require((int)$old['membership_id']===(int)$membership_id && $old['request_key']===$key,'Rezervasyon zaten başka işlemle ayrılmış.');return (int)$old['id'];}
        ea_member_require('pending'===$v('_ea_status'),'Yalnızca bekleyen yeni rezervasyon için hak ayrılabilir.');
        $local=$v('_ea_booking_date').' '.$v('_ea_start_time'); $date=DateTimeImmutable::createFromFormat('!Y-m-d H:i',$local,wp_timezone());
        ea_member_require($date && $date->format('Y-m-d H:i')===$local,'Rezervasyon zamanı geçersiz.');
        $at=$date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        ea_member_require($at>ea_member_now() && ea_membership_valid($m,$at),'Üyelik rezervasyon tarihinde geçerli değil.');
        $e=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.ea_member_table('membership_entitlements').' WHERE membership_id=%d FOR UPDATE',$membership_id),ARRAY_A);
        ea_member_require($e && ea_member_scope($m,$e,(int)$v('_ea_resource')),'Paket bu kaynağı kapsamıyor.');
        ea_member_require(1===ea_member_sql($wpdb->prepare('UPDATE '.ea_member_table('membership_entitlements').' SET reserved_units=reserved_units+1 WHERE id=%d AND total_units>reserved_units+consumed_units',$e['id'])),'Yeterli seans hakkı yok.');
        $id=ea_member_insert('booking_allocations',array('booking_id'=>$booking_id,'user_id'=>$m['user_id'],'membership_id'=>$membership_id,'entitlement_id'=>$e['id'],'units'=>1,'state'=>'reserved','booking_start'=>$at,'request_key'=>$key,'created_at'=>ea_member_now()));
        ea_member_event($membership_id,$e['id'],$id,'reserve',0,1,0,'Bekleyen rezervasyon hakkı ayrıldı',$key);return $id;
    });
}

/** Changes allocation only; never edits legacy booking status or metadata. */
function ea_membership_allocation_transition( $booking_id, $action, $reason = '' ) {
    if ( function_exists('ea_booking_integrated') && ea_booking_integrated($booking_id) ) {
        if ( ! in_array($action,array('confirm','cancel_member','cancel_admin'),true) ) return new WP_Error('booking','Geçersiz işlem.');
        $result=ea_booking_change($booking_id,array('status'=>'confirm'===$action?'confirmed':'cancelled'),'cancel_member'===$action?'member':'facility',$reason,'confirm'===$action?'edit':'cancel');
        if(is_wp_error($result))return $result;
        global $wpdb;return $wpdb->get_var($wpdb->prepare('SELECT state FROM '.ea_member_table('booking_allocations').' WHERE booking_id=%d',$booking_id));
    }
    return ea_member_transaction(function()use($booking_id,$action,$reason){
        global $wpdb;
        $found=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.ea_member_table('booking_allocations').' WHERE booking_id=%d',$booking_id),ARRAY_A);
        ea_member_require($found,'Bu rezervasyona ayrılmış hak yok.');
        $m=ea_member_row('user_memberships',$found['membership_id'],true);ea_member_actor($m['user_id']);
        if('confirm'===$action || 'cancel_admin'===$action)ea_member_admin();
        ea_member_require(in_array($action,array('confirm','cancel_member','cancel_admin'),true),'Geçersiz işlem.');
        if('cancel_admin'===$action)ea_member_require(trim($reason)!=='','Yönetici iptali için gerekçe gerekir.');
        $e=ea_member_row('membership_entitlements',$found['entitlement_id'],true);$a=ea_member_row('booking_allocations',$found['id'],true);
        if(in_array($a['state'],array('refunded','cancelled_consumed'),true))return $a['state'];
        if('confirm'===$action && 'consumed'===$a['state'])return 'consumed';
        ea_member_require(in_array($a['state'],array('reserved','consumed'),true),'Hak durumu geçersiz.');
        $rd=0;$cd=0;
        if('confirm'===$action){ea_member_require(ea_membership_valid($m,$a['booking_start']),'Üyelik onaya uygun değil.');$rd=-(int)$a['units'];$cd=(int)$a['units'];$state='consumed';}
        else {
            $refund='cancel_admin'===$action || strtotime($a['booking_start'].' UTC')-time()>=24*HOUR_IN_SECONDS;
            if('reserved'===$a['state']){$rd=-(int)$a['units'];if(!$refund)$cd=(int)$a['units'];}
            elseif($refund)$cd=-(int)$a['units'];
            $state=$refund?'refunded':'cancelled_consumed';
        }
        ea_member_require((int)$e['reserved_units']+$rd>=0 && (int)$e['consumed_units']+$cd>=0,'Hak bakiyesi tutarsız.');
        ea_member_sql($wpdb->prepare('UPDATE '.ea_member_table('membership_entitlements').' SET reserved_units=reserved_units+%d, consumed_units=consumed_units+%d WHERE id=%d',$rd,$cd,$e['id']));
        ea_member_sql($wpdb->prepare('UPDATE '.ea_member_table('booking_allocations').' SET state=%s WHERE id=%d',$state,$a['id']));
        ea_member_event($m['id'],$e['id'],$a['id'],$action,0,$rd,$cd,sanitize_textarea_field($reason),hash('sha256',$action.'|'.$a['id']));return $state;
    });
}
function ea_membership_confirm( $booking_id ) { return ea_membership_allocation_transition($booking_id,'confirm'); }
function ea_membership_cancel( $booking_id, $by_admin = false, $reason = '' ) { return ea_membership_allocation_transition($booking_id,$by_admin?'cancel_admin':'cancel_member',$reason); }

function ea_membership_adjust( $entitlement_id, $delta, $reason, $request_key ) {
    return ea_member_transaction(function()use($entitlement_id,$delta,$reason,$request_key){
        global $wpdb;ea_member_admin();ea_member_require(filter_var($delta,FILTER_VALIDATE_INT)!==false && $delta!=0 && abs($delta)<=100000 && trim($reason)!=='','Geçerli hak farkı ve gerekçe gerekir.');
        $found=ea_member_row('membership_entitlements',$entitlement_id);$m=ea_member_row('user_memberships',$found['membership_id'],true);$e=ea_member_row('membership_entitlements',$entitlement_id,true);$key=ea_member_key('adjust',$request_key);$reason=sanitize_textarea_field($reason);
        $old=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.ea_member_table('entitlement_events').' WHERE operation_key=%s',$key),ARRAY_A);
        if($old){ea_member_require((int)$old['entitlement_id']===(int)$entitlement_id && (int)$old['total_delta']===(int)$delta && $old['reason']===$reason,'İşlem anahtarı farklı düzeltmeye ait.');return (int)$old['id'];}
        ea_member_require(ea_membership_available($e)+(int)$delta>=0 && (int)$e['total_units']+(int)$delta<=1000000,'Düzeltme ayrılmış/kullanılmış hakların altına inemez.');
        ea_member_sql($wpdb->prepare('UPDATE '.ea_member_table('membership_entitlements').' SET total_units=total_units+%d WHERE id=%d',$delta,$entitlement_id));
        return ea_member_event($m['id'],$entitlement_id,0,'adjust',(int)$delta,0,0,$reason,$key);
    });
}
function ea_membership_set_status( $membership_id, $status, $reason, $request_key ) {
    return ea_member_transaction(function()use($membership_id,$status,$reason,$request_key){
        global $wpdb;ea_member_admin();ea_member_require(in_array($status,array('active','suspended','cancelled'),true) && trim($reason)!=='','Durum ve gerekçe gerekir.');
        $m=ea_member_row('user_memberships',$membership_id,true);$key=ea_member_key('status',$request_key);
        $old=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.ea_member_table('entitlement_events').' WHERE operation_key=%s',$key),ARRAY_A);
        if($old){ea_member_require((int)$old['membership_id']===(int)$membership_id && $old['event_type']==='status_'.$status && $old['reason']===sanitize_textarea_field($reason),'İşlem anahtarı farklı durum değişikliğine ait.');return true;}
        ea_member_require('cancelled'!==$m['status'] && ('active'!==$status || $m['ends_at']>ea_member_now()),'İptal edilmiş veya süresi dolmuş üyelik yeniden etkinleştirilemez.');
        ea_member_sql($wpdb->prepare('UPDATE '.ea_member_table('user_memberships').' SET status=%s WHERE id=%d',$status,$membership_id));
        $e=$wpdb->get_var($wpdb->prepare('SELECT id FROM '.ea_member_table('membership_entitlements').' WHERE membership_id=%d',$membership_id));
        ea_member_event($membership_id,$e,0,'status_'.$status,0,0,0,sanitize_textarea_field($reason),$key);return true;
    });
}
function ea_membership_history( $membership_id, $limit = 100 ) {
    try{global $wpdb;$m=ea_member_row('user_memberships',$membership_id);ea_member_actor($m['user_id']);return $wpdb->get_results($wpdb->prepare('SELECT * FROM '.ea_member_table('entitlement_events').' WHERE membership_id=%d ORDER BY id DESC LIMIT %d',$membership_id,min(500,max(1,(int)$limit))),ARRAY_A);}catch(Throwable $e){return new WP_Error('forbidden',$e->getMessage());}
}
