<?php
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();
$is_listing = is_page( 'pt-egitimleri' ) || is_post_type_archive( 'ea_program' );
?>
<main id="content_navigator" class="ea-pt" tabindex="-1">
<?php if ( $is_listing ) : ?>
<section class="ea-pt-hero">
    <div class="ea-pt-hero-copy"><p class="ea-pt-eyebrow">EREN ATHLETIC / PT EĞİTİMLERİ</p><h1>Hedefin sana özel.<br><span>Eğitimin de öyle.</span></h1><p>Gücünü geliştir, hareket alanını genişlet. Bireysel çalışmalardan takım sporlarına, hedeflerine uygun eğitimi keşfet.</p><a class="ea-button" href="#ea-pt-programs">Eğitimleri keşfet <span aria-hidden="true">↗</span></a><a class="ea-pt-secondary" href="#ea-pt-packages">PT paketleri ↓</a></div>
    <div class="ea-pt-hero-mark" aria-hidden="true"><span>EREN<br>ATHLETIC</span><strong>DAHA GÜÇLÜ<br>BİR SEN.</strong><small>HAREKET ET. GELİŞ. DEVAM ET.</small></div>
</section>
<section id="ea-pt-programs" class="ea-pt-section" aria-labelledby="ea-pt-program-title">
    <div class="ea-pt-heading"><div><p class="ea-pt-eyebrow">SANA UYGUN ANTRENMAN</p><h2 id="ea-pt-program-title">Gelişimin için bir başlangıç seç.</h2></div><p>Her hedef farklı bir yol ister.<br>Senin yolun burada başlasın.</p></div>
    <div class="ea-pt-grid">
    <?php foreach ( ea_pt_posts( 'ea_program' ) as $index => $program ) : ?>
        <article class="ea-pt-card"><div class="ea-pt-card-top"><span class="ea-pt-number"><?php echo esc_html( sprintf( '%02d', $index + 1 ) ); ?></span><span class="ea-pt-card-tag">EREN ATHLETIC</span></div><h3><?php echo esc_html( $program->post_title ); ?></h3><p><?php echo esc_html( $program->post_excerpt ? $program->post_excerpt : wp_trim_words( wp_strip_all_tags( $program->post_content ), 30 ) ); ?></p><a class="ea-pt-link" href="<?php echo esc_url( get_permalink( $program ) ); ?>" aria-label="<?php echo esc_attr( $program->post_title . ' detaylarını incele' ); ?>">Detayları incele <span aria-hidden="true">↗</span></a></article>
    <?php endforeach; ?>
    </div>
</section>
<section id="ea-pt-packages" class="ea-pt-section" aria-labelledby="ea-pt-package-title"><p class="ea-pt-eyebrow">SÜREKLİLİKLE GELİŞ</p><h2 id="ea-pt-package-title">PT paketleri</h2><p><small>Fiyatlar örnek olarak gösterilmiştir.</small></p><div class="ea-pt-grid">
<?php foreach ( ea_pt_posts( 'ea_package' ) as $package ) ea_pt_package_card( $package ); ?>
</div></section>
<section class="ea-pt-steps ea-pt-section" aria-label="Nasıl başlarım?"><div><span>01 / KEŞFET</span><h3>Eğitimini seç</h3><p>Hedefine ve ilgi alanına uygun programı incele.</p></div><div><span>02 / PLANLA</span><h3>Uygunluğu kontrol et</h3><p>Tanımlanan takvim üzerinden tarih ve saat seçeneklerini gör.</p></div><div><span>03 / BAŞLA</span><h3>Talebini ilet</h3><p>Rezervasyon talebin tesis onayından sonra kesinleşir.</p></div></section>
<?php else : while ( have_posts() ) : the_post(); $id = get_the_ID(); $is_package = 'ea_package' === get_post_type(); ?>
<nav class="ea-pt-breadcrumb" aria-label="İçerik yolu"><a href="<?php echo esc_url( ea_core_page_url( 'pt-egitimleri' ) ); ?>">← Tüm PT eğitimleri</a></nav>
<section class="ea-pt-detail-hero"><p class="ea-pt-eyebrow"><?php echo $is_package ? 'PT PAKETİ' : 'EREN ATHLETIC / EĞİTİM'; ?></p><h1><?php the_title(); ?></h1><p><?php echo esc_html( get_the_excerpt() ); ?></p></section>
<div class="ea-pt-detail-grid"><section class="ea-pt-content"><h2><?php echo $is_package ? 'Paket hakkında' : 'Eğitim hakkında'; ?></h2><?php echo wp_kses_post( wpautop( get_the_content() ) ); ?>
<?php if ( $is_package ) echo wp_kses_post( wpautop( get_post_meta( $id, '_ea_details', true ) ) ); ?>
<h2>Birlikte çalışacağın antrenör</h2><?php echo ea_pt_trainer( $id ); ?>
<?php if ( ! $is_package ) : $packages = ea_pt_posts( 'ea_package' ); $linked = absint( get_post_meta( $id, '_ea_package', true ) ); foreach ( $packages as $package ) { if ( $linked === $package->ID || $id === absint( get_post_meta( $package->ID, '_ea_program', true ) ) ) ea_pt_package_card( $package ); } endif; ?>
</section><aside class="ea-pt-summary" aria-label="Eğitim ve rezervasyon bilgileri"><h2><?php echo $is_package ? 'Paket bilgileri' : 'Eğitim bilgileri'; ?></h2><?php ea_pt_specs( $id );
$resource = $is_package ? absint( get_post_meta( $id, '_ea_program', true ) ) : $id;
if ( 'ea_program' === get_post_type( $resource ) && 'publish' === get_post_status( $resource ) ) : ?>
<a class="ea-button" href="<?php echo esc_url( ea_pt_booking_url( $resource ) ); ?>">Rezervasyona geç <span aria-hidden="true">↗</span></a>
<?php else : ?><a class="ea-button" href="<?php echo esc_url( ea_core_page_url( 'rezervasyon' ) ); ?>">Rezervasyon seçenekleri</a><?php endif; ?>
<p>Çalışma günleri ve uygun saatler ilgili eğitim sayfasında ve rezervasyon takviminde gösterilir. Talebin tesis onayından sonra kesinleşir.</p></aside></div>
<?php endwhile; endif; ?>
<section class="ea-pt-end"><p class="ea-pt-eyebrow">BİR SONRAKİ ADIM</p><h2>Harekete geçmeye hazır mısın?</h2><p>Eğitimleri incele, antrenörleri tanı ve sana uygun başlangıcı planla.</p><a class="ea-button" href="<?php echo esc_url( ea_core_page_url( 'antrenorler' ) ); ?>">Antrenörleri tanı ↗</a></section>
</main>
<?php get_footer(); ?>
