<?php
/** Eren Athletic home page, using the existing parent header and footer. */
get_header();
$reservation_url = erah_home_page_url( 'rezervasyon' );
$program_url     = erah_home_page_url( 'pt-egitimleri' );
$hero_image_id   = absint( get_option( 'erah_home_hero_attachment_id' ) );
?>
<main id="content_navigator" class="erah-home">
	<section class="erah-hero" aria-labelledby="erah-hero-title">
		<div class="erah-shell erah-hero-grid">
			<div class="erah-hero-copy">
				<p class="erah-eyebrow">EREN ATHLETIC <span aria-hidden="true">/</span> SPOR KOMPLEKSİ</p>
				<h1 id="erah-hero-title">Hedefine odaklan.<br><span>Harekete geç.</span></h1>
				<p class="erah-lead">PT eğitimlerini, antrenörleri, salonları ve üyelik seçeneklerini keşfet. Sana uygun ilk adımı seç.</p>
				<div class="erah-actions">
					<a class="erah-button erah-button-primary" href="<?php echo esc_url( $reservation_url ); ?>">Rezervasyon yap <span aria-hidden="true">↗</span></a>
					<a class="erah-button erah-button-secondary" href="<?php echo esc_url( $program_url ); ?>">PT eğitimlerini incele</a>
				</div>
			</div>
			<div class="erah-hero-visual">
				<?php echo wp_get_attachment_image( $hero_image_id, 'full', false, array( 'fetchpriority' => 'high', 'loading' => 'eager', 'decoding' => 'async', 'sizes' => '(max-width: 620px) calc(100vw - 46px), (max-width: 960px) 706px, 590px' ) ); ?>
			</div>
		</div>
		<div class="erah-shell erah-hero-foot"><span>GÜCÜNÜ KEŞFET</span><span aria-hidden="true">↓</span></div>
	</section>

	<section class="erah-section erah-discover" aria-labelledby="erah-discover-title">
		<div class="erah-shell">
			<div class="erah-section-heading"><div><p class="erah-eyebrow">EREN ATHLETIC’TE</p><h2 id="erah-discover-title">Sana uygun alanı <span>keşfet.</span></h2></div><p>İhtiyacına göre antrenmanını planla, salonları incele veya üyelik başvurunu başlat.</p></div>
			<div class="erah-service-grid">
				<a class="erah-service-card" href="<?php echo esc_url( $program_url ); ?>"><span class="erah-card-number">01</span><span class="erah-card-icon" aria-hidden="true">↗</span><h3>PT Eğitimleri</h3><p>Bireysel ve grup eğitim seçeneklerini incele.</p><span class="erah-card-link">Eğitimleri keşfet <b aria-hidden="true">→</b></span></a>
				<a class="erah-service-card" href="<?php echo esc_url( erah_home_page_url( 'antrenorler' ) ); ?>"><span class="erah-card-number">02</span><span class="erah-card-icon" aria-hidden="true">↗</span><h3>Antrenörler</h3><p>Antrenör profillerini ve uzmanlık alanlarını görüntüle.</p><span class="erah-card-link">Antrenörleri tanı <b aria-hidden="true">→</b></span></a>
				<a class="erah-service-card" href="<?php echo esc_url( erah_home_page_url( 'salonlar' ) ); ?>"><span class="erah-card-number">03</span><span class="erah-card-icon" aria-hidden="true">↗</span><h3>Salonlar</h3><p>Basketbol ve voleybol salonlarını incele, talep oluştur.</p><span class="erah-card-link">Salonları incele <b aria-hidden="true">→</b></span></a>
				<a class="erah-service-card" href="<?php echo esc_url( erah_home_page_url( 'uyelik' ) ); ?>"><span class="erah-card-number">04</span><span class="erah-card-icon" aria-hidden="true">↗</span><h3>Üyelik</h3><p>Üyelik seçenekleri ve başvuru adımlarına ulaş.</p><span class="erah-card-link">Üyelik bilgisi al <b aria-hidden="true">→</b></span></a>
			</div>
		</div>
	</section>

	<?php $venues = erah_home_query( 'ea_venue', 3 ); if ( $venues->have_posts() ) : ?>
	<section class="erah-section erah-venues" aria-labelledby="erah-venues-title"><div class="erah-shell">
		<div class="erah-section-heading"><div><p class="erah-eyebrow">SALON KİRALAMA</p><h2 id="erah-venues-title">Oyunun <span>başlasın.</span></h2></div><a class="erah-text-link" href="<?php echo esc_url( erah_home_page_url( 'salonlar' ) ); ?>">Tüm salonlar <b aria-hidden="true">→</b></a></div>
		<div class="erah-content-grid">
		<?php while ( $venues->have_posts() ) : $venues->the_post(); $venue_type = get_post_meta( get_the_ID(), '_ea_venue_type', true ); $venue_alt = 'volleyball' === $venue_type ? 'Voleybol Sahası için yapay zekâ ile oluşturulmuş temsili kapalı voleybol sahası görseli.' : get_the_title() . ' için yapay zekâ ile oluşturulmuş temsili kapalı basketbol sahası görseli.'; ?>
			<article class="erah-content-card">
				<a class="erah-content-image" href="<?php the_permalink(); ?>"><?php if ( has_post_thumbnail() ) { the_post_thumbnail( 'large', array( 'loading' => 'lazy', 'decoding' => 'async', 'alt' => $venue_alt ) ); } else { ?><span class="erah-image-placeholder" aria-hidden="true">EA</span><?php } ?><span class="erah-image-disclaimer">Temsili görsel &ndash; Yapay zekâ ile oluşturulmuştur</span></a>
				<div class="erah-content-body"><p class="erah-card-kicker"><?php echo esc_html( 'basketball' === $venue_type ? 'BASKETBOL' : ( 'volleyball' === $venue_type ? 'VOLEYBOL' : 'EREN ATHLETIC' ) ); ?></p><h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3><?php if ( has_excerpt() ) : ?><p><?php echo esc_html( get_the_excerpt() ); ?></p><?php endif; ?><a class="erah-card-link" href="<?php echo esc_url( $reservation_url ); ?>">Rezervasyon talebi <b aria-hidden="true">→</b></a></div>
			</article>
		<?php endwhile; wp_reset_postdata(); ?>
		</div>
		<p class="erah-note">Salon kullanım saatleri, kiralama koşulları ve hakem hizmeti talep sırasında tesis tarafından teyit edilir.</p>
	</div></section>
	<?php endif; ?>

	<?php $amenities = erah_home_query( 'ea_amenity', 8 ); if ( $amenities->have_posts() ) : ?>
	<section class="erah-section erah-amenities" aria-labelledby="erah-amenities-title"><div class="erah-shell erah-amenity-layout">
		<div><p class="erah-eyebrow">TESİS OLANAKLARI</p><h2 id="erah-amenities-title">Antrenmanın için<br><span>daha fazlası.</span></h2><a class="erah-text-link" href="<?php echo esc_url( erah_home_page_url( 'tesis-olanaklari' ) ); ?>">Tüm olanaklar <b aria-hidden="true">→</b></a></div>
		<div class="erah-amenity-list"><?php while ( $amenities->have_posts() ) : $amenities->the_post(); ?><a href="<?php the_permalink(); ?>"><span><?php the_title(); ?></span><b aria-hidden="true">↗</b></a><?php endwhile; wp_reset_postdata(); ?></div>
	</div></section>
	<?php endif; ?>

	<?php $announcements = erah_home_query( 'ea_announcement', 3 ); if ( $announcements->have_posts() ) : ?>
	<section class="erah-section erah-announcements" aria-labelledby="erah-announcements-title"><div class="erah-shell">
		<div class="erah-section-heading"><div><p class="erah-eyebrow">GÜNCEL</p><h2 id="erah-announcements-title">Kulüpten <span>haberler.</span></h2></div><a class="erah-text-link" href="<?php echo esc_url( erah_home_page_url( 'duyurular' ) ); ?>">Tüm duyurular <b aria-hidden="true">→</b></a></div>
		<div class="erah-announcement-grid"><?php while ( $announcements->have_posts() ) : $announcements->the_post(); $event_date = get_post_meta( get_the_ID(), '_ea_event_date', true ); ?>
			<article class="erah-announcement-card"><a href="<?php the_permalink(); ?>"><?php if ( has_post_thumbnail() ) { the_post_thumbnail( 'medium_large', array( 'loading' => 'lazy' ) ); } ?><div class="erah-announcement-copy"><p class="erah-card-kicker"><?php echo esc_html( $event_date ? date_i18n( get_option( 'date_format' ), strtotime( $event_date ) ) : get_the_date() ); ?></p><h3><?php the_title(); ?></h3><?php if ( has_excerpt() ) : ?><p><?php echo esc_html( get_the_excerpt() ); ?></p><?php endif; ?><span class="erah-card-link">Duyuruyu oku <b aria-hidden="true">→</b></span></div></a></article>
		<?php endwhile; wp_reset_postdata(); ?></div>
	</div></section>
	<?php endif; ?>

	<section class="erah-final-cta"><div class="erah-shell erah-final-cta-inner"><div><p class="erah-eyebrow">İLK ADIMINI AT</p><h2>Hedefin için<br><span>hazır mısın?</span></h2></div><a class="erah-button erah-button-dark" href="<?php echo esc_url( $reservation_url ); ?>">Rezervasyon talebi oluştur <span aria-hidden="true">↗</span></a></div></section>
</main>
<?php get_footer(); ?>
