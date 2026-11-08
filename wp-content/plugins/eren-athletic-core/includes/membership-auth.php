<?php
/** Account pages use WordPress credentials and sessions; membership remains administrator controlled. */
if ( ! defined( 'ABSPATH' ) ) exit;

function ea_account_pages() {
    return array('kayit-ol'=>array('Kayıt Ol','ea_account_register'),'giris-yap'=>array('Giriş Yap','ea_account_login'),'uye-panelim'=>array('Üye Panelim','ea_member_dashboard'));
}
function ea_account_install_pages() {
    if ( ! current_user_can('manage_options') ) return new WP_Error('forbidden','Yönetici yetkisi gerekir.');
    global $wpdb;$lock='ea_account_pages_'.substr(md5(DB_NAME.$wpdb->prefix),0,32);
    if(1!==(int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,5)',$lock)))return new WP_Error('busy','Hesap sayfaları şu anda kuruluyor. Daha sonra tekrar deneyin.');
    try{
    $ids=array();
    foreach(ea_account_pages() as $slug=>$definition){
        $page=get_page_by_path($slug,OBJECT,'page');
        if($page){
            if('publish'!==$page->post_status || !has_shortcode($page->post_content,$definition[1]))return new WP_Error('page_conflict','Mevcut hesap sayfası korunarak kurulum durduruldu: '.$slug);
            $ids[$slug]=$page->ID;continue;
        }
        $id=wp_insert_post(array('post_type'=>'page','post_status'=>'publish','post_title'=>$definition[0],'post_name'=>$slug,'post_content'=>'['.$definition[1].']','comment_status'=>'closed','ping_status'=>'closed'),true);
        if(is_wp_error($id))return $id;$ids[$slug]=$id;
    }
    update_option('ea_account_pages_version','1',false);return $ids;
    }finally{$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock));}
}
add_action('admin_init',function(){
    if(current_user_can('manage_options') && get_option('ea_account_pages_version')!=='1'){
        $result=ea_account_install_pages();
        if(is_wp_error($result))add_action('admin_notices',function()use($result){echo '<div class="notice notice-error"><p>'.esc_html($result->get_error_message()).'</p></div>';});
    }
});
function ea_account_context(){
    $post=get_queried_object();
    if(!$post instanceof WP_Post || !is_page())return false;
    foreach(ea_account_pages()as$definition)if(has_shortcode($post->post_content,$definition[1]))return true;
    return false;
}
function ea_account_no_cache(){
    if(!defined('DONOTCACHEPAGE'))define('DONOTCACHEPAGE',true);
    if(!headers_sent()){nocache_headers();header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');header('Vary: Cookie',false);header('X-Robots-Tag: noindex, nofollow');}
}
function ea_account_guest_token(){
    $token=isset($_COOKIE['ea_account_guest'])&&is_string($_COOKIE['ea_account_guest'])?$_COOKIE['ea_account_guest']:'';
    return preg_match('/^[a-f0-9]{64}$/D',$token)?$token:'';
}
add_filter('nonce_user_logged_out',function($uid,$action){
    if(in_array($action,array('ea_account_register','ea_account_login'),true))return ea_account_guest_token()?substr(wp_hash(ea_account_guest_token()),0,12):0;
    return $uid;
},10,2);

/** Atomic, bounded IP/identity counters. No passwords, raw IPs or email addresses are persisted. */
function ea_account_rate_take($scope,$identity,$limit,$seconds){
    global $wpdb;
    $digest=hash_hmac('sha256',$scope.'|'.$identity,wp_salt('auth'));
    $name='_ea_account_rate_'.$digest;$lock='ea_rate_'.substr($digest,0,48);
    if(1!==(int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,2)',$lock)))return false;
    try{
        $stored=$wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name=%s",$name));
        $parts=explode(':',(string)$stored);$expires=(int)($parts[0]??0);$count=(int)($parts[1]??0);
        if($expires<=time()){$expires=time()+$seconds;$count=0;}
        if($count >= $limit)return false;
        $ok=$wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->options} (option_name,option_value,autoload) VALUES (%s,%s,'off') ON DUPLICATE KEY UPDATE option_value=VALUES(option_value)",$name,$expires.':'.($count+1)));
        if(false===$ok)return false;
        // Bounded cleanup of expired rate counters; no unrelated options are touched.
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND CAST(SUBSTRING_INDEX(option_value,':',1) AS UNSIGNED)<%d LIMIT 50",$wpdb->esc_like('_ea_account_rate_').'%',time()));
        return true;
    }finally{$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock));}
}
function ea_account_ip(){return isset($_SERVER['REMOTE_ADDR'])&&filter_var($_SERVER['REMOTE_ADDR'],FILTER_VALIDATE_IP)?$_SERVER['REMOTE_ADDR']:'unknown';}
function ea_account_strong_password($password){
    $length=function_exists('mb_strlen')?mb_strlen($password,'UTF-8'):strlen($password);
    return $length>=12 && $length<=128 && strlen($password)<=512 && preg_match('/\p{Lu}/u',$password) && preg_match('/\p{Ll}/u',$password) && preg_match('/[0-9]/',$password) && preg_match('/[^\p{L}\p{N}\s]/u',$password);
}
function ea_account_verify_form($input,$action){
    if(!is_array($input))return false;
    foreach($input as$value)if(!is_scalar($value))return false;
    return ea_account_guest_token() && isset($input['ea_account_nonce']) && wp_verify_nonce($input['ea_account_nonce'],$action) && empty($input['website']);
}
function ea_account_register($input){
    if(is_user_logged_in())return new WP_Error('signed_in','Zaten giriş yaptınız.');
    if(!ea_account_verify_form($input,'ea_account_register'))return new WP_Error('invalid','Form doğrulanamadı. Sayfayı yenileyip tekrar deneyin.');
    $name=sanitize_text_field($input['full_name']??'');$email=strtolower(trim($input['email']??''));$password=$input['password']??'';
    if(!ea_account_rate_take('register_ip',ea_account_ip(),5,HOUR_IN_SECONDS) || !ea_account_rate_take('register_email',$email,5,HOUR_IN_SECONDS))return new WP_Error('rate','Çok fazla deneme yaptınız. Lütfen bir saat sonra tekrar deneyin.');
    if(strlen($name)<3 || strlen($name)>120 || strlen($email)>190 || !is_email($email))return new WP_Error('invalid','Ad soyad ve geçerli bir e-posta adresi girin.');
    if(!ea_account_strong_password($password))return new WP_Error('password','Şifreniz 12–128 karakter olmalı; büyük harf, küçük harf, rakam ve özel karakter içermeli.');
    global $wpdb;$lock='ea_signup_'.substr(hash('sha256',$email),0,48);
    if(1!==(int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,5)',$lock)))return new WP_Error('busy','İşlem şu anda tamamlanamıyor. Daha sonra tekrar deneyin.');
    try{
        // Direct SQL uniqueness check avoids a stale user lookup during concurrent registrations.
        if($wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->users} WHERE user_email=%s LIMIT 1",$email)))return new WP_Error('exists','Bu bilgilerle kayıt tamamlanamadı. Giriş yapabilir veya şifrenizi sıfırlayabilirsiniz.');
        $id=wp_insert_user(array('user_login'=>'ea_'.bin2hex(random_bytes(10)),'user_email'=>$email,'user_pass'=>$password,'display_name'=>$name,'nickname'=>$name,'first_name'=>$name,'role'=>'subscriber'));
        if(is_wp_error($id))return new WP_Error('register','Hesabınız oluşturulamadı. Lütfen daha sonra tekrar deneyin.');
        // Email ownership is deliberately unverified; no membership/application/booking is assigned.
        return $id;
    }finally{$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock));}
}

