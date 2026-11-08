<?php
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();
$detail = is_singular( 'ea_amenity' );
?>
<main id="content_navigator" class="ea-amenities">
<div class="ea-amenities-shell">
<?php if ( ! $detail ) : ?>
    <header class="ea-amenities-heading"><p class="ea-amenities-eyebrow">EREN ATHLETIC / TESİS OLANAKLARI</p><h1>Antrenmanının yanında<br><span>konfor da var.</span></h1><p>Antrenman öncesinden sonrasına, tesisteki alanları keşfet.</p></header>
    <div class="ea-amenities-grid">
    <?php
    $amenities = get_posts( array( 'post_type' => 'ea_amenity', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
    foreach ( $amenities as $amenity ) : ?>
        <article class="ea-amenity-card">
            <?php ea_amenity_figure( $amenity->ID ); ?>
            <div class="ea-amenity-body"><h2><a href="<?php echo esc_url( get_permalink( $amenity ) ); ?>"><?php echo esc_html( $amenity->post_title ); ?></a></h2><p><?php echo esc_html( $amenity->post_excerpt ); ?></p><a class="ea-amenity-link" href="<?php echo esc_url( get_permalink( $amenity ) ); ?>" aria-label="<?php echo esc_attr( $amenity->post_title . ' detaylarını incele' ); ?>">Detayları incele <span aria-hidden="true">↗</span></a></div>
        </article>
    <?php endforeach; ?>
    </div>
<?php else : while ( have_posts() ) : the_post(); ?>
    <nav class="ea-amenity-back" aria-label="İçerik yolu"><a href="<?php echo esc_url( ea_core_page_url( 'tesis-olanaklari' ) ); ?>">← Tüm tesis olanakları</a></nav>
    <header class="ea-amenities-heading"><p class="ea-amenities-eyebrow">EREN ATHLETIC / TESİS OLANAKLARI</p><h1><?php the_title(); ?></h1></header>
    <?php ea_amenity_figure( get_the_ID(), true ); ?>
    <div class="ea-amenity-content"><?php echo wp_kses_post( wpautop( get_the_content() ) ); ?></div>
<?php endwhile; endif; ?>
</div>
</main>
<?php get_footer(); ?>
