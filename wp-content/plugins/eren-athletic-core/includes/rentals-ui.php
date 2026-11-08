<?php
if(!defined('ABSPATH'))exit;
function ea_rental_policy(){return 'Bekleyen başvuru saat ayırmaz; aynı saate başka başvurular gelebilir. Ödeme ayrı işlemle yönetici tarafından manuel doğrulanır. Kesinleşme, onay anındaki müsaitliğe bağlıdır. Bekleyen başvurunuzu iptal edebilirsiniz. Onaylı kiralamada iptal ve olası iade için tesisle iletişime geçin; yönetici değerlendirmesi gerekir. Otomatik ödeme veya iade yapılmaz.';}
function ea_rental_status($r){return array('pending'=>'Ödeme / Yönetici Onayı Bekliyor','confirmed'=>'Onaylandı','rejected'=>'Reddedildi','cancelled'=>'İptal Edildi')[$r['status']]??'Bilinmeyen durum';}
function ea_rental_payment($r){return $r['payment_status']==='verified'?'Ödeme manuel doğrulandı':'Ödeme doğrulanmadı';}
function ea_rental_selection(){
 $out=array();foreach(array('rental_venue','rental_date','rental_start')as$k){$v=$_REQUEST[$k]??'';$out[$k]=is_scalar($v)?sanitize_text_field(wp_unslash($v)):'';}
 if(!ea_rental_venue(absint($out['rental_venue'])))return array();$out['rental_venue']=absint($out['rental_venue']);
 if(!ea_core_valid_date($out['rental_date']))$out['rental_date']='';if(!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D',$out['rental_start']))$out['rental_start']='';return $out;
}
add_filter('ea_account_destination',function($url,$slug){$s=ea_rental_selection();return $s?add_query_arg($s,ea_core_page_url($slug)):$url;},10,2);
add_filter('ea_account_after_login',function($url){$s=ea_rental_selection();return $s?add_query_arg($s,get_permalink($s['rental_venue'])).'#ea-rental-form':$url;});
add_filter('ea_account_context_fields',function($html){foreach(ea_rental_selection()as$k=>$v)$html.='<input type="hidden" name="'.esc_attr($k).'" value="'.esc_attr($v).'">';return $html;});
function ea_rental_form($venue){
 $s=ea_rental_selection();$date=$s['rental_date']??'';$start=$s['rental_start']??'';
 $html='<section id="ea-rental-form" class="ea-rental-booking"><h2>Saha Kiralama Başvurusu</h2><p class="ea-rental-policy">'.esc_html(ea_rental_policy()).'</p>';
 if(!ea_rental_ready())return $html.'<p role="status">Kiralama sistemi yönetici tarafından hazırlanıyor.</p></section>';
 $html.='<form class="ea-calendar-form ea-rental-form" method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="ea_rental_apply"><input type="hidden" name="venue_id" value="'.esc_attr($venue).'"><input type="hidden" name="request_key" value="'.esc_attr(wp_generate_uuid4()).'"><input type="hidden" name="quote" value="'.esc_attr(ea_rental_quote($venue)).'">'.wp_nonce_field('ea_rental_apply','_wpnonce',true,false);
 $html.='<p><label for="ea-resource">Seçilen saha</label><select id="ea-resource" aria-label="Seçilen saha"><option value="'.esc_attr($venue).'">'.esc_html(get_the_title($venue)).'</option></select></p>';
 $html.='<section class="ea-booking-calendar" hidden aria-label="Saha uygunluk takvimi"><div class="ea-calendar-toolbar"><button type="button" class="ea-calendar-prev" aria-label="Önceki ay">←</button><h3 class="ea-calendar-title"></h3><button type="button" class="ea-calendar-next" aria-label="Sonraki ay">→</button></div><p class="ea-calendar-message" role="status" aria-live="polite"></p><div class="ea-calendar-week" aria-hidden="true"><span>Pzt</span><span>Sal</span><span>Çar</span><span>Per</span><span>Cum</span><span>Cmt</span><span>Paz</span></div><div class="ea-calendar-days" role="group" aria-label="Günler"></div><h3 class="ea-calendar-day-title">Saat seçimi</h3><div class="ea-calendar-times" role="group" aria-label="Saatler"></div></section>';
 $html.='<p><label for="ea-date">Tarih</label><input id="ea-date" type="date" name="booking_date" required min="'.esc_attr(wp_date('Y-m-d')).'" value="'.esc_attr($date).'"></p><p><label for="ea-slot">60 dakikalık zaman aralığı</label><select id="ea-slot" name="slot" required disabled><option value="">Önce tarih ve saat seçin</option></select><span class="ea-slot-status" role="status" aria-live="polite"></span></p><input type="hidden" name="start_time" value="'.esc_attr($start).'"><div class="ea-referee-field" hidden><input type="checkbox" disabled aria-label="Kullanılmayan hakem alanı" tabindex="-1"></div><noscript><p>Saatleri yüklemek için JavaScript gerekir. Tesisle iletişime geçebilirsiniz.</p></noscript>';
 if(is_user_logged_in())$html.='<p>Başvuru hesabınıza bağlanır. Tutar: <strong>'.esc_html(ea_sales_price_label(get_post_meta($venue,'_ea_price',true))).'</strong> / 60 dakika.</p><button type="submit" class="ea-button">Kiralama Başvurusu Gönder</button>';
 else{$args=array('rental_venue'=>$venue,'rental_date'=>$date,'rental_start'=>$start);$html.='<p>Başvuru için giriş yapın veya kayıt olun. Seçiminiz giriş sonrasında yeniden kontrol edilir.</p><div class="ea-rental-auth"><a class="ea-button" data-auth-base="'.esc_url(ea_core_page_url('giris-yap')).'" href="'.esc_url(add_query_arg($args,ea_core_page_url('giris-yap'))).'">Giriş Yap</a><a class="ea-button" data-auth-base="'.esc_url(ea_core_page_url('kayit-ol')).'" href="'.esc_url(add_query_arg($args,ea_core_page_url('kayit-ol'))).'">Kayıt Ol</a></div>';}
 return $html.'</form></section>';
}
add_action('admin_post_ea_rental_apply',function(){
 $input=wp_unslash($_POST);$slot=$input['slot']??'';if(is_scalar($slot)&&preg_match('/^(\d{2}:\d{2})\|\d{2}:\d{2}$/D',(string)$slot,$m))$input['start_time']=$m[1];else $input['start_time']='';
 $r=ea_rental_apply($input);$message='Başvurunuz alındı. Ödeme / yönetici onayı bekleniyor; saat henüz ayrılmadı.';
 if(!is_wp_error($r)){$row=ea_rental_row($r);if($row['status']!=='pending')$message='Daha önce kaydedilmiş başvurunuzun durumu: '.ea_rental_status($row).'. Yeni başvuru oluşturulmadı.';}
 ea_rental_notice($r,$message);wp_safe_redirect(ea_core_page_url('uye-panelim').'#ea-rentals');exit;
});
add_action('admin_post_nopriv_ea_rental_apply',function(){wp_safe_redirect(ea_core_page_url('giris-yap'));exit;});
function ea_rental_notice($r,$success){set_transient('ea_rental_notice_'.get_current_user_id(),array('error'=>is_wp_error($r),'text'=>is_wp_error($r)?$r->get_error_message():$success),120);}
function ea_rental_notice_html(){if(!is_user_logged_in())return '';$n=get_transient('ea_rental_notice_'.get_current_user_id());if(!$n)return '';delete_transient('ea_rental_notice_'.get_current_user_id());return '<p role="'.($n['error']?'alert':'status').'" class="ea-rental-policy">'.esc_html($n['text']).'</p>';}
add_action('admin_post_ea_rental_manage',function(){
 foreach(array('request_id','operation','reason','request_key','_wpnonce')as$k)if(!isset($_POST[$k])||!is_scalar($_POST[$k]))wp_die('Geçersiz form.',400);
 $id=absint($_POST['request_id']);check_admin_referer('ea_rental_manage_'.$id);
 $op=sanitize_key($_POST['operation']);if($op!=='cancel'&&!current_user_can('manage_options'))wp_die('Yetkisiz işlem.',403);
 if($op==='verify'&&($_POST['payment_checked']??'')!=='1')$r=new WP_Error('payment','Ödemeyi tesis kayıtlarından manuel doğruladığınızı işaretleyin.');else $r=ea_rental_review($id,$op,sanitize_textarea_field(wp_unslash($_POST['reason'])),(string)wp_unslash($_POST['request_key']));
 ea_rental_notice($r,'İşlem kaydedildi. Otomatik ödeme veya iade yapılmadı.');wp_safe_redirect(current_user_can('manage_options')?admin_url('admin.php?page=ea-rentals'):ea_core_page_url('uye-panelim').'#ea-rentals');exit;
});
function ea_rental_user_panel(){
 if(!is_user_logged_in())return '';ea_account_no_cache();$page=isset($_GET['ea_rental_page'])&&is_scalar($_GET['ea_rental_page'])?max(1,absint($_GET['ea_rental_page'])):1;$rows=ea_rental_owned($page);
 $html='<section class="ea-rental ea-rental-panel" id="ea-rentals"><p class="ea-rental-eyebrow">BAĞIMSIZ SAHA KİRALAMA</p><h2>Saha Kiralamalarım</h2>'.ea_rental_notice_html().'<p>'.esc_html(ea_rental_policy()).'</p>';
 if(!$rows)$html.='<p>Henüz saha kiralama başvurunuz yok.</p>';
 foreach(array_slice($rows,0,20)as$r){$html.='<article class="ea-rental-history"><h3>'.esc_html($r['venue_title']).'</h3><p>'.esc_html($r['booking_date'].' · '.$r['start_time'].'–'.$r['end_time'].' · '.ea_sales_price_label($r['price'])).'</p><p class="ea-rental-badge" data-status="'.esc_attr($r['status']).'">'.esc_html(ea_rental_status($r)).'</p><p>'.esc_html(ea_rental_payment($r)).'</p><p>Başvuru: '.esc_html(get_date_from_gmt($r['created_at'],'d.m.Y H:i')).'</p>';
  if($r['status']==='pending')$html.='<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="ea_rental_manage"><input type="hidden" name="operation" value="cancel"><input type="hidden" name="request_id" value="'.esc_attr($r['id']).'"><input type="hidden" name="request_key" value="'.esc_attr(wp_generate_uuid4()).'"><input type="hidden" name="reason" value="Üye bekleyen başvurusunu iptal etti.">'.wp_nonce_field('ea_rental_manage_'.$r['id'],'_wpnonce',true,false).'<button type="submit" class="ea-button">Başvuruyu İptal Et</button></form>';
  if($r['payment_status']==='verified'&&in_array($r['status'],array('cancelled','rejected'),true))$html.='<p>Olası iade için tesisle görüşün; otomatik iade yapılmadı.</p>';$html.='</article>';
 }
 if($page>1)$html.='<a href="'.esc_url(add_query_arg('ea_rental_page',$page-1,ea_core_page_url('uye-panelim'))).'#ea-rentals">Önceki kiralamalar</a> ';if(count($rows)>20)$html.='<a href="'.esc_url(add_query_arg('ea_rental_page',$page+1,ea_core_page_url('uye-panelim'))).'#ea-rentals">Sonraki kiralamalar</a>';
 return $html.'</section>';
}
add_filter('do_shortcode_tag',function($html,$tag){return $tag==='ea_member_dashboard'?$html.ea_rental_user_panel():$html;},60,2);
add_filter('do_shortcode_tag',function($html,$tag){
 if($tag!=='ea_booking_form')return $html;$urls=array();foreach(array(21,22,23)as$id)if(ea_rental_venue($id))$urls[$id]=get_permalink($id);
 $route='<aside class="ea-rental-route"><p>Saha kiralama, PT/fitness haklarından bağımsız başvuru ve yönetici onayıyla yapılır.</p><a class="ea-account-outline" href="'.esc_url(ea_core_page_url('salonlar')).'">Saha Kiralama Sayfasına Git ↗</a><script type="application/json" class="ea-rental-route-data">'.wp_json_encode($urls,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).'</script></aside>';
 return str_replace('<section class="ea-booking-calendar"',$route.'<section class="ea-booking-calendar"',$html);
},65,2);
add_action('admin_menu',function(){add_submenu_page('ea-members','Saha Kiralama Başvuruları','Saha Kiralama Başvuruları','manage_options','ea-rentals','ea_rental_admin');});
function ea_rental_admin(){
 if(!current_user_can('manage_options'))wp_die('Yetkisiz işlem.',403);global $wpdb;
 echo '<div class="wrap"><h1>Saha Kiralama Başvuruları</h1>'.ea_rental_notice_html().'<p>'.esc_html(ea_rental_policy()).'</p><p>Fiyat ve saha bilgileri: Salonlar → ilgili sahayı düzenle. Fiziksel eğitim eşleştirmeleri: PT Eğitimleri → yetkili antrenörler ve gerekli fiziksel kaynaklar.</p>';
 if(!ea_rental_ready()){echo '<p>Kurulum tamamlanmadı.</p></div>';return;}
 $page=isset($_GET['rental_page'])&&is_scalar($_GET['rental_page'])?max(1,absint($_GET['rental_page'])):1;
 $rows=$wpdb->get_results($wpdb->prepare('SELECT * FROM '.ea_member_table('rental_requests').' ORDER BY id DESC LIMIT 26 OFFSET %d',($page-1)*25),ARRAY_A);
 foreach(array_slice($rows,0,25)as$r){$u=get_userdata($r['user_id']);echo '<div style="background:white;border:1px solid #ccc;padding:18px;margin:18px 0"><h2>#'.esc_html($r['id']).' — '.esc_html($r['venue_title']).'</h2><p>Kullanıcı: <a href="'.esc_url(get_edit_user_link($r['user_id'])).'">'.esc_html($u?$u->display_name.' (#'.$r['user_id'].')':'Hesap bulunamadı').'</a></p><p>'.esc_html($r['booking_date'].' '.$r['start_time'].'–'.$r['end_time'].' / '.$r['timezone']).'</p><p>Başvuru fiyatı: <strong>'.esc_html(ea_sales_price_label($r['price'])).'</strong> ('.esc_html($r['currency']).'). Güncel katalog: '.esc_html(ea_sales_price_label(get_post_meta($r['venue_id'],'_ea_price',true))).'</p><p>'.esc_html(ea_rental_status($r).' — '.ea_rental_payment($r)).'</p>';
  if(in_array($r['status'],array('pending','confirmed'),true)){
   echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="ea_rental_manage"><input type="hidden" name="request_id" value="'.esc_attr($r['id']).'"><input type="hidden" name="request_key" value="'.esc_attr(wp_generate_uuid4()).'">'.wp_nonce_field('ea_rental_manage_'.$r['id'],'_wpnonce',true,false).'<p><label for="rental-reason-'.esc_attr($r['id']).'">İşlem açıklaması / ödeme kayıt referansı</label><br><input id="rental-reason-'.esc_attr($r['id']).'" name="reason" required maxlength="1000" class="regular-text"></p>';
   if($r['status']==='pending'){if($r['payment_status']!=='verified')echo '<p><label><input type="checkbox" name="payment_checked" value="1"> Ödemeyi tesis kayıtlarından manuel doğruladım.</label></p><button class="button" name="operation" value="verify">Ödemeyi Manuel Doğrula</button> ';
    else echo '<button class="button button-primary" name="operation" value="approve">Müsaitliği Kontrol Et ve Kesin Onayla</button> ';
    echo '<button class="button" name="operation" value="reject">Başvuruyu Reddet</button> ';
   }echo '<button class="button" name="operation" value="cancel">İptal Et</button></form>';
  }
  echo '<details><summary>İşlem geçmişi</summary><ul>';$events=$wpdb->get_results($wpdb->prepare('SELECT * FROM '.ea_member_table('rental_events').' WHERE request_id=%d ORDER BY id',$r['id']),ARRAY_A);foreach($events as$e)echo '<li>'.esc_html($e['created_at'].' UTC / '.$e['event_type'].' / kullanıcı #'.$e['actor_id'].' / '.$e['reason']).'<pre style="white-space:pre-wrap">'.esc_html($e['details']).'</pre></li>';echo '</ul></details></div>';
 }
 if($page>1)echo '<a class="button" href="'.esc_url(add_query_arg(array('page'=>'ea-rentals','rental_page'=>$page-1),admin_url('admin.php'))).'">Önceki</a> ';if(count($rows)>25)echo '<a class="button" href="'.esc_url(add_query_arg(array('page'=>'ea-rentals','rental_page'=>$page+1),admin_url('admin.php'))).'">Sonraki</a>';echo '</div>';
}
add_filter('template_include',function($template){return is_singular('ea_venue')||is_post_type_archive('ea_venue')||is_page('salonlar')?EA_CORE_DIR.'includes/rentals-template.php':$template;},140);
add_action('wp_enqueue_scripts',function(){
 $p=get_queried_object();$venue=is_singular('ea_venue');$booking=$p instanceof WP_Post&&has_shortcode($p->post_content,'ea_booking_form');$context=$venue||$booking||is_post_type_archive('ea_venue')||is_page('salonlar')||($p instanceof WP_Post&&has_shortcode($p->post_content,'ea_member_dashboard'));if(!$context)return;
 ea_account_no_cache();wp_enqueue_style('ea-rentals',EA_CORE_URL.'assets/rentals.css',array('ea-core'),filemtime(EA_CORE_DIR.'assets/rentals.css'));
 if($venue||$booking){wp_enqueue_style('ea-booking-calendar',EA_CORE_URL.'assets/booking-calendar.css',array('ea-core'),filemtime(EA_CORE_DIR.'assets/booking-calendar.css'));wp_enqueue_script('ea-booking-calendar',EA_CORE_URL.'assets/booking-calendar.js',array('ea-core'),filemtime(EA_CORE_DIR.'assets/booking-calendar.js'),true);wp_localize_script('ea-booking-calendar','eaCalendar',array('ajaxUrl'=>admin_url('admin-ajax.php'),'nonce'=>wp_create_nonce('ea_calendar'),'today'=>wp_date('Y-m-d')));wp_enqueue_script('ea-rentals',EA_CORE_URL.'assets/rentals.js',array('ea-booking-calendar'),filemtime(EA_CORE_DIR.'assets/rentals.js'),true);}
},55);