/** Covers password sign-in through this page and WordPress's native login endpoint. */
add_filter('authenticate',function($user,$login,$password){
    if(''===(string)$login || ''===(string)$password)return $user;
    if(!ea_account_rate_take('login_ip',ea_account_ip(),20,15*MINUTE_IN_SECONDS) || !ea_account_rate_take('login_identity',strtolower(trim($login)),10,15*MINUTE_IN_SECONDS))return new WP_Error('ea_rate','Çok fazla giriş denemesi. Lütfen 15 dakika sonra tekrar deneyin.');
    return $user;
},100,3);
function ea_account_login($input){
    if(!ea_account_verify_form($input,'ea_account_login'))return new WP_Error('invalid','Form doğrulanamadı. Sayfayı yenileyip tekrar deneyin.');
    $login=trim($input['login']??'');$password=$input['password']??'';
    if(''===$login || strlen($login)>190 || ''===$password || strlen($password)>512)return new WP_Error('login','E-posta/kullanıcı adı veya şifre hatalı.');
    $user=wp_signon(array('user_login'=>$login,'user_password'=>$password,'remember'=>!empty($input['remember'])),is_ssl());
    if(is_wp_error($user))return new WP_Error('login',in_array('ea_rate',$user->get_error_codes(),true)?'Çok fazla giriş denemesi. Lütfen 15 dakika sonra tekrar deneyin.':'E-posta/kullanıcı adı veya şifre hatalı.');
    wp_set_current_user($user->ID);return $user;
}

