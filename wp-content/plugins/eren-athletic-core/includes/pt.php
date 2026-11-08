<?php
/** PT presentation only. Booking validation and availability remain in the core. */
if ( ! defined( 'ABSPATH' ) ) exit;

function ea_pt_context() {
    return is_page( 'pt-egitimleri' ) || is_post_type_archive( 'ea_program' ) || is_singular( array( 'ea_program', 'ea_package' ) );
}
add_filter( 'template_include', function ( $template ) {
    return ea_pt_context() ? EA_CORE_DIR . 'includes/pt-template.php' : $template;
}, 99 );
add_action( 'wp_enqueue_scripts', function () {
    if ( ea_pt_context() ) wp_enqueue_style( 'ea-pt', EA_CORE_URL . 'assets/ea-pt.css', array( 'ea-core' ), filemtime( EA_CORE_DIR . 'assets/ea-pt.css' ) );
}, 40 );

function ea_pt_posts( $type ) {
    return get_posts( array( 'post_type' => $type, 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => array( 'menu_order' => 'ASC', 'title' => 'ASC' ) ) );
}
function ea_pt_booking_url( $id ) {
    return add_query_arg( 'ea_pt_resource', absint( $id ), ea_core_page_url( 'rezervasyon' ) );
}
// Presentation-only preselection. The existing form, slot checks and submission handler are unchanged.
add_filter( 'do_shortcode_tag', function ( $output, $tag ) {
    if ( 'ea_booking_form' !== $tag || empty( $_GET['ea_pt_resource'] ) || ! is_scalar( $_GET['ea_pt_resource'] ) ) return $output;
    $id = absint( $_GET['ea_pt_resource'] );
    if ( 'ea_program' !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) ) return $output;
    return str_replace( '<option value="' . $id . '" data-referee=', '<option selected value="' . $id . '" data-referee=', $output );
}, 10, 2 );

function ea_pt_trainer( $id ) {
    $trainer = absint( get_post_meta( $id, '_ea_trainer', true ) );
    if ( ! $trainer || 'ea_trainer' !== get_post_type( $trainer ) || 'publish' !== get_post_status( $trainer ) ) return '<span>Antrenör bilgisi yakında</span>';
    return '<a class="ea-pt-trainer" href="' . esc_url( get_permalink( $trainer ) ) . '">' . get_the_post_thumbnail( $trainer, 'thumbnail', array( 'loading' => 'lazy', 'alt' => '' ) ) . '<span>' . esc_html( get_the_title( $trainer ) ) . '<small>Antrenör profilini incele →</small></span></a>';
}
function ea_pt_specs( $id ) {
    $package = 'ea_package' === get_post_type( $id );
    $fields = $package ? array( 'session_count' => array( 'Seans sayısı', ' seans' ), 'duration' => array( 'Seans süresi', ' dakika' ), 'price' => array( 'Paket fiyatı', '' ) ) : array( 'duration' => array( 'Seans süresi', ' dakika' ), 'price' => array( 'Seans fiyatı', '' ), 'hours' => array( 'Uygunluk takvimi', '' ) );
    echo '<dl class="ea-pt-specs">';
    foreach ( $fields as $key => $field ) {
        $value = get_post_meta( $id, '_ea_' . $key, true );
        $known = '' !== $value && '0' !== (string) $value;
        $display=$known?$value.$field[1]:'Henüz belirlenmedi';
        if('price'===$key){
            $display=$known?($package?$value:ea_sales_price_label($value)):($package?'Fiyat bilgisi için iletişime geçin':'Tek seans fiyatı için iletişime geçin');
            $program=$package?(int)get_post_meta($id,'_ea_program',true):$id;
            if($known&&'group'===get_post_meta($program,'_ea_type',true)&&!preg_match('/kişi\s+başı/iu',$display))$display.=' / kişi başı';
        }
        echo '<div><dt>' . esc_html( $field[0] ) . '</dt><dd>' . nl2br(esc_html($display)) . '</dd></div>';
    }
    echo '</dl>';
    if ( $package ) echo '<p><small>Fiyatlar örnek olarak gösterilmiştir.</small></p>';
}
function ea_pt_package_card( $package ) {
    echo '<article class="ea-pt-package"><p class="ea-pt-eyebrow">PT PAKETİ</p><h3><a href="' . esc_url( get_permalink( $package ) ) . '">' . esc_html( $package->post_title ) . '</a></h3>';
    ea_pt_specs( $package->ID );
    echo ea_pt_trainer( $package->ID );
    echo '<a class="ea-pt-link" href="' . esc_url( get_permalink( $package ) ) . '">Paket detaylarını incele <span aria-hidden="true">↗</span></a></article>';
}
