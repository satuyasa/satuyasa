<?php
/**
 * Satu pita spesimen free font di arsip.
 *
 * Strukturnya mengikuti "Font Specimen Card" di DESIGN3: strip metadata di
 * atas (nama kiri, statistik tengah, aksi kanan), lalu jeda vertikal, lalu
 * spesimen selebar penuh. Tidak ada latar kartu dan tidak ada bayangan —
 * garis rambut 1px ITULAH chrome kartunya.
 *
 * @package Aksara
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$fd_id       = get_the_ID();
$fd_type     = aksara_free_meta( $fd_id, '_ath_free_download_type', 'font' );
$fd_specimen = aksara_free_font_specimen( $fd_id );
$fd_license  = function_exists( 'ath_free_download_resolve_license' ) ? ath_free_download_resolve_license( $fd_id ) : array();
$fd_label    = ! empty( $fd_license['label'] ) ? $fd_license['label'] : aksara_free_meta( $fd_id, '_ath_free_download_license_label', '' );
$fd_styles   = 0;
if ( $fd_specimen && function_exists( 'aksara_authentype_styles' ) ) {
	$fd_styles = count( aksara_authentype_styles( $fd_specimen['font_id'] ) );
}
?>
<article class="foundry-band">
	<div class="foundry-meta">
		<span><?php the_title(); ?></span>
		<span class="foundry-meta-group muted">
			<span><?php echo esc_html( aksara_free_type_label( $fd_type ) ); ?></span>
			<?php if ( $fd_styles ) : ?>
				<span><?php printf( esc_html( _n( '%d style', '%d styles', $fd_styles, 'aksara' ) ), (int) $fd_styles ); ?></span>
			<?php endif; ?>
			<?php if ( $fd_label ) : ?><span><?php echo esc_html( $fd_label ); ?></span><?php endif; ?>
		</span>
		<span class="foundry-actions">
			<a class="foundry-btn" href="<?php the_permalink(); ?>"><?php esc_html_e( 'Download free', 'aksara' ); ?></a>
			<a class="foundry-btn-ghost" href="<?php the_permalink(); ?>"><?php esc_html_e( 'Explore', 'aksara' ); ?></a>
		</span>
	</div>

	<?php
	/*
	 * WADAH .ath-specimen-v7 WAJIB ADA — bukan hiasan kelas.
	 *
	 * specimen.js hanya menjalankan initRoot() pada elemen berkelas
	 * .ath-specimen-v7 (baris 1273), dan initRoot itulah yang memasang
	 * IntersectionObserver yang me-render canvas. Tanpa wadah ini canvas
	 * TIDAK PERNAH dirender sama sekali — bukan gagal, tapi diam.
	 *
	 * data-font-post-id juga wajib, dan isinya ID ath_font yang DITAUTKAN,
	 * bukan ID free download ini: nilai itulah yang dikirim sebagai post_id ke
	 * endpoint render, dan endpoint menolak apa pun yang bukan ath_font
	 * berstatus publish.
	 *
	 * data-text-color / data-bg-color sengaja TIDAK ditulis di sini walau
	 * terlihat masuk akal. initRoot() menimpanya tanpa syarat ke #111111 dan
	 * #ffffff di dua baris pertamanya, jadi apa pun yang ditulis di markup
	 * akan hilang. Palet gelapnya dipasang setelah init oleh
	 * assets/js/foundry-tester.js.
	 */
	?>
	<a class="foundry-specimen" href="<?php the_permalink(); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Open %s', 'aksara' ), get_the_title() ) ); ?>">
		<?php if ( $fd_specimen ) : ?>
			<span class="ath-specimen ath-specimen-v7 foundry-root" data-font-post-id="<?php echo esc_attr( $fd_specimen['font_id'] ); ?>">
			<canvas class="ath-server-canvas"
				data-sync-master="1"
				data-font-token="<?php echo esc_attr( $fd_specimen['token'] ); ?>"
				data-mode="style-text"
				data-text="<?php echo esc_attr( get_the_title() ); ?>"
				data-font-size="120"
				data-fit-single-line="1"
				aria-hidden="true"></canvas>
			<?php
			/*
			 * Tiga keadaan gagal, satu komponen, keterangan berbeda — pola
			 * yang sama dengan sistem terang di 0.9.10. Placeholder-nya
			 * sengaja terbaca SEBAGAI placeholder: ukurannya jauh di bawah
			 * spesimen sungguhan dan warnanya --fd-ash, supaya tidak ada
			 * pengunjung yang mengira nama dalam JetBrains Mono itu wujud
			 * font yang sedang ditawarkan.
			 */
			aksara_foundry_placeholder( get_the_title(), __( 'Preview unavailable', 'aksara' ), true );
			?>
			<noscript><?php aksara_foundry_placeholder( get_the_title(), __( 'Preview needs JavaScript', 'aksara' ) ); ?></noscript>
			</span>
		<?php else : ?>
			<?php
			/*
			 * Tidak ada ath_font yang ditautkan lewat
			 * _ath_free_download_related_font, jadi tidak ada token preview
			 * dan spesimen sungguhan memang tidak bisa dirender. Ini keadaan
			 * data, bukan kegagalan teknis — keterangannya dibedakan.
			 */
			aksara_foundry_placeholder( get_the_title(), __( 'No specimen linked', 'aksara' ) );
			?>
		<?php endif; ?>
	</a>

	<?php if ( has_excerpt() ) : ?>
		<p class="foundry-band__note"><?php echo esc_html( get_the_excerpt() ); ?></p>
	<?php endif; ?>
</article>
