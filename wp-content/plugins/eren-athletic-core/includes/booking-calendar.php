<?php
/** Availability-only calendar and shared reservation locking. */
if ( ! defined( 'ABSPATH' ) ) exit;

function ea_calendar_resource( $id ) {
    $p = get_post( $id );
    return $p && 'publish' === $p->post_status && in_array( $p->post_type, array( 'ea_venue', 'ea_program', 'ea_trainer' ), true ) ? $p : false;
}
// Read current SQL data after locking; do not reuse a cached availability result.
function ea_calendar_busy( $id, $from, $to, $exclude = 0 ) {
    global $wpdb;
    if(function_exists('ea_coach_busy'))return ea_coach_busy($id,$from,$to,$exclude);
    return $wpdb->get_results( $wpdb->prepare(
        "SELECT d.meta_value AS day, s.meta_value AS start, e.meta_value AS end
        FROM {$wpdb->posts} p
        JOIN {$wpdb->postmeta} r ON r.post_id=p.ID AND r.meta_key='_ea_resource'
        JOIN {$wpdb->postmeta} d ON d.post_id=p.ID AND d.meta_key='_ea_booking_date'
        JOIN {$wpdb->postmeta} s ON s.post_id=p.ID AND s.meta_key='_ea_start_time'
        JOIN {$wpdb->postmeta} e ON e.post_id=p.ID AND e.meta_key='_ea_end_time'
        JOIN {$wpdb->postmeta} t ON t.post_id=p.ID AND t.meta_key='_ea_status'
        WHERE p.post_type='ea_booking' AND p.post_status IN ('private','publish','draft','pending')
        AND r.meta_value=%s AND d.meta_value BETWEEN %s AND %s
        AND t.meta_value IN ('pending','confirmed') AND p.ID<>%d",
        (string) absint( $id ), $from, $to, absint( $exclude )
    ), ARRAY_A );
}
function ea_calendar_overlaps( $busy, $date, $start, $end ) {
    foreach ( $busy as $row ) if ( $row['day'] === $date && $start < $row['end'] && $end > $row['start'] ) return true;
    return false;
}
function ea_calendar_lock( $names ) {
    global $wpdb;
    $names = array_unique( $names ); sort( $names, SORT_STRING ); $held = array();
    foreach ( $names as $name ) {
        if ( 1 !== (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s,5)', $name ) ) ) {
            ea_calendar_unlock( $held ); return new WP_Error( 'lock', 'Rezervasyon şu anda işlenemiyor. Lütfen tekrar deneyin.' );
        }
        $held[] = $name;
    }
    return $held;
}
function ea_calendar_unlock( $names ) {
    global $wpdb;
    foreach ( array_reverse( $names ) as $name ) $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $name ) );
}
function ea_calendar_create_booking( $resource, $date, $start, $end, $contact, $membership_id = 0, $request_key = '', $trainer_id = 0 ) {
    return ea_booking_create_member( $resource, $date, $start, $end, $contact, $membership_id, $request_key, $trainer_id );
}
// Called only after the existing administrator capability and nonce checks.
function ea_calendar_admin_guard( $id, &$values ) {
    $record = ea_calendar_lock( array( 'ea_booking_' . md5( $GLOBALS['wpdb']->prefix . '|' . $id ) ) );
    if ( is_wp_error( $record ) ) return $record;
    clean_post_cache( $id );
    $old = array(); $next = array();
    foreach ( array( 'status', 'resource', 'booking_date', 'start_time', 'end_time' ) as $key ) {
        $old[ $key ] = (string) get_post_meta( $id, '_ea_' . $key, true );
        if ( isset( $values[ $key ] ) && ! is_scalar( $values[ $key ] ) ) { ea_calendar_unlock( $record ); return new WP_Error( 'invalid', 'Geçersiz rezervasyon alanı.' ); }
        $next[ $key ] = array_key_exists( $key, $values ) ? trim( (string) $values[ $key ] ) : $old[ $key ];
    }
    if ( ! in_array( $next['status'], array( 'pending', 'confirmed', 'cancelled' ), true ) || ! ctype_digit( $next['resource'] ) || ! ea_core_valid_date( $next['booking_date'] ) ) {
        ea_calendar_unlock( $record ); return new WP_Error( 'invalid', 'Kaynak, tarih veya durum geçersiz.' );
    }
    $resource = ea_calendar_resource( (int) $next['resource'] );
    if ( ! $resource ) { ea_calendar_unlock( $record ); return new WP_Error( 'invalid', 'Yayımlanmış bir rezervasyon kaynağı seçin.' ); }
    $old_context=ea_coach_record_context(ea_booking_meta($id));
    try{$context=(int)$next['resource']===(int)$old['resource']?$old_context:ea_coach_context((int)$next['resource']);}
    catch(Throwable $e){ea_calendar_unlock($record);return new WP_Error('mapping',$e->getMessage());}
    $names = ea_coach_lock_names((int)$next['resource'],$next['booking_date'],$context);
    if ( $old['resource'] && ea_core_valid_date( $old['booking_date'] ) ) $names=array_merge($names,ea_coach_lock_names((int)$old['resource'],$old['booking_date'],$old_context));
    $locks = ea_calendar_lock( $names );
    if ( is_wp_error( $locks ) ) { ea_calendar_unlock( $record ); return $locks; }
    $held = array_merge( $record, $locks );
    if ( in_array( $next['status'], array( 'pending', 'confirmed' ), true ) ) {
        $same_active = in_array( $old['status'], array( 'pending', 'confirmed' ), true );
        foreach ( array( 'resource', 'booking_date', 'start_time', 'end_time' ) as $key ) $same_active = $same_active && $old[ $key ] === $next[ $key ];
        // Keep existing active intervals editable after a program-duration migration.
        // New/moved/restored cancelled intervals still require the current schedule.
        $allowed = $same_active;
        foreach ( ea_core_schedule( $resource->ID, $next['booking_date'], $same_active ) as $slot ) {
            if ( $slot['start'] === $next['start_time'] && $slot['end'] === $next['end_time'] ) $allowed = true;
        }
        if ( ( ! $same_active && ! ea_core_future_date( $next['booking_date'] ) ) || ! $allowed || ea_calendar_overlaps( ea_calendar_busy( $resource->ID, $next['booking_date'], $next['booking_date'], $id ), $next['booking_date'], $next['start_time'], $next['end_time'] ) ) {
            ea_calendar_unlock( $held ); return new WP_Error( 'conflict', 'Bu saat başka bir rezervasyonla çakışıyor veya çalışma takvimine uygun değil. Değişiklik uygulanmadı.' );
        }
    }
    $values['resource_type'] = $resource->post_type;
    if(in_array($next['status'],array('pending','confirmed'),true)){
        foreach(array_filter(array_merge(array($context['trainer']),$context['venues']))as$shared){
            if(!ea_coach_within_hours($shared,$next['booking_date'],$next['start_time'],$next['end_time'])||ea_calendar_overlaps(ea_coach_busy($shared,$next['booking_date'],$next['booking_date'],$id),$next['booking_date'],$next['start_time'],$next['end_time'])){
                ea_calendar_unlock($held);return new WP_Error('conflict','Antrenör veya fiziksel kaynak bu aralıkta uygun değil.');
            }
        }
        if('ea_program'===$resource->post_type && (!$old['resource'] || $old['resource']!==$next['resource'])){
            ea_booking_write_meta($id,array('_ea_trainer_id'=>$context['trainer'],'_ea_physical_resources'=>wp_json_encode($context['venues'])));
        }
    }
    return $held;
}
// Treat an empty or unusable weekly schedule as undefined, rather than fully booked.
function ea_calendar_configured( $id ) {
    static $checked = array();
    $hours = trim( (string) get_post_meta( $id, '_ea_hours', true ) );
    $duration = absint( get_post_meta( $id, '_ea_duration', true ) );
    $key = $id . '|' . md5( $hours . '|' . $duration );
    if ( isset( $checked[ $key ] ) ) return $checked[ $key ];
    $checked[ $key ] = false;
    if ( ! $hours || $duration < 15 || $duration > 480 ) return false;
    $monday = new DateTimeImmutable( 'next monday', wp_timezone() );
    for ( $day = 0; $day < 7; $day++ ) {
        if ( ea_core_schedule( $id, $monday->modify( '+' . $day . ' days' )->format( 'Y-m-d' ), true ) ) { $checked[ $key ] = true; break; }
    }
    return $checked[ $key ];
}
function ea_calendar_day( $id, $date, $busy ) {
    if ( $date < wp_date( 'Y-m-d' ) ) return array( 'date' => $date, 'status' => 'past', 'slots' => array() );
    if ( ! ea_calendar_configured( $id ) ) return array( 'date' => $date, 'status' => 'undefined', 'slots' => array() );
    $slots = array(); $free = 0;
    foreach ( ea_core_schedule( $id, $date ) as $slot ) {
        $slot['available'] = ! ea_calendar_overlaps( $busy, $date, $slot['start'], $slot['end'] );
        if ( $slot['available'] ) $free++;
        $slots[] = $slot;
    }
    return array( 'date' => $date, 'status' => ! $slots ? 'closed' : ( 0 === $free ? 'full' : ( $free === count( $slots ) ? 'available' : 'partial' ) ), 'slots' => $slots );
}
function ea_calendar_ajax() {
    foreach ( array( 'nonce', 'resource', 'month', 'date' ) as $key ) if ( isset( $_POST[ $key ] ) && ! is_scalar( $_POST[ $key ] ) ) wp_send_json_error( array( 'message' => 'Geçersiz istek.' ), 400 );
    check_ajax_referer( 'ea_calendar', 'nonce' ); nocache_headers();
    $raw = isset( $_POST['resource'] ) ? (string) wp_unslash( $_POST['resource'] ) : '';
    if ( ! ctype_digit( $raw ) || ! ea_calendar_resource( (int) $raw ) ) wp_send_json_error( array( 'message' => 'Geçerli bir kaynak seçin.' ), 400 );
    $id = (int) $raw;
    if(function_exists('ea_coach_calendar_request')&&ea_coach_calendar_request())return;
    if ( isset( $_POST['date'] ) ) {
        $date = sanitize_text_field( wp_unslash( $_POST['date'] ) );
        if ( ! ea_core_future_date( $date ) ) wp_send_json_error( array( 'message' => 'Geçerli bir gelecek tarih seçin.' ), 400 );
        wp_send_json_success( ea_calendar_day( $id, $date, ea_calendar_busy( $id, $date, $date ) ) );
    }
    $month = isset( $_POST['month'] ) ? sanitize_text_field( wp_unslash( $_POST['month'] ) ) : '';
    if ( ! preg_match( '/^(20\d{2})-(0[1-9]|1[0-2])$/D', $month ) ) wp_send_json_error( array( 'message' => 'Geçersiz ay.' ), 400 );
    $first = new DateTimeImmutable( $month . '-01', wp_timezone() ); $last = $first->modify( 'last day of this month' );
    $busy = ea_calendar_busy( $id, $first->format( 'Y-m-d' ), $last->format( 'Y-m-d' ) ); $days = array();
    for ( $day = $first; $day <= $last; $day = $day->modify( '+1 day' ) ) {
        $info = ea_calendar_day( $id, $day->format( 'Y-m-d' ), $busy ); unset( $info['slots'] ); $days[] = $info;
    }
    wp_send_json_success( array( 'today' => wp_date( 'Y-m-d' ), 'days' => $days ) );
}
add_action( 'wp_ajax_ea_calendar', 'ea_calendar_ajax' );
add_action( 'wp_ajax_nopriv_ea_calendar', 'ea_calendar_ajax' );
add_action( 'wp_enqueue_scripts', function () {
    $post = get_queried_object();
    if ( ! is_page( 'rezervasyon' ) && ! ( $post instanceof WP_Post && has_shortcode( $post->post_content, 'ea_booking_form' ) ) ) return;
    wp_enqueue_style( 'ea-booking-calendar', EA_CORE_URL . 'assets/booking-calendar.css', array( 'ea-core' ), (string) filemtime( EA_CORE_DIR . 'assets/booking-calendar.css' ) );
    wp_enqueue_script( 'ea-booking-calendar', EA_CORE_URL . 'assets/booking-calendar.js', array( 'ea-core' ), (string) filemtime( EA_CORE_DIR . 'assets/booking-calendar.js' ), true );
    wp_localize_script( 'ea-booking-calendar', 'eaCalendar', array( 'ajaxUrl' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'ea_calendar' ), 'today' => wp_date( 'Y-m-d' ) ) );
}, 40 );
add_filter( 'do_shortcode_tag', function ( $html, $tag ) {
    if ( 'ea_booking_form' !== $tag || false === strpos( $html, 'id="ea-resource"' ) ) return $html;
    $html = str_replace( 'class="ea-form"', 'class="ea-form ea-calendar-form"', $html );
    if ( isset( $_GET['ea_pt_resource'] ) && is_scalar( $_GET['ea_pt_resource'] ) ) {
        $id = absint( $_GET['ea_pt_resource'] );
        if ( ea_calendar_resource( $id ) ) $html = str_replace( '<option value="' . $id . '" data-referee=', '<option selected value="' . $id . '" data-referee=', $html );
    }
    $calendar = '<section class="ea-booking-calendar" hidden aria-label="Rezervasyon takvimi"><div class="ea-calendar-toolbar"><button type="button" class="ea-calendar-prev" aria-label="Önceki ay">←</button><h2 class="ea-calendar-title"></h2><button type="button" class="ea-calendar-next" aria-label="Sonraki ay">→</button></div><p class="ea-calendar-message" role="status" aria-live="polite">Önce bir salon, eğitim veya antrenör seçin.</p><div class="ea-calendar-week" aria-hidden="true"><span>Pzt</span><span>Sal</span><span>Çar</span><span>Per</span><span>Cum</span><span>Cmt</span><span>Paz</span></div><div class="ea-calendar-days" role="group" aria-label="Gün seçimi"></div><ul class="ea-calendar-legend"><li data-state="available">Müsait</li><li data-state="partial">Kısmen dolu</li><li data-state="full">Tamamen dolu</li><li data-state="closed">Kapalı</li><li data-state="undefined">Takvim tanımlanmamış</li></ul><h3 class="ea-calendar-day-title">Saat seçimi</h3><div class="ea-calendar-times" role="group" aria-label="Saat seçimi"></div><noscript>Tarih alanını kullanarak seçim yapabilirsiniz; saatleri yüklemek için JavaScript gereklidir.</noscript></section>';
    return str_replace( '<p><label for="ea-date">', $calendar . '<p><label for="ea-date">', $html );
}, 20, 2 );

// Restoring an active booking from Trash must not bypass the same conflict guard.
add_filter( 'pre_untrash_post', function ( $override, $post ) {
    if ( null !== $override || 'ea_booking' !== $post->post_type ) return $override;
    $values = array(); $locks = ea_calendar_admin_guard( $post->ID, $values );
    if ( is_wp_error( $locks ) ) wp_die( esc_html( $locks->get_error_message() ), 'Rezervasyon geri alınamadı', array( 'response' => 409, 'back_link' => true ) );
    $released = false;
    $release = function () use ( $locks, &$released ) { if ( ! $released ) { ea_calendar_unlock( $locks ); $released = true; } };
    add_action( 'untrashed_post', function ( $id ) use ( $post, $release ) { if ( $id === $post->ID ) $release(); } );
    add_action( 'shutdown', $release );
    return $override;
}, 10, 2 );
