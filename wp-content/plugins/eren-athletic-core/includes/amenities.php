<?php
/** Presentation for existing facility amenities only. */
if ( ! defined( 'ABSPATH' ) ) exit;

function ea_amenities_context() {
    return is_page( 'tesis-olanaklari' ) || is_post_type_archive( 'ea_amenity' ) || is_singular( 'ea_amenity' );
}
add_filter( 'template_include', function ( $template ) {
    return ea_amenities_context() ? EA_CORE_DIR . 'includes/amenities-template.php' : $template;
}, 99 );
add_action( 'wp_enqueue_scripts', function () {
    if ( ea_amenities_context() ) {
        wp_enqueue_style( 'ea-amenities', EA_CORE_URL . 'assets/ea-amenities.css', array( 'ea-core' ), (string) filemtime( EA_CORE_DIR . 'assets/ea-amenities.css' ) );
    }
}, 40 );

function ea_amenity_figure( $id, $detail = false ) {
    if ( ! has_post_thumbnail( $id ) ) return;
    echo '<figure class="ea-amenity-figure">';
    if ( ! $detail ) echo '<a href="' . esc_url( get_permalink( $id ) ) . '" aria-label="' . esc_attr( get_the_title( $id ) . ' detaylarını incele' ) . '">';
    $size = $detail ? 'full' : 'large';
    $image = wp_get_attachment_image_src( get_post_thumbnail_id( $id ), $size );
    $width = $image ? absint( $image[1] ) : 0;
    echo get_the_post_thumbnail( $id, $size, array( 'loading' => $detail ? 'eager' : 'lazy', 'decoding' => 'async', 'style' => $width ? 'max-width:min(100%,' . $width . 'px)' : '', 'sizes' => $detail ? '(max-width: 1122px) calc(100vw - 40px), 1082px' : '(max-width: 700px) calc(100vw - 40px), (max-width: 1200px) calc((100vw - 64px) / 2), 548px' ) );
    if ( ! $detail ) echo '</a>';
    echo '</figure>';
}
