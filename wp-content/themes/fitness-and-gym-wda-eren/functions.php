<?php
/** Eren Athletic child theme additions. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function erah_enqueue_home_styles() {
	if ( ! is_front_page() ) {
		return;
	}
	$path = get_stylesheet_directory() . '/assets/css/home.css';
	wp_enqueue_style(
		'erah-home-style',
		get_stylesheet_directory_uri() . '/assets/css/home.css',
		array( 'wpdevart_fitness_and_gym_wda_styles' ),
		file_exists( $path ) ? (string) filemtime( $path ) : '1.0.0'
	);
}
add_action( 'wp_enqueue_scripts', 'erah_enqueue_home_styles', 30 );

function erah_enqueue_header_assets() {
	$style_path  = get_stylesheet_directory() . '/assets/css/header.css';
	$script_path = get_stylesheet_directory() . '/assets/js/header.js';
	wp_enqueue_style(
		'erah-header-style',
		get_stylesheet_directory_uri() . '/assets/css/header.css',
		array( 'wpdevart_fitness_and_gym_wda_styles' ),
		file_exists( $style_path ) ? (string) filemtime( $style_path ) : '1.0.0'
	);
	wp_enqueue_script(
		'erah-header-script',
		get_stylesheet_directory_uri() . '/assets/js/header.js',
		array( 'wpdevart-js' ),
		file_exists( $script_path ) ? (string) filemtime( $script_path ) : '1.0.0',
		true
	);
}
add_action( 'wp_enqueue_scripts', 'erah_enqueue_header_assets', 30 );

function erah_enqueue_typography() {
	wp_dequeue_style( 'wpdevart-theme-fonts' );
	$path = get_stylesheet_directory() . '/assets/css/typography.css';
	wp_enqueue_style( 'erah-typography', get_stylesheet_directory_uri() . '/assets/css/typography.css', array( 'wpdevart_fitness_and_gym_wda_styles' ), (string) filemtime( $path ) );
}
add_action( 'wp_enqueue_scripts', 'erah_enqueue_typography', 100 );

function erah_home_page_url( $slug ) {
	$page = get_page_by_path( $slug, OBJECT, 'page' );
	return $page ? get_permalink( $page ) : home_url( '/' . trim( $slug, '/' ) . '/' );
}

function erah_home_query( $type, $limit = 3 ) {
	return new WP_Query( array(
		'post_type'           => $type,
		'post_status'         => 'publish',
		'posts_per_page'      => $limit,
		'orderby'             => 'date',
		'order'               => 'DESC',
		'ignore_sticky_posts' => true,
	) );
}

if ( ! defined( 'ABSPATH' ) ) { exit; }

function erah_enqueue_trainer_styles() {
	if ( ! is_page( 'antrenorler' ) && ! is_post_type_archive( 'ea_trainer' ) ) { return; }
	$path = get_stylesheet_directory() . '/assets/css/trainers.css';
	wp_enqueue_style( 'erah-trainers-style', get_stylesheet_directory_uri() . '/assets/css/trainers.css', array( 'wpdevart_fitness_and_gym_wda_styles' ), file_exists( $path ) ? (string) filemtime( $path ) : '1.0.0' );
}
add_action( 'wp_enqueue_scripts', 'erah_enqueue_trainer_styles', 30 );

function erah_trainer_cards_shortcode() {
	$query = new WP_Query( array( 'post_type' => 'ea_trainer', 'post_status' => 'publish', 'posts_per_page' => 50, 'orderby' => 'title', 'order' => 'ASC', 'no_found_rows' => true ) );
	if ( ! $query->have_posts() ) { return '<p class="erah-trainers-empty">Henüz yayımlanmış antrenör profili bulunmuyor.</p>'; }
	$html = '<div class="erah-trainers-grid">';
	while ( $query->have_posts() ) {
		$query->the_post();
		$id = get_the_ID();
		$url = get_permalink( $id );
		$expertise = get_post_meta( $id, '_ea_expertise', true );
		$html .= '<article class="erah-trainer-card">';
		$html .= '<a class="erah-trainer-photo" href="' . esc_url( $url ) . '" aria-label="' . esc_attr( get_the_title() . ' profilini görüntüle' ) . '">';
		if ( has_post_thumbnail( $id ) ) { $html .= get_the_post_thumbnail( $id, 'large', array( 'loading' => 'lazy' ) ); }
		else { $html .= '<span class="erah-trainer-photo-placeholder" aria-hidden="true">EA</span>'; }
		$html .= '</a>'; // Close the photo link before card content.
		$html .= '<div class="erah-trainer-card-body"><h2><a href="' . esc_url( $url ) . '">' . esc_html( get_the_title() ) . '</a></h2>';
		if ( $expertise ) { $html .= '<p class="erah-trainer-expertise">' . esc_html( $expertise ) . '</p>'; }
		$html .= '<a class="erah-trainer-link" href="' . esc_url( $url ) . '">Profili görüntüle <span aria-hidden="true">→</span></a></div></article>';
	}
	wp_reset_postdata();
	return $html . '</div>';
}
add_shortcode( 'erah_trainer_cards', 'erah_trainer_cards_shortcode' );

/** The contact page uses the fitness hours already managed in Üye Yönetimi. */
function erah_contact_hours_shortcode() {
	$hours = (string) get_option( 'ea_fitness_hours', '' );
	$days  = array(
		'mon' => 'Pazartesi', 'tue' => 'Salı', 'wed' => 'Çarşamba',
		'thu' => 'Perşembe', 'fri' => 'Cuma', 'sat' => 'Cumartesi', 'sun' => 'Pazar',
	);
	if ( ! preg_match_all( '/\b(mon|tue|wed|thu|fri|sat|sun)\s+(\d{2}:\d{2})-(\d{2}:\d{2})\b/i', $hours, $matches, PREG_SET_ORDER ) ) {
		return '<p class="erah-contact-muted">Çalışma saatleri henüz tanımlanmadı.</p>';
	}
	$rows = array();
	foreach ( $matches as $match ) {
		$day = strtolower( $match[1] );
		$rows[ $day ] = '<div class="erah-contact-hours-row"><dt>' . esc_html( $days[ $day ] ) . '</dt><dd>' . esc_html( $match[2] . '–' . $match[3] ) . '</dd></div>';
	}
	$html = '<dl class="erah-contact-hours">';
	foreach ( array_keys( $days ) as $day ) {
		if ( isset( $rows[ $day ] ) ) $html .= $rows[ $day ];
	}
	return $html . '</dl>';
}
add_shortcode( 'erah_contact_hours', 'erah_contact_hours_shortcode' );

