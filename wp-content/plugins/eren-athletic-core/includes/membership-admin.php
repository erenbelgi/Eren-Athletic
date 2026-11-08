<?php
if ( ! defined( 'ABSPATH' ) ) exit;
add_action('admin_menu',function(){add_menu_page('Üye Yönetimi','Üye Yönetimi','manage_options','ea-members','ea_member_admin_page','dashicons-id-alt',26);});
add_action('admin_post_ea_member_manage',function(){
    if(!current_user_can('manage_options'))wp_die('Yetkisiz işlem.',403);
    check_admin_referer('ea_member_manage');
    $fields=array('operation','user_id','offer_id','membership_id','entitlement_id','delta','reason','request_key','status');
    foreach($fields as $f)if(isset($_POST[$f])&&!is_scalar($_POST[$f]))wp_die('Geçersiz alan.',400);
    $op=sanitize_key($_POST['operation']??'');$key=sanitize_text_field(wp_unslash($_POST['request_key']??''));$reason=sanitize_textarea_field(wp_unslash($_POST['reason']??''));
    if('activate'===$op)$result=ea_membership_activate(absint($_POST['user_id']??0),'ea_package',absint($_POST['offer_id']??0),$key);
    elseif('adjust'===$op)$result=ea_membership_adjust(absint($_POST['entitlement_id']??0),$_POST['delta']??'',$reason,$key);
    elseif('status'===$op)$result=ea_membership_set_status(absint($_POST['membership_id']??0),sanitize_key($_POST['status']??''),$reason,$key);
    else $result=new WP_Error('invalid','Geçersiz işlem.');
    // Store notices per administrator; never display request-provided HTML or SQL errors.
    set_transient('ea_member_notice_'.get_current_user_id(),array('error'=>is_wp_error($result),'message'=>is_wp_error($result)?$result->get_error_message():'İşlem tamamlandı.'),60);
    wp_safe_redirect(admin_url('admin.php?page=ea-members'));exit;
});
function ea_member_admin_form_start($operation){
    echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="ea_member_manage"><input type="hidden" name="operation" value="'.esc_attr($operation).'"><input type="hidden" name="request_key" value="'.esc_attr(wp_generate_uuid4()).'">';wp_nonce_field('ea_member_manage');
}
function ea_member_admin_page(){
    if(!current_user_can('manage_options'))wp_die('Yetkisiz işlem.',403);
    global $wpdb;
    echo '<div class="wrap"><h1>Üye Yönetimi</h1><p>PT paketleri aktivasyon anından itibaren 60 gün; fitness üyelikleri takvim ayı esasına göre geçerlidir. Bu ekran mevcut rezervasyonları değiştirmez.</p>';
    $notice=get_transient('ea_member_notice_'.get_current_user_id());if($notice){delete_transient('ea_member_notice_'.get_current_user_id());echo '<div class="notice '.($notice['error']?'notice-error':'notice-success').'"><p>'.esc_html($notice['message']).'</p></div>';}
    if(get_option('ea_membership_schema_version')!==EA_MEMBERSHIP_SCHEMA_VERSION){echo '<p>Üyelik veri altyapısı hazır değil.</p></div>';return;}
    ea_fitness_admin_section();
    echo '<h2>PT paketi ata</h2>';ea_member_admin_form_start('activate');
    echo '<p><label>Kullanıcı <select name="user_id" required><option value="">Kullanıcı seçin</option>';
    foreach(get_users(array('orderby'=>'display_name')) as $u)echo '<option value="'.esc_attr($u->ID).'">'.esc_html($u->display_name.' — '.$u->user_email.' (#'.$u->ID.')').'</option>';
    echo '</select></label></p><p><label>PT paketi <select name="offer_id" required><option value="">Paket seçin</option>';
    foreach(get_posts(array('post_type'=>'ea_package','post_status'=>'publish','numberposts'=>-1)) as $p)echo '<option value="'.esc_attr($p->ID).'">'.esc_html($p->post_title.' — '.get_post_meta($p->ID,'_ea_session_count',true).' seans / '.get_the_title((int)get_post_meta($p->ID,'_ea_program',true))).'</option>';
    echo '</select></label></p>';submit_button('60 gün aktif et');echo '</form><p>Yeni salon paketi veya fiyat oluşturulmaz. Doğrulanmış kullanıcı bağlantısı olmayan başvurular otomatik eşleştirilmez.</p><h2>Kullanıcılar</h2><ul>';
    foreach(get_users(array('orderby'=>'display_name')) as $u)echo '<li>'.esc_html($u->display_name.' (#'.$u->ID.')').'</li>';
    echo '</ul><h2>Üyelikler</h2>';
    $page=max(1,absint($_GET['member_page']??1));
    $rows=$wpdb->get_results($wpdb->prepare('SELECT m.*,e.id entitlement_id,e.total_units,e.reserved_units,e.consumed_units FROM '.ea_member_table('user_memberships').' m LEFT JOIN '.ea_member_table('membership_entitlements').' e ON e.membership_id=m.id ORDER BY m.id DESC LIMIT 30 OFFSET %d',($page-1)*30),ARRAY_A);
    $labels=array('active'=>'Aktif','suspended'=>'Askıda','cancelled'=>'İptal','expired'=>'Süresi dolmuş');
    foreach($rows as $m){
        if(ea_fitness_row($m)){ea_fitness_admin_card($m);continue;}
        $s=json_decode($m['snapshot'],true);$u=get_userdata($m['user_id']);$status=$m['status'];if('active'===$status&&$m['ends_at']<=ea_member_now())$status='expired';
        echo '<section style="background:#fff;padding:16px;margin:16px 0;border:1px solid #ccd0d4"><h3>'.esc_html('#'.$m['id'].' / '.($u?$u->display_name:'Silinmiş kullanıcı').' / '.($s['title']??'Paket')).'</h3><p>'.esc_html($labels[$status]??$status).' — '.esc_html(get_date_from_gmt($m['starts_at'],'d.m.Y H:i').' – '.get_date_from_gmt($m['ends_at'],'d.m.Y H:i')).'</p><p>'.esc_html('Toplam: '.$m['total_units'].' / Ayrılmış: '.$m['reserved_units'].' / Kullanılmış: '.$m['consumed_units'].' / Kullanılabilir: '.ea_membership_available($m)).'</p><p>Kapsam: '.esc_html(implode(', ',array_map('get_the_title',$s['resources']??array()))).'</p>';
        ea_member_admin_form_start('status');echo '<input type="hidden" name="membership_id" value="'.esc_attr($m['id']).'"><label for="ea-pt-status-'.esc_attr($m['id']).'">PT üyelik durumu</label> <select id="ea-pt-status-'.esc_attr($m['id']).'" name="status"><option value="suspended">Askıya al</option><option value="active">Yeniden aktif et (süre uzatılmaz)</option><option value="cancelled">İptal et</option></select> <label for="ea-pt-status-reason-'.esc_attr($m['id']).'">Durum değişikliği gerekçesi</label> <input id="ea-pt-status-reason-'.esc_attr($m['id']).'" name="reason" required maxlength="1000" placeholder="Gerekçe"> ';submit_button('Durumu kaydet','secondary','submit',false);echo '</form><br>';
        ea_member_admin_form_start('adjust');echo '<input type="hidden" name="entitlement_id" value="'.esc_attr($m['entitlement_id']).'"><label>Hak farkı <input type="number" name="delta" required min="-100000" max="100000" placeholder="Örn. 2 veya -1"></label> <label for="ea-pt-adjust-reason-'.esc_attr($m['entitlement_id']).'">Düzeltme gerekçesi</label> <input id="ea-pt-adjust-reason-'.esc_attr($m['entitlement_id']).'" name="reason" required maxlength="1000" placeholder="Düzeltme gerekçesi"> ';submit_button('Hak düzelt','secondary','submit',false);echo '</form><details><summary>Hak hareketleri (son 100)</summary><table class="widefat"><thead><tr><th>Zaman</th><th>İşlem</th><th>Toplam / Ayrılmış / Kullanılmış farkı</th><th>İşlemi yapan</th><th>Gerekçe</th></tr></thead><tbody>';
        foreach(ea_membership_history($m['id']) as $event)echo '<tr><td>'.esc_html(get_date_from_gmt($event['created_at'],'d.m.Y H:i:s')).'</td><td>'.esc_html($event['event_type']).'</td><td>'.esc_html($event['total_delta'].' / '.$event['reserved_delta'].' / '.$event['consumed_delta']).'</td><td>'.esc_html($event['actor_id']).'</td><td>'.esc_html($event['reason']).'</td></tr>';
        echo '</tbody></table></details></section>';
    }
    if($page>1)echo '<a class="button" href="'.esc_url(add_query_arg('member_page',$page-1)).'">Önceki</a> ';
    if(count($rows)===30)echo '<a class="button" href="'.esc_url(add_query_arg('member_page',$page+1)).'">Sonraki</a>';
    echo '</div>';
}