add_action('template_redirect',function(){
    if(!ea_account_context())return;
    ea_account_no_cache();
    $post=get_queried_object();
    if(has_shortcode($post->post_content,'ea_member_dashboard')&&!is_user_logged_in()){
        wp_safe_redirect(ea_core_page_url('giris-yap'));exit;
    }
    if(!is_user_logged_in()&&!ea_account_guest_token()){
        $token=bin2hex(random_bytes(32));
        setcookie('ea_account_guest',$token,array('expires'=>time()+DAY_IN_SECONDS,'path'=>COOKIEPATH?:'/','secure'=>is_ssl(),'httponly'=>true,'samesite'=>'Lax'));
        $_COOKIE['ea_account_guest']=$token;
    }
    if('POST'!==($_SERVER['REQUEST_METHOD']??'GET')||is_user_logged_in())return;
    $input=wp_unslash($_POST);$action=isset($input['ea_account_action'])&&is_string($input['ea_account_action'])?$input['ea_account_action']:'';
    if('register'===$action && has_shortcode($post->post_content,'ea_account_register')){
        $result=ea_account_register($input);
        if(!is_wp_error($result)){wp_safe_redirect(add_query_arg('account','registered',ea_sales_auth_destination('giris-yap')));exit;}
    }elseif('login'===$action && has_shortcode($post->post_content,'ea_account_login')){
        $result=ea_account_login($input);
        if(!is_wp_error($result)){wp_safe_redirect(ea_sales_after_login());exit;}
    }else $result=new WP_Error('invalid','Geçersiz form.');
    $GLOBALS['ea_account_error']=$result->get_error_message();
});