function erah_enqueue_contact_styles() {
	if ( ! is_page( 'iletisim' ) ) return;
	$path = get_stylesheet_directory() . '/assets/css/contact.css';
	wp_enqueue_style( 'erah-contact', get_stylesheet_directory_uri() . '/assets/css/contact.css', array( 'wpdevart_fitness_and_gym_wda_styles' ), (string) filemtime( $path ) );
}
add_action( 'wp_enqueue_scripts', 'erah_enqueue_contact_styles', 30 );

function erah_enqueue_accessibility_widget() {
	$base = get_stylesheet_directory_uri();
	$dir  = get_stylesheet_directory();
	$css  = $dir . '/assets/css/accessibility-widget.css';
	$js   = $dir . '/assets/js/accessibility-widget.js';
	wp_enqueue_style( 'erah-accessibility-widget', $base . '/assets/css/accessibility-widget.css', array(), file_exists( $css ) ? (string) filemtime( $css ) : '1.0.0' );
	wp_enqueue_script( 'erah-accessibility-widget', $base . '/assets/js/accessibility-widget.js', array(), file_exists( $js ) ? (string) filemtime( $js ) : '1.0.0', true );
}
add_action( 'wp_enqueue_scripts', 'erah_enqueue_accessibility_widget', 110 );
