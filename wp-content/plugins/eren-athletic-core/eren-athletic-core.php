<?php
/**
 * Plugin Name: Eren Athletic Core
 * Description: Tesis içerikleri, üyelik başvuruları ve takvim kontrollü rezervasyonlar.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: eren-athletic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EA_CORE_VERSION', '1.0.0' );
define( 'EA_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'EA_CORE_URL', plugin_dir_url( __FILE__ ) );

require_once EA_CORE_DIR . 'includes/pt.php';
require_once EA_CORE_DIR . 'includes/amenities.php';
require_once EA_CORE_DIR . 'includes/booking-calendar.php';
require_once EA_CORE_DIR . 'includes/membership-schema.php';
require_once EA_CORE_DIR . 'includes/membership-services.php';
require_once EA_CORE_DIR . 'includes/membership-admin.php';
require_once EA_CORE_DIR . 'includes/membership-auth.php';
require_once EA_CORE_DIR . 'includes/member-dashboard.php';
require_once EA_CORE_DIR . 'includes/booking-membership.php';
require_once EA_CORE_DIR . 'includes/fitness-membership.php';
require_once EA_CORE_DIR . 'includes/membership-sales.php';
require_once EA_CORE_DIR . 'includes/coaching.php';
require_once EA_CORE_DIR . 'includes/rentals.php';
require_once EA_CORE_DIR . 'includes/accessibility.php';

function ea_core_content_types() {
	$types = array(
		'ea_trainer' => array( 'Antrenörler', 'Antrenör', 'Antrenörler', true ),
		'ea_program' => array( 'PT Eğitimleri', 'Eğitim', 'PT Eğitimleri', true ),
		'ea_package' => array( 'PT Paketleri', 'Paket', 'PT Paketleri', true ),
		'ea_venue' => array( 'Salonlar', 'Salon', 'Salonlar', true ),
		'ea_amenity' => array( 'Tesis Olanakları', 'Olanak', 'Tesis Olanakları', true ),
		'ea_membership' => array( 'Üyelik Seçenekleri', 'Üyelik Seçeneği', 'Üyelik Seçenekleri', true ),
		'ea_announcement' => array( 'Duyurular', 'Duyuru', 'Duyurular', true ),
		'ea_booking' => array( 'Rezervasyonlar', 'Rezervasyon', 'Rezervasyonlar', false ),
		'ea_application' => array( 'Üyelik Başvuruları', 'Başvuru', 'Üyelik Başvuruları', false ),
	);

	foreach ( $types as $type => $labels ) {
		$public = $labels[3];
		register_post_type( $type, array(
			'labels' => array( 'name' => $labels[0], 'singular_name' => $labels[1], 'menu_name' => $labels[2], 'add_new_item' => $labels[1] . ' Ekle', 'edit_item' => $labels[1] . ' Bilgilerini Düzenle' ),
			'public' => $public,
			'show_ui' => true,
			'show_in_rest' => $public,
			'has_archive' => $public,
			'rewrite' => $public ? array( 'slug' => sanitize_title( remove_accents( $labels[2] ) ) ) : false,
			'menu_icon' => 'dashicons-' . ( 'ea_booking' === $type ? 'calendar-alt' : ( 'ea_application' === $type ? 'id-alt' : 'groups' ) ),
			'supports' => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
			'exclude_from_search' => ! $public,
			'publicly_queryable' => $public,
			'capability_type' => 'post',
			// Private admin records use manage_options directly; mapping it as a meta cap hides admin menus.
			'map_meta_cap' => $public,
			'capabilities' => $public ? array() : array( 'edit_post' => 'manage_options', 'read_post' => 'manage_options', 'delete_post' => 'manage_options', 'edit_posts' => 'manage_options', 'edit_others_posts' => 'manage_options', 'publish_posts' => 'manage_options', 'read_private_posts' => 'manage_options', 'delete_posts' => 'manage_options', 'delete_private_posts' => 'manage_options', 'delete_published_posts' => 'manage_options', 'delete_others_posts' => 'manage_options', 'edit_private_posts' => 'manage_options', 'edit_published_posts' => 'manage_options', 'create_posts' => 'do_not_allow' ),
		) );
	}
}
add_action( 'init', 'ea_core_content_types' );

function ea_core_fields() {
	return array(
		'ea_trainer' => array(
			'expertise' => array( 'Uzmanlık alanları', 'text' ), 'education' => array( 'Eğitim bilgileri', 'textarea' ),
			'certificates' => array( 'Sertifikalar ve belgeler', 'textarea' ), 'training_types' => array( 'Çalıştığı antrenman türleri', 'text' ),
			'hours' => array( 'Uygunluk takvimi (gün saat aralıkları)', 'hours' ), 'duration' => array( 'Seans süresi (dakika)', 'number' ),
		),
		'ea_program' => array(
            'content_status' => array('İçerik doğruluğu','select:unset,verified,sample'),
            'goal' => array('Eğitim amacı','textarea'), 'scope' => array('Eğitim kapsamı','textarea'),
            'syllabus' => array('Eğitim içeriği','textarea'), 'participation' => array('Katılım koşulları','textarea'),
            'levels' => array('Başlangıç / orta / ileri seviye uygunluğu','textarea'),
            'group_capacity' => array('Bilgilendirme amaçlı grup kapasitesi (kontenjan satışı değildir)','number'),
			'type' => array( 'Eğitim türü', 'select:individual,group' ), 'duration' => array( 'Seans süresi (dakika)', 'number' ),
			'price' => array( 'Seans ücreti (belirtilmediyse boş bırakın)', 'text' ), 'hours' => array( 'Uygunluk takvimi (gün saat aralıkları)', 'hours' ),
			'trainer' => array( 'İlişkili antrenör', 'number' ), 'package' => array( 'İlişkili paket', 'number' ),
		),
		'ea_package' => array( 'session_count' => array( 'Seans adedi', 'number' ), 'duration' => array( 'Seans süresi (dakika)', 'number' ), 'price' => array( 'Paket ücreti (bilinmiyorsa boş bırakın)', 'text' ), 'trainer' => array( 'Uygun antrenör', 'number' ), 'program' => array( 'İlgili eğitim', 'number' ), 'details' => array( 'Paket kapsamı', 'textarea' ) ),
		'ea_venue' => array(
            'gallery' => array('Galeri görsel ID’leri (mevcut Medya kütüphanesi; virgülle ayırın)','text'),
			'venue_type' => array( 'Salon türü', 'select:basketball,volleyball' ), 'facilities' => array( 'Salon olanakları', 'textarea' ),
			'rental_info' => array( 'Kiralama bilgileri ve koşulları', 'textarea' ), 'price' => array( 'Kiralama ücreti (belirtilmediyse boş bırakın)', 'text' ),
			'referee_available' => array( 'Hakem hizmeti', 'select:unset,available,unavailable' ), 'referee_price' => array( 'Hakem hizmet ücreti (bilinmiyorsa boş bırakın)', 'text' ),
			'hours' => array( 'Kullanılabilirlik takvimi (gün saat aralıkları)', 'hours' ), 'duration' => array( 'Rezervasyon süresi (dakika)', 'number' ),
		),
		'ea_amenity' => array( 'hours' => array( 'Çalışma saatleri (biliniyorsa)', 'text' ) ),
		'ea_membership' => array( 'price' => array( 'Ücret (bilinmiyorsa boş bırakın)', 'text' ), 'duration' => array( 'Süre (bilinmiyorsa boş bırakın)', 'text' ), 'terms' => array( 'Üyelik koşulları', 'textarea' ) ),
		'ea_announcement' => array( 'event_date' => array( 'Yayın/duyuru tarihi', 'date' ) ),
		'ea_booking' => array(
			'status' => array( 'Durum', 'select:pending,confirmed,cancelled' ), 'resource' => array( 'Kaynak (kayıt ID)', 'number' ),
			'resource_type' => array( 'Rezervasyon kaynağı türü', 'text' ), 'booking_date' => array( 'Tarih', 'date' ),
			'start_time' => array( 'Başlangıç saati', 'text' ), 'end_time' => array( 'Bitiş saati', 'text' ),
			'full_name' => array( 'Ad soyad', 'text' ), 'email' => array( 'E-posta', 'email' ), 'phone' => array( 'Telefon', 'text' ),
			'referee' => array( 'Hakem talebi', 'text' ), 'note' => array( 'Açıklama', 'textarea' ),
		),
		'ea_application' => array(
			'status' => array( 'Durum', 'select:pending,contacted,accepted,rejected' ), 'membership' => array( 'İlgili üyelik seçeneği', 'number' ),
			'full_name' => array( 'Ad soyad', 'text' ), 'email' => array( 'E-posta', 'email' ), 'phone' => array( 'Telefon', 'text' ),
		),
	);
}

function ea_core_add_meta_boxes() {
	foreach ( ea_core_fields() as $type => $fields ) {
		add_meta_box( 'ea-core-details', 'Eren Athletic Bilgileri', 'ea_core_render_meta_box', $type, 'normal', 'high' );
	}
}
add_action( 'add_meta_boxes', 'ea_core_add_meta_boxes' );

function ea_core_render_meta_box( $post ) {
	wp_nonce_field( 'ea_core_save_meta', 'ea_core_meta_nonce' );
	$fields = ea_core_fields()[ $post->post_type ];
	echo '<style>.ea-admin-field{margin:14px 0}.ea-admin-field label{display:block;font-weight:600;margin-bottom:5px}.ea-admin-field input,.ea-admin-field select,.ea-admin-field textarea{width:100%;max-width:680px}.ea-admin-hint{color:#646970;font-size:12px}</style>';
	foreach ( $fields as $key => $field ) {
		$value = get_post_meta( $post->ID, '_ea_' . $key, true );
		$input_id = 'ea-meta-' . esc_attr( $key );
		echo '<div class="ea-admin-field"><label for="' . $input_id . '">' . esc_html( $field[0] ) . '</label>';
		$references = array( 'trainer' => array( 'ea_trainer', 'Antrenör seçin' ), 'program' => array( 'ea_program', 'Eğitim seçin' ), 'package' => array( 'ea_package', 'Paket seçin' ), 'membership' => array( 'ea_membership', 'Üyelik seçin' ) );
		if ( isset( $references[ $key ] ) ) {
			echo '<select id="' . $input_id . '" name="ea_meta[' . esc_attr( $key ) . ']"><option value="0">' . esc_html( $references[ $key ][1] ) . '</option>';
			$items = get_posts( array( 'post_type' => $references[ $key ][0], 'post_status' => array( 'publish', 'draft', 'pending', 'private' ), 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
			foreach ( $items as $item ) echo '<option value="' . esc_attr( $item->ID ) . '" ' . selected( (int) $value, $item->ID, false ) . '>' . esc_html( $item->post_title ) . '</option>';
			echo '</select>';
		} elseif ( 'hours' === $field[1] ) {
			echo '<textarea id="' . $input_id . '" name="ea_meta[' . esc_attr( $key ) . ']" rows="7" placeholder="pzt 09:00-20:00&#10;sal 09:00-20:00">' . esc_textarea( $value ) . '</textarea><p class="ea-admin-hint">Her satıra bir aralık yazın: pzt, sal, çar, per, cum, cmt veya paz; saatleri 24 saat biçiminde belirtin. Tanımlanmayan günler kapalıdır.</p>';
		} elseif ( 0 === strpos( $field[1], 'select:' ) ) {
			$options = explode( ',', substr( $field[1], 7 ) );
			$labels = array( 'verified' => 'Yönetici tarafından doğrulandı', 'sample' => 'Örnek proje içeriği', 'pending' => 'Beklemede', 'confirmed' => 'Onaylandı', 'cancelled' => 'İptal edildi', 'contacted' => 'İletişime geçildi', 'accepted' => 'Kabul edildi', 'rejected' => 'Reddedildi', 'individual' => 'Bireysel', 'group' => 'Grup', 'basketball' => 'Basketbol', 'volleyball' => 'Voleybol', 'unset' => 'Belirtilmedi', 'available' => 'Sunuluyor', 'unavailable' => 'Sunulmuyor' );
			echo '<select id="' . $input_id . '" name="ea_meta[' . esc_attr( $key ) . ']">';
			foreach ( $options as $option ) echo '<option value="' . esc_attr( $option ) . '" ' . selected( $value, $option, false ) . '>' . esc_html( isset( $labels[ $option ] ) ? $labels[ $option ] : $option ) . '</option>';
			echo '</select>';
		} elseif ( 'textarea' === $field[1] ) {
			echo '<textarea id="' . $input_id . '" name="ea_meta[' . esc_attr( $key ) . ']" rows="4">' . esc_textarea( $value ) . '</textarea>';
		} else {
			$type = in_array( $field[1], array( 'number', 'date', 'email' ), true ) ? $field[1] : 'text';
			echo '<input id="' . $input_id . '" type="' . esc_attr( $type ) . '" name="ea_meta[' . esc_attr( $key ) . ']" value="' . esc_attr( $value ) . '" ' . ( 'number' === $type ? 'min="1" step="1"' : '' ) . '>';
		}
		echo '</div>';
	}
}

function ea_core_save_meta( $post_id ) {
	if ( ! isset( $_POST['ea_core_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ea_core_meta_nonce'] ) ), 'ea_core_save_meta' ) ) return;
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
	$post = get_post( $post_id );
	if ( ! $post || ! current_user_can( 'edit_post', $post_id ) || ! isset( ea_core_fields()[ $post->post_type ] ) ) return;
	$fields = ea_core_fields()[ $post->post_type ];
	$values = isset( $_POST['ea_meta'] ) && is_array( $_POST['ea_meta'] ) ? wp_unslash( $_POST['ea_meta'] ) : array();
    if ('ea_application' === $post->post_type && ea_sales_application($post_id)) {
        $result=ea_sales_legacy_review($post_id,$values);
        if(is_wp_error($result))wp_die(esc_html($result->get_error_message()),'Başvuru kaydedilemedi',array('response'=>409,'back_link'=>true));
        return;
    }
	if ( 'ea_booking' === $post->post_type && ea_booking_integrated( $post_id ) ) {
		$result = ea_booking_admin_save( $post_id, $values );
		if ( is_wp_error( $result ) ) wp_die( esc_html( $result->get_error_message() ), 'Rezervasyon kaydedilemedi', array( 'response' => 409, 'back_link' => true ) );
		return;
	}
	if ('ea_program' === $post->post_type) {
        $result=ea_coach_program_admin_save($post_id,$values);
        if(is_wp_error($result))wp_die(esc_html($result->get_error_message()),'Eğitim kaydedilemedi',array('response'=>409,'back_link'=>true));
        unset($values['trainer']);
    }
    if('ea_venue'===$post->post_type&&ea_rental_venue($post_id)&&array_key_exists('price',$values)){
        $r=ea_rental_set_price($post_id,$values['price']);
        if(is_wp_error($r))wp_die(esc_html($r->get_error_message()),'Fiyat kaydedilemedi',array('response'=>400,'back_link'=>true));unset($values['price']);
    }
	$locks = array();
	if ( 'ea_booking' === $post->post_type ) {
		$locks = ea_calendar_admin_guard( $post_id, $values );
		if ( is_wp_error( $locks ) ) wp_die( esc_html( $locks->get_error_message() ), 'Rezervasyon kaydedilemedi', array( 'response' => 409, 'back_link' => true ) );
	}
	try {
	foreach ( $fields as $key => $field ) {
		if ( ! array_key_exists( $key, $values ) ) continue;
		$value = is_array( $values[ $key ] ) ? '' : trim( (string) $values[ $key ] );
        if ('price' === $key && (ea_fitness_offer($post_id) || 'ea_program' === $post->post_type)) {
            try { ea_member_admin(); $value=ea_sales_decimal($value,true); }
            catch(Throwable $e){wp_die(esc_html($e->getMessage()),'Fiyat kaydedilemedi',array('response'=>400,'back_link'=>true));}
            update_post_meta($post_id,'_ea_price',$value);continue;
        }
		if ( 'textarea' === $field[1] || 'hours' === $field[1] ) $value = sanitize_textarea_field( $value );
		elseif ( 'email' === $field[1] ) $value = sanitize_email( $value );
		elseif ( 'date' === $field[1] && $value && ! ea_core_valid_date( $value ) ) $value = '';
		elseif ( 'number' === $field[1] ) {
			$value = absint( $value );
			$reference_types = array( 'trainer' => 'ea_trainer', 'program' => 'ea_program', 'package' => 'ea_package', 'membership' => 'ea_membership' );
			if ( $value && isset( $reference_types[ $key ] ) && $reference_types[ $key ] !== get_post_type( $value ) ) $value = 0;
		}
		elseif ( 0 === strpos( $field[1], 'select:' ) && ! in_array( $value, explode( ',', substr( $field[1], 7 ) ), true ) ) $value = '';
		else $value = sanitize_text_field( $value );
		update_post_meta( $post_id, '_ea_' . $key, $value );
	}
	} finally {
		ea_calendar_unlock( $locks );
	}
}
add_action( 'save_post', 'ea_core_save_meta' );

function ea_core_valid_date( $date ) {
	$d = DateTime::createFromFormat( '!Y-m-d', $date, wp_timezone() );
	return $d && $d->format( 'Y-m-d' ) === $date;
}

function ea_core_future_date( $date ) {
	return ea_core_valid_date( $date ) && $date >= wp_date( 'Y-m-d' );
}

function ea_core_schedule( $resource_id, $date, $include_elapsed = false ) {
	if ( ! ea_core_valid_date( $date ) ) return array();
	$hours = get_post_meta( $resource_id, '_ea_hours', true );
	$duration = absint( get_post_meta( $resource_id, '_ea_duration', true ) );
	if ( $duration < 15 || $duration > 480 ) return array();
	$step = 'ea_venue' === get_post_type( $resource_id ) ? ((int)get_post_meta($resource_id,'_ea_slot_step',true)===30?30:60) : 30;
	$day_keys = array( 'mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun' );
	$day_aliases = array( 'mon' => 'mon', 'pzt' => 'mon', 'tue' => 'tue', 'sal' => 'tue', 'wed' => 'wed', 'car' => 'wed', 'çar' => 'wed', 'thu' => 'thu', 'per' => 'thu', 'fri' => 'fri', 'cum' => 'fri', 'sat' => 'sat', 'cmt' => 'sat', 'sun' => 'sun', 'paz' => 'sun' );
	$weekday = (int) ( new DateTime( $date, wp_timezone() ) )->format( 'N' ) - 1;
	$slots = array();
	foreach ( preg_split( '/\r\n|\r|\n/', (string) $hours ) as $line ) {
		if ( ! preg_match( '/^\s*([a-zç]{3})\s+((?:[01]\d|2[0-3]):[0-5]\d)\s*-\s*((?:[01]\d|2[0-3]):[0-5]\d)\s*$/iu', $line, $match ) ) continue;
		$day_name = function_exists( 'mb_strtolower' ) ? mb_strtolower( $match[1], 'UTF-8' ) : strtolower( strtr( $match[1], array( 'Ç' => 'ç', 'İ' => 'i' ) ) );
		if ( ! isset( $day_aliases[ $day_name ] ) || $day_aliases[ $day_name ] !== $day_keys[ $weekday ] ) continue;
		$start = strtotime( $match[2] ); $end = strtotime( $match[3] );
		if ( false === $start || false === $end || $end <= $start ) continue;
		for ( $time = $start; $time + $duration * MINUTE_IN_SECONDS <= $end; $time += $step * MINUTE_IN_SECONDS ) {
			$slot_start = gmdate( 'H:i', $time );
			if ( ! $include_elapsed && $date === wp_date( 'Y-m-d' ) && $slot_start <= wp_date( 'H:i' ) ) continue;
			$slots[] = array( 'start' => $slot_start, 'end' => gmdate( 'H:i', $time + $duration * MINUTE_IN_SECONDS ) );
		}
	}
	$unique = array();
	foreach ( $slots as $slot ) $unique[ $slot['start'] . '|' . $slot['end'] ] = $slot;
	return array_values( $unique );
}

function ea_core_is_available( $resource_id, $date, $start, $end ) {
	$slots = ea_core_schedule( $resource_id, $date );
	$valid = false;
	foreach ( $slots as $slot ) if ( $slot['start'] === $start && $slot['end'] === $end ) $valid = true;
	if ( ! $valid ) return false;
	return ! ea_calendar_overlaps( ea_calendar_busy( $resource_id, $date, $date ), $date, $start, $end );
}

function ea_core_ajax_slots() {
	foreach ( array( 'nonce', 'resource', 'date' ) as $key ) if ( isset( $_POST[ $key ] ) && ! is_scalar( $_POST[ $key ] ) ) wp_send_json_error( array( 'message' => 'Geçersiz istek.' ), 400 );
	check_ajax_referer( 'ea_core_slots', 'nonce' );
	$resource_id = isset( $_POST['resource'] ) ? absint( $_POST['resource'] ) : 0;
	$date = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';
	$post = get_post( $resource_id );
	if ( ! $post || ! in_array( $post->post_type, array( 'ea_trainer', 'ea_program', 'ea_venue' ), true ) || 'publish' !== $post->post_status || ! ea_core_future_date( $date ) ) wp_send_json_error( array( 'message' => 'Geçerli bir salon, eğitim veya antrenör seçin.' ) );
	$slots = array_values( array_filter( ea_core_schedule( $resource_id, $date ), function ( $slot ) use ( $resource_id, $date ) { return ea_core_is_available( $resource_id, $date, $slot['start'], $slot['end'] ); } ) );
	wp_send_json_success( array( 'slots' => $slots ) );
}
add_action( 'wp_ajax_ea_core_slots', 'ea_core_ajax_slots' );
add_action( 'wp_ajax_nopriv_ea_core_slots', 'ea_core_ajax_slots' );

function ea_core_enqueue() {
	wp_enqueue_style( 'ea-core', EA_CORE_URL . 'assets/ea-core.css', array(), EA_CORE_VERSION );
	wp_enqueue_script( 'ea-core', EA_CORE_URL . 'assets/ea-core.js', array(), (string) filemtime( EA_CORE_DIR . 'assets/ea-core.js' ), true );
	wp_localize_script( 'ea-core', 'eaCore', array( 'ajaxUrl' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'ea_core_slots' ), 'loading' => 'Uygun saatler yükleniyor…', 'choose' => 'Uygun bir saat seçin', 'empty' => 'Bu tarihte tanımlı uygun saat bulunamadı.' ) );
}
add_action( 'wp_enqueue_scripts', 'ea_core_enqueue' );

function ea_core_shortcode_listing( $atts ) {
	$atts = shortcode_atts( array( 'type' => 'ea_program', 'limit' => 20 ), $atts, 'ea_list' );
	$allowed = array( 'ea_trainer', 'ea_program', 'ea_package', 'ea_venue', 'ea_amenity', 'ea_membership', 'ea_announcement' );
	if ( ! in_array( $atts['type'], $allowed, true ) ) return '';
	$query = new WP_Query( array( 'post_type' => $atts['type'], 'post_status' => 'publish', 'posts_per_page' => min( 50, max( 1, absint( $atts['limit'] ) ) ), 'orderby' => 'date', 'order' => 'DESC' ) );
	if ( ! $query->have_posts() ) return '<p class="ea-empty">Bu bölüm için henüz yayımlanmış bilgi bulunmuyor.</p>';
	$html = '<div class="ea-grid">';
	while ( $query->have_posts() ) {
		$query->the_post();
        if ('ea_membership' === get_post_type() && ea_fitness_offer(get_the_ID())) continue;
		$html .= '<article class="ea-card">';
		if ( has_post_thumbnail() ) $html .= '<a class="ea-card-image" href="' . esc_url( get_permalink() ) . '">' . get_the_post_thumbnail( get_the_ID(), 'medium' ) . '</a>';
		$html .= '<div class="ea-card-body"><h3><a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a></h3>';
		$html .= '<div class="ea-card-text">' . wp_kses_post( wpautop( get_the_excerpt() ? get_the_excerpt() : wp_trim_words( get_the_content(), 32 ) ) ) . '</div>';
		if ( in_array( get_post_type(), array( 'ea_program', 'ea_venue' ), true ) ) $html .= '<a class="ea-button" href="' . esc_url( ea_core_page_url( 'rezervasyon' ) ) . '">Rezervasyon talebi</a>';
		$html .= '</div></article>';
	}
	wp_reset_postdata();
	return $html . '</div>';
}
add_shortcode( 'ea_list', 'ea_core_shortcode_listing' );

function ea_core_single_details( $content ) {
	if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) return $content;
	$type = get_post_type();
	$id = get_the_ID();
	$rows = array();
	$add = function ( $label, $key ) use ( $id, &$rows ) {
		$value = get_post_meta( $id, '_ea_' . $key, true );
		if ( '' !== $value && null !== $value ) $rows[] = array( $label, nl2br( esc_html( $value ) ) );
	};
	if ( 'ea_trainer' === $type ) {
		$add( 'Uzmanlık alanları', 'expertise' ); $add( 'Eğitim bilgileri', 'education' ); $add( 'Sertifikalar ve belgeler', 'certificates' );
		$add( 'Çalıştığı antrenman türleri', 'training_types' ); $add( 'Uygunluk takvimi', 'hours' );
		$rows[] = array( 'Randevu', '<a href="' . esc_url( ea_core_page_url( 'rezervasyon' ) ) . '">Rezervasyon talebi gönderin</a>' );
	} elseif ( 'ea_program' === $type ) {
		$kind = get_post_meta( $id, '_ea_type', true );
		if ( $kind ) $rows[] = array( 'Eğitim türü', esc_html( 'individual' === $kind ? 'Bireysel' : 'Grup' ) );
		$add( 'Seans süresi', 'duration' ); $add( 'Seans ücreti', 'price' ); $add( 'Uygun gün ve saatler', 'hours' );
		$trainer_id = absint( get_post_meta( $id, '_ea_trainer', true ) ); $package_id = absint( get_post_meta( $id, '_ea_package', true ) );
		if ( $trainer_id && 'ea_trainer' === get_post_type( $trainer_id ) ) $rows[] = array( 'Antrenör', '<a href="' . esc_url( get_permalink( $trainer_id ) ) . '">' . esc_html( get_the_title( $trainer_id ) ) . '</a>' );
		if ( $package_id && 'ea_package' === get_post_type( $package_id ) ) $rows[] = array( 'İlişkili paket', '<a href="' . esc_url( get_permalink( $package_id ) ) . '">' . esc_html( get_the_title( $package_id ) ) . '</a>' );
		$rows[] = array( 'Rezervasyon', '<a href="' . esc_url( ea_core_page_url( 'rezervasyon' ) ) . '">Rezervasyon talebi gönderin</a>' );
	} elseif ( 'ea_package' === $type ) {
		$add( 'Seans adedi', 'session_count' ); $add( 'Seans süresi', 'duration' ); $add( 'Paket ücreti', 'price' ); $add( 'Paket kapsamı', 'details' );
	} elseif ( 'ea_venue' === $type ) {
		$kind = get_post_meta( $id, '_ea_venue_type', true );
		if ( $kind ) $rows[] = array( 'Salon türü', esc_html( 'basketball' === $kind ? 'Basketbol' : 'Voleybol' ) );
		$add( 'Mevcut olanaklar', 'facilities' ); $add( 'Kiralama bilgileri ve koşulları', 'rental_info' ); $add( 'Kiralama ücreti', 'price' );
		$add( 'Kullanılabilir gün ve saatler', 'hours' );
		$referee = get_post_meta( $id, '_ea_referee_available', true );
		if ( 'available' === $referee ) { $rows[] = array( 'Hakem hizmeti', 'Talep edilebilir; uygunluk tesis tarafından teyit edilir.' ); $add( 'Hakem hizmet ücreti', 'referee_price' ); }
		elseif ( 'unavailable' === $referee ) $rows[] = array( 'Hakem hizmeti', 'Sunulmuyor' );
		$rows[] = array( 'Rezervasyon', '<a href="' . esc_url( ea_core_page_url( 'rezervasyon' ) ) . '">Rezervasyon talebi gönderin</a>' );
	} elseif ( 'ea_amenity' === $type ) $add( 'Çalışma saatleri', 'hours' );
	elseif ( 'ea_membership' === $type ) {
        if(ea_fitness_offer($id))$rows[]=array('Üyelik ücreti',esc_html(ea_sales_price_label(get_post_meta($id,'_ea_price',true))));
        else $add( 'Üyelik ücreti', 'price' );
        $add( 'Üyelik süresi', 'duration' ); $add( 'Üyelik koşulları', 'terms' );
    }
	elseif ( 'ea_announcement' === $type ) $add( 'Duyuru tarihi', 'event_date' );
	if ( ! $rows ) return $content;
	$html = '<dl class="ea-details">';
	foreach ( $rows as $row ) $html .= '<div class="ea-detail"><dt>' . esc_html( $row[0] ) . '</dt><dd>' . $row[1] . '</dd></div>';
	$html .= '</dl>';
	return $content . $html;
}
add_filter( 'the_content', 'ea_core_single_details', 20 );

function ea_core_page_url( $slug ) {
	$page = get_page_by_path( $slug, OBJECT, 'page' );
	return $page ? get_permalink( $page ) : home_url( '/' . trim( $slug, '/' ) . '/' );
}

function ea_core_resource_options() {
	$types = array( 'ea_venue' => 'Salon', 'ea_program' => 'Eğitim', 'ea_trainer' => 'Antrenör' );
	$options = '';
	foreach ( $types as $type => $label ) {
		$posts = get_posts( array( 'post_type' => $type, 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
		if ( ! $posts ) continue;
		$options .= '<optgroup label="' . esc_attr( $label ) . '">';
		foreach ( $posts as $post ) {
			$referee = get_post_meta( $post->ID, '_ea_referee_available', true );
			$options .= '<option value="' . esc_attr( $post->ID ) . '" data-referee="' . esc_attr( $referee ) . '">' . esc_html( $post->post_title ) . '</option>';
		}
		$options .= '</optgroup>';
	}
	return $options;
}

function ea_core_booking_form() {
	$resources = ea_core_resource_options();
	if ( ! $resources ) return '<p class="ea-empty">Rezervasyon için önce yönetim panelinde salon, eğitim veya antrenör bilgisi ekleyin ve yayımlayın.</p>';
	$notice = isset( $_GET['ea_result'] ) ? sanitize_key( wp_unslash( $_GET['ea_result'] ) ) : '';
	$html = ea_core_notice( $notice );
	$html .= '<form class="ea-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="ea_core_booking"><input type="hidden" name="redirect_to" value="' . esc_url( get_permalink() ) . '">';
	$html .= wp_nonce_field( 'ea_core_booking', 'ea_nonce', true, false );
	$html .= '<p class="ea-trap"><label>Web sitesi<input type="text" name="website" tabindex="-1" autocomplete="off"></label></p>';
	$html .= '<p><label for="ea-resource">Rezervasyon türü ve kaynağı <span aria-hidden="true">*</span></label><select required id="ea-resource" name="resource"><option value="">Seçin</option>' . $resources . '</select></p>';
	$html .= '<p><label for="ea-date">Tarih <span aria-hidden="true">*</span></label><input required type="date" id="ea-date" name="booking_date" min="' . esc_attr( wp_date( 'Y-m-d' ) ) . '"></p>';
	$html .= '<p><label for="ea-slot">Uygun saat <span aria-hidden="true">*</span></label><select required disabled id="ea-slot" name="slot"><option value="">Önce kaynak ve tarih seçin</option></select><span class="ea-slot-status" aria-live="polite"></span></p>';
	$html .= '<p><label for="ea-name">Ad soyad <span aria-hidden="true">*</span></label><input required maxlength="120" id="ea-name" name="full_name" autocomplete="name"></p>';
	$html .= '<p><label for="ea-phone">Telefon <span aria-hidden="true">*</span></label><input required maxlength="40" id="ea-phone" name="phone" autocomplete="tel"></p>';
	$html .= '<p><label for="ea-email">E-posta</label><input type="email" maxlength="190" id="ea-email" name="email" autocomplete="email"></p>';
	$html .= '<p class="ea-referee-field" hidden><label><input type="checkbox" name="referee" value="1"> Hakem ayarlanmasını talep ediyorum</label><span class="ea-referee-note">Hakem hizmetinin uygunluğu ve ücreti tesis tarafından teyit edilir.</span></p>';
	$html .= '<p><label for="ea-note">Açıklama (isteğe bağlı)</label><textarea maxlength="1000" rows="4" id="ea-note" name="note"></textarea></p>';
	$html .= '<p><button type="submit" class="ea-button">Rezervasyon talebi gönder</button></p><p class="ea-privacy-note">Talebiniz onaylanana kadar rezervasyon kesinleşmez.</p></form>';
	return $html;
}
add_shortcode( 'ea_booking_form', 'ea_core_booking_form' );

function ea_core_membership_form() {
	$memberships = get_posts( array( 'post_type' => 'ea_membership', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
	$options = '<option value="">Belirtmek istemiyorum</option>';
	foreach ( $memberships as $membership ) if (!ea_fitness_offer($membership->ID)) $options .= '<option value="' . esc_attr( $membership->ID ) . '">' . esc_html( $membership->post_title ) . '</option>';
	$html = ea_core_notice( isset( $_GET['ea_result'] ) ? sanitize_key( wp_unslash( $_GET['ea_result'] ) ) : '' );
	$html .= '<form class="ea-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="ea_core_application"><input type="hidden" name="redirect_to" value="' . esc_url( get_permalink() ) . '">' . wp_nonce_field( 'ea_core_application', 'ea_nonce', true, false );
	$html .= '<p class="ea-trap"><label>Web sitesi<input type="text" name="website" tabindex="-1" autocomplete="off"></label></p>';
	if ( $memberships ) $html .= '<p><label for="ea-membership">İlgilendiğiniz üyelik seçeneği</label><select id="ea-membership" name="membership">' . $options . '</select></p>';
	$html .= '<p><label for="ea-app-name">Ad soyad <span aria-hidden="true">*</span></label><input required maxlength="120" id="ea-app-name" name="full_name" autocomplete="name"></p><p><label for="ea-app-phone">Telefon <span aria-hidden="true">*</span></label><input required maxlength="40" id="ea-app-phone" name="phone" autocomplete="tel"></p><p><label for="ea-app-email">E-posta</label><input type="email" maxlength="190" id="ea-app-email" name="email" autocomplete="email"></p><p><button type="submit" class="ea-button">Üyelik başvurusu gönder</button></p><p class="ea-privacy-note">Başvurunuz alınır; üyelik, ödeme veya yenileme işlemi bu form üzerinden otomatik olarak etkinleştirilmez.</p></form>';
	return $html;
}
add_shortcode( 'ea_membership_form', 'ea_core_membership_form' );

function ea_core_notice( $result ) {
	$messages = array( 'booking_received' => 'Rezervasyon talebiniz alındı. Tesis onayı sonrası sizinle iletişime geçilecektir.', 'application_received' => 'Üyelik başvurunuz alındı. Tesis ekibi sizinle iletişime geçecektir.', 'invalid' => 'Form doğrulanamadı. Lütfen alanları kontrol edip tekrar deneyin.', 'unavailable' => 'Seçtiğiniz saat artık uygun değil veya çalışma takviminde tanımlı değil. Başka bir saat seçin.', 'error' => 'Talebiniz şu anda alınamadı. Lütfen daha sonra tekrar deneyin.' );
	if ( ! isset( $messages[ $result ] ) ) return '';
	$class = in_array( $result, array( 'booking_received', 'application_received' ), true ) ? 'success' : 'error';
	return '<div class="ea-notice ea-notice-' . esc_attr( $class ) . '" role="status">' . esc_html( $messages[ $result ] ) . '</div>';
}

function ea_core_redirect_result( $result ) {
	$redirect = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : home_url( '/' );
	$redirect = wp_validate_redirect( $redirect, home_url( '/' ) );
	wp_safe_redirect( add_query_arg( 'ea_result', $result, $redirect ) );
	exit;
}

function ea_core_lock_name( $resource_id, $date ) {
	global $wpdb;
	return 'ea_' . substr( md5( $wpdb->prefix . '|' . $resource_id . '|' . $date ), 0, 48 );
}

function ea_core_handle_booking() {
	if ( ! is_user_logged_in() ) ea_core_redirect_result( 'login_required' );
	foreach ( array( 'ea_nonce', 'resource', 'booking_date', 'slot', 'full_name', 'phone', 'email', 'note', 'redirect_to', 'membership_id', 'request_key', 'trainer_id' ) as $key ) {
		if ( isset( $_POST[ $key ] ) && ! is_scalar( $_POST[ $key ] ) ) ea_core_redirect_result( 'invalid' );
	}
	if ( ! isset( $_POST['ea_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ea_nonce'] ) ), 'ea_core_booking' ) ) ea_core_redirect_result( 'invalid' );
	if ( ! empty( $_POST['website'] ) ) ea_core_redirect_result( 'booking_received' );
	$resource_id = isset( $_POST['resource'] ) ? absint( $_POST['resource'] ) : 0;
	$date = isset( $_POST['booking_date'] ) ? sanitize_text_field( wp_unslash( $_POST['booking_date'] ) ) : '';
	$slot = isset( $_POST['slot'] ) ? sanitize_text_field( wp_unslash( $_POST['slot'] ) ) : '';
	if ( ! preg_match( '/^(\d{2}:\d{2})\|(\d{2}:\d{2})$/', $slot, $times ) || ! ea_core_future_date( $date ) ) ea_core_redirect_result( 'invalid' );
	$resource = get_post( $resource_id );
	if ( ! $resource || ! in_array( $resource->post_type, array( 'ea_venue', 'ea_program', 'ea_trainer' ), true ) || 'publish' !== $resource->post_status ) ea_core_redirect_result( 'invalid' );
	$name = isset( $_POST['full_name'] ) ? sanitize_text_field( wp_unslash( $_POST['full_name'] ) ) : '';
	$phone = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
	$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
	if ( '' === $name || '' === $phone || ( ! empty( $_POST['email'] ) && ! is_email( $email ) ) || strlen( $name ) > 120 || strlen( $phone ) > 40 ) ea_core_redirect_result( 'invalid' );
	$referee = ! empty( $_POST['referee'] ) && 'ea_venue' === $resource->post_type && in_array( get_post_meta( $resource_id, '_ea_referee_available', true ), array( 'available', 'unset' ), true );
	$note = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';
	$membership_id = isset( $_POST['membership_id'] ) ? absint( $_POST['membership_id'] ) : 0;
    $trainer_id=absint($_POST['trainer_id']??0);
    if('ea_program'===$resource->post_type&&!$trainer_id)ea_core_redirect_result('invalid');
	$at = ea_booking_utc( $date, $times[1] );
	if ( is_wp_error( $at ) || true !== ea_membership_can_book( $membership_id, $resource_id, $at ) ) {
		// A completed retry is resolved by the shared service even when the last right is already reserved.
		if ( ! ea_booking_request_exists( get_current_user_id(), isset( $_POST['request_key'] ) ? (string) wp_unslash( $_POST['request_key'] ) : '' ) ) ea_core_redirect_result( 'membership_required' );
	}
	$booking_id = ea_calendar_create_booking( $resource, $date, $times[1], $times[2], array( '_ea_full_name' => $name, '_ea_phone' => $phone, '_ea_email' => $email, '_ea_referee' => $referee ? 'İsteniyor' : '', '_ea_note' => $note ), $membership_id, isset( $_POST['request_key'] ) ? (string) wp_unslash( $_POST['request_key'] ) : '', $trainer_id );
	if ( is_wp_error( $booking_id ) ) ea_core_redirect_result( 'unavailable' === $booking_id->get_error_code() ? 'unavailable' : 'membership_required' );
	if ( empty( $GLOBALS['ea_booking_replayed'] ) ) {
	wp_mail( get_option( 'admin_email' ), 'Yeni rezervasyon talebi — Eren Athletic', "Kaynak: {$resource->post_title}\nTarih: {$date}\nSaat: {$times[1]}–{$times[2]}\nAd soyad: {$name}\nTelefon: {$phone}\nE-posta: {$email}\nHakem: " . ( $referee ? 'İsteniyor' : 'İstenmiyor' ) . "\n\nTalebi WordPress yönetim panelindeki Rezervasyonlar bölümünden yönetin." );
	}
	$_POST['redirect_to'] = add_query_arg( array( 'ea_pt_resource' => $resource_id, 'ea_calendar_date' => $date ), isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : ea_core_page_url( 'rezervasyon' ) );
	ea_core_redirect_result( 'booking_received' );
}
add_action( 'admin_post_nopriv_ea_core_booking', 'ea_core_handle_booking' );
add_action( 'admin_post_ea_core_booking', 'ea_core_handle_booking' );

function ea_core_handle_application() {
    foreach(array('ea_nonce','membership','full_name','phone','email','website')as$field)if(isset($_POST[$field])&&!is_scalar($_POST[$field]))ea_core_redirect_result('invalid');
    if(ea_fitness_offer(absint($_POST['membership']??0)))ea_core_redirect_result('invalid');
	if ( ! isset( $_POST['ea_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ea_nonce'] ) ), 'ea_core_application' ) ) ea_core_redirect_result( 'invalid' );
	if ( ! empty( $_POST['website'] ) ) ea_core_redirect_result( 'application_received' );
	$name = isset( $_POST['full_name'] ) ? sanitize_text_field( wp_unslash( $_POST['full_name'] ) ) : '';
	$phone = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
	$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
	$membership = isset( $_POST['membership'] ) ? absint( $_POST['membership'] ) : 0;
	if ( '' === $name || '' === $phone || ( ! empty( $_POST['email'] ) && ! is_email( $email ) ) ) ea_core_redirect_result( 'invalid' );
	if ( $membership && 'publish' !== get_post_status( $membership ) || $membership && 'ea_membership' !== get_post_type( $membership ) ) $membership = 0;
	$id = wp_insert_post( array( 'post_type' => 'ea_application', 'post_status' => 'private', 'post_title' => $name . ' — ' . current_time( 'mysql' ), 'meta_input' => array( '_ea_status' => 'pending', '_ea_membership' => $membership, '_ea_full_name' => $name, '_ea_phone' => $phone, '_ea_email' => $email ) ), true );
	if ( is_wp_error( $id ) || ! $id ) ea_core_redirect_result( 'error' );
	wp_mail( get_option( 'admin_email' ), 'Yeni üyelik başvurusu — Eren Athletic', "Ad soyad: {$name}\nTelefon: {$phone}\nE-posta: {$email}\nBaşvuru WordPress yönetim panelindeki Üyelik Başvuruları bölümündedir." );
	ea_core_redirect_result( 'application_received' );
}
add_action( 'admin_post_nopriv_ea_core_application', 'ea_core_handle_application' );
add_action( 'admin_post_ea_core_application', 'ea_core_handle_application' );

function ea_core_admin_columns( $columns ) {
	$columns['ea_status'] = 'Durum';
	if ( 'ea_booking' === get_current_screen()->post_type ) $columns['ea_date'] = 'Rezervasyon saati';
	return $columns;
}
function ea_core_admin_column_content( $column, $post_id ) {
	if ( 'ea_status' === $column ) {
		$status = get_post_meta( $post_id, '_ea_status', true );
		$labels = array( 'pending' => 'Beklemede', 'confirmed' => 'Onaylandı', 'cancelled' => 'İptal edildi', 'contacted' => 'İletişime geçildi', 'accepted' => 'Kabul edildi', 'rejected' => 'Reddedildi' );
		echo esc_html( isset( $labels[ $status ] ) ? $labels[ $status ] : 'Beklemede' );
	}
	if ( 'ea_date' === $column ) echo esc_html( get_post_meta( $post_id, '_ea_booking_date', true ) . ' ' . get_post_meta( $post_id, '_ea_start_time', true ) . '–' . get_post_meta( $post_id, '_ea_end_time', true ) );
}
add_filter( 'manage_ea_booking_posts_columns', 'ea_core_admin_columns' );
add_filter( 'manage_ea_application_posts_columns', 'ea_core_admin_columns' );
add_action( 'manage_ea_booking_posts_custom_column', 'ea_core_admin_column_content', 10, 2 );
add_action( 'manage_ea_application_posts_custom_column', 'ea_core_admin_column_content', 10, 2 );

function ea_core_default_logo( $html ) {
	if ( $html ) return $html;
	$path = WP_CONTENT_DIR . '/eren-athletic/22b5e36f-5228-4489-93ae-13564afcb22a.png';
	if ( ! file_exists( $path ) ) return $html;
	$url = content_url( '/eren-athletic/22b5e36f-5228-4489-93ae-13564afcb22a.png' );
	return '<img class="custom-logo ea-brand-logo" src="' . esc_url( $url ) . '" alt="' . esc_attr( get_bloginfo( 'name' ) ) . '" width="1254" height="1254" decoding="async">';
}
add_filter( 'get_custom_logo', 'ea_core_default_logo' );

function ea_core_activation() {
	ea_core_content_types();
	ea_core_install_logo();
	$pages = array(
		'pt-egitimleri' => array( 'PT Eğitimleri', "[ea_list type=\"ea_program\"]\n[ea_list type=\"ea_package\"]" ),
		'antrenorler' => array( 'Antrenörler', '[ea_list type="ea_trainer"]' ),
		'salonlar' => array( 'Salonlar', '[ea_list type="ea_venue"]' ),
		'tesis-olanaklari' => array( 'Tesis Olanakları', '[ea_list type="ea_amenity"]' ),
		'uyelik' => array( 'Üyelik', "[ea_list type=\"ea_membership\"]\n[ea_membership_form]" ),
		'rezervasyon' => array( 'Rezervasyon', '[ea_booking_form]' ),
		'duyurular' => array( 'Duyurular', '[ea_list type="ea_announcement"]' ),
	);
	$menu_items = array();
	foreach ( $pages as $slug => $page ) {
		$existing = get_page_by_path( $slug, OBJECT, 'page' );
		$id = $existing ? $existing->ID : wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $page[0], 'post_name' => $slug, 'post_content' => $page[1] ) );
		if ( $id && ! is_wp_error( $id ) ) $menu_items[] = array( 'title' => $page[0], 'url' => get_permalink( $id ) );
	}
	ea_core_localize_theme_demo_content();
	if ( ! has_nav_menu( 'primary_menu' ) && $menu_items ) {
		$menu_id = wp_create_nav_menu( 'Eren Athletic Ana Menü' );
		if ( ! is_wp_error( $menu_id ) ) {
			foreach ( $menu_items as $item ) wp_update_nav_menu_item( $menu_id, 0, array( 'menu-item-title' => $item['title'], 'menu-item-url' => $item['url'], 'menu-item-status' => 'publish', 'menu-item-type' => 'custom' ) );
			$locations = get_theme_mod( 'nav_menu_locations', array() );
			$locations['primary_menu'] = $menu_id;
			set_theme_mod( 'nav_menu_locations', $locations );
		}
	}
	$seed = array( 'ea_venue' => array( 'Basketbol Sahası 1', 'Basketbol Sahası 2', 'Voleybol Sahası' ), 'ea_amenity' => array( 'Soyunma Odaları', 'Duşlar', 'Otopark', 'Kafe' ) );
	foreach ( $seed as $type => $titles ) foreach ( $titles as $title ) {
		$found = get_page_by_title( $title, OBJECT, $type );
		if ( ! $found ) {
			$id = wp_insert_post( array( 'post_type' => $type, 'post_status' => 'publish', 'post_title' => $title, 'post_content' => '' ) );
			if ( $id && 'ea_venue' === $type ) update_post_meta( $id, '_ea_venue_type', false !== strpos( $title, 'Basketbol' ) ? 'basketball' : 'volleyball' );
		}
	}
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'ea_core_activation' );

function ea_core_localize_theme_demo_content() {
	$theme_mods = array(
		'wpdevart_fitness_and_gym_wda_custom_homepage_banner_title' => array( 'FitPro: Fitness & Gym', 'Eren Athletic' ),
		'wpdevart_fitness_and_gym_wda_custom_homepage_banner_short_description' => array( 'Push Your Limits, Find Your Strength!', 'Spor, gelişim ve hareket için' ),
		'wpdevart_fitness_and_gym_wda_custom_homepage_banner_content' => array( 'Step into FitPro, your ultimate destination for fitness and well-being. Our gym is designed to support all types of workouts, from strength training to cardio. With expert guidance and a motivating atmosphere, your fitness journey becomes an empowering experience.', 'Eren Athletic’te PT eğitimlerini, salon kiralama seçeneklerini ve üyelik başvurularını inceleyebilirsiniz.' ),
		'wpdevart_fitness_and_gym_wda_custom_homepage_banner_sliding_first_text' => array( 'Skilled Trainers', '' ),
		'wpdevart_fitness_and_gym_wda_custom_homepage_banner_sliding_second_text' => array( 'Best Equipment', '' ),
		'wpdevart_fitness_and_gym_wda_custom_homepage_banner_sliding_third_text' => array( 'Great Atmosphere', '' ),
		'wpdevart_fitness_and_gym_wda_custom_homepage_banner_sliding_fourth_text' => array( 'Gifts & Discounts', '' ),
		'wpdevart_fitness_and_gym_wda_custom_homepage_banner_first_button_text' => array( 'Our Shop', 'Rezervasyon' ),
		'wpdevart_fitness_and_gym_wda_custom_homepage_banner_first_button_url' => array( '#', ea_core_page_url( 'rezervasyon' ) ),
		'wpdevart_fitness_and_gym_wda_custom_homepage_banner_second_button_text' => array( 'Our Programs', 'PT Eğitimleri' ),
		'wpdevart_fitness_and_gym_wda_custom_homepage_banner_second_button_url' => array( '#', ea_core_page_url( 'pt-egitimleri' ) ),
		'wpdevart_fitness_and_gym_wda_custom_homepage_call_action_title' => array( 'Call to Action', 'Eğitim ve salon seçeneklerini keşfedin' ),
		'wpdevart_fitness_and_gym_wda_custom_homepage_call_action_desc' => array( 'A brief description of the section below.', 'PT eğitimi, salon kiralama ve üyelik bilgilerine göz atın.' ),
		'wpdevart_fitness_and_gym_wda_custom_homepage_call_action_sub_title' => array( 'Call to Action Title', 'Size uygun seçeneği inceleyin' ),
		'wpdevart_fitness_and_gym_wda_custom_homepage_call_action_text' => array( 'This is an important post or event that visitors to our site should pay attention to. Any important content can be added here. This is test content, so feel free to add your own text here. If you are interested or want to know more details, you can click the link below and check more details.', 'Eğitim programlarını, salonları ve üyelik seçeneklerini inceleyin; rezervasyon veya başvuru talebinizi çevrimiçi iletin.' ),
		'wpdevart_fitness_and_gym_wda_custom_homepage_call_action_button_text' => array( 'Learn More', 'Rezervasyon ve başvuru' ),
		'wpdevart_fitness_and_gym_wda_custom_homepage_call_action_button_url' => array( '#', ea_core_page_url( 'rezervasyon' ) ),
	);
	foreach ( $theme_mods as $key => $values ) if ( get_theme_mod( $key ) === $values[0] ) set_theme_mod( $key, $values[1] );
	if ( ! get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => 1 ) ) && 0 === (int) get_theme_mod( 'wpdevart_fitness_and_gym_wda_custom_homepage_hide_latest_post_section', 1 ) ) {
		set_theme_mod( 'wpdevart_fitness_and_gym_wda_custom_homepage_hide_latest_post_section', 1 );
	}
}

function ea_core_install_logo() {
	if ( has_custom_logo() ) return;
	$source = WP_CONTENT_DIR . '/eren-athletic/22b5e36f-5228-4489-93ae-13564afcb22a.png';
	if ( ! is_readable( $source ) ) return;
	$existing = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'numberposts' => 1, 'meta_key' => '_ea_source_logo', 'meta_value' => 'eren-athletic' ) );
	if ( $existing ) {
		set_theme_mod( 'custom_logo', $existing[0]->ID );
		return;
	}
	$uploads = wp_upload_dir();
	if ( ! empty( $uploads['error'] ) || ! wp_mkdir_p( $uploads['path'] ) ) return;
	$temp_base = tempnam( $uploads['path'], 'ea-logo-' );
	if ( ! $temp_base ) return;
	@unlink( $temp_base );
	$temp_file = $temp_base . '.png';
	if ( ! copy( $source, $temp_file ) ) return;
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$attachment_id = media_handle_sideload( array( 'name' => 'eren-athletic-logo.png', 'tmp_name' => $temp_file ), 0, 'Eren Athletic logo' );
	if ( is_wp_error( $attachment_id ) ) {
		if ( file_exists( $temp_file ) ) @unlink( $temp_file );
		return;
	}
	update_post_meta( $attachment_id, '_ea_source_logo', 'eren-athletic' );
	set_theme_mod( 'custom_logo', $attachment_id );
}

function ea_core_deactivation() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'ea_core_deactivation' );