function ea_account_form_shortcode($kind){
    ea_account_no_cache();
    if(is_user_logged_in())return '<section class="ea-account"><div class="ea-account-card"><h2>Zaten giriş yaptınız.</h2><a class="ea-account-button" href="'.esc_url(ea_core_page_url('uye-panelim')).'">Üye Panelim →</a> <a href="'.esc_url(wp_logout_url(ea_core_page_url('giris-yap'))).'">Çıkış Yap</a></div></section>';
    $register='register'===$kind;$action='ea_account_'.$kind;
    $html='<section class="ea-account ea-account-auth"><div class="ea-account-intro"><p class="ea-account-eyebrow">EREN ATHLETIC / ÜYELİK</p><h1>'.($register?'Bir sonraki<br><span>adımın burada.</span>':'Kaldığın yerden<br><span>devam et.</span>').'</h1><p>'.($register?'Hesabını oluştur, üyeliklerini tek bir yerden takip et.':'Paketlerin, seans hakların ve rezervasyon geçmişin bir arada.').'</p><div class="ea-account-intro-note">'.($register?'Hesap oluşturduktan sonra paketlerin yönetici onayıyla aktif edilir.':'Hedefine odaklan.<br>Gelişimini takip et.').'</div></div><div class="ea-account-card"><p class="ea-account-eyebrow">'.($register?'İLK ADIM':'TEKRAR MERHABA').'</p><h2>'.($register?'Hesap oluştur':'Giriş yap').'</h2>';
    if(!empty($GLOBALS['ea_account_error']))$html.='<p class="ea-account-notice ea-account-error" role="alert">'.esc_html($GLOBALS['ea_account_error']).'</p>';
    if(!$register && isset($_GET['account']) && is_string($_GET['account']) && 'registered'===$_GET['account'])$html.='<p class="ea-account-notice" role="status">Hesabınız oluşturuldu. Giriş yapabilirsiniz. Üyelik paketiniz yönetici onayıyla aktif edilir.</p>';
    $html.='<form class="ea-account-form" method="post" action="'.esc_url(ea_sales_auth_destination($register?'kayit-ol':'giris-yap')).'"><input type="hidden" name="ea_account_action" value="'.$kind.'">'.wp_nonce_field($action,'ea_account_nonce',true,false).ea_sales_auth_fields().'<div class="ea-account-trap" aria-hidden="true"><label>Web sitesi<input name="website" tabindex="-1" autocomplete="off"></label></div>';
    if($register)$html.='<p><label for="ea-account-name">Ad soyad</label><input id="ea-account-name" name="full_name" required maxlength="120" autocomplete="name"></p>';
    $html.='<p><label for="ea-account-login">'.($register?'E-posta adresi':'E-posta veya kullanıcı adı').'</label><input id="ea-account-login" name="'.($register?'email':'login').'" type="'.($register?'email':'text').'" required maxlength="190" autocomplete="'.($register?'email':'username').'" autocapitalize="none" spellcheck="false"></p><p><label for="ea-account-password">Şifre</label><input id="ea-account-password" name="password" type="password" required '.($register?'minlength="12" maxlength="128" aria-describedby="ea-password-hint"':'maxlength="512"').' autocomplete="'.($register?'new-password':'current-password').'"><button class="ea-account-password-toggle" type="button" hidden aria-controls="ea-account-password" aria-pressed="false">Şifreyi göster</button></p>';
    if($register)$html.='<p id="ea-password-hint" class="ea-account-muted">12–128 karakter; büyük harf, küçük harf, rakam ve özel karakter.</p>';
    else $html.='<div class="ea-account-form-links"><label class="ea-account-check"><input name="remember" type="checkbox" value="1"> Beni hatırla</label><a href="'.esc_url(wp_lostpassword_url(ea_core_page_url('giris-yap'))).'">Şifremi unuttum</a></div>';
    $html.='<button type="submit" class="ea-account-button">'.($register?'Hesap oluştur':'Giriş yap').' <span aria-hidden="true">↗</span></button></form><p class="ea-account-switch">'.($register?'Zaten hesabın var mı? <a href="'.esc_url(ea_sales_auth_destination('giris-yap')).'">Giriş Yap</a>':'Henüz hesabın yok mu? <a href="'.esc_url(ea_sales_auth_destination('kayit-ol')).'">Kayıt Ol</a>').'</p></div></section>';
    return $html;
}
add_shortcode('ea_account_register',function(){return ea_account_form_shortcode('register');});
add_shortcode('ea_account_login',function(){return ea_account_form_shortcode('login');});

add_filter('wp_nav_menu_items',function($items,$args){
    if('primary_menu'!==($args->theme_location??''))return $items;
    $links=is_user_logged_in()?array(ea_core_page_url('uye-panelim')=>'Üye Panelim',wp_logout_url(ea_core_page_url('giris-yap'))=>'Çıkış Yap'):array(ea_core_page_url('giris-yap')=>'Giriş Yap',ea_core_page_url('kayit-ol')=>'Kayıt Ol');
    foreach($links as$url=>$label)$items.='<li class="menu-item ea-account-nav"><a href="'.esc_url($url).'">'.esc_html($label).'</a></li>';
    return $items;
},20,2);
add_filter('body_class',function($classes){$classes[]='ea-account-navigation';if(ea_account_context())$classes[]='ea-account-page';return $classes;});
add_action('wp_enqueue_scripts',function(){
    wp_enqueue_style('ea-membership-navigation',EA_CORE_URL.'assets/membership-navigation.css',array('erah-header-style'),(string)filemtime(EA_CORE_DIR.'assets/membership-navigation.css'));
    if(!ea_account_context())return;
    wp_enqueue_style('ea-membership-account',EA_CORE_URL.'assets/membership-account.css',array('ea-core'),(string)filemtime(EA_CORE_DIR.'assets/membership-account.css'));
    wp_enqueue_script('ea-membership-account',EA_CORE_URL.'assets/membership-account.js',array(),(string)filemtime(EA_CORE_DIR.'assets/membership-account.js'),true);
},110);
add_filter('template_include',function($template){return ea_account_context()?EA_CORE_DIR.'includes/member-account-template.php':$template;},110);
