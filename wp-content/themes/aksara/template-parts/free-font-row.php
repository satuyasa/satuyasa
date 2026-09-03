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
	 * CANVAS LANGSUNG, BUKAN SHORTCODE — dan ini pemulihan, bukan gaya baru.
	 *
	 * Sampai 0.9.19 blok ini memang mencetak canvas seperti di bawah dan
	 * spesimennya tampil. Di 0.9.20 saya menggantinya dengan
	 * do_shortcode('[authentype_free_font_preview ...]'). Shortcode itu TIDAK
	 * PERNAH ADA di plugin Authentype 1.0.7 — yang terdaftar hanya
	 * authentype_free_downloads dan authentype_font_specimen. Akibatnya
	 * shortcode_exists() selalu false dan setiap baris jatuh ke placeholder.
	 * Perbaikan warna di 0.9.25 pun mengatur PNG yang tidak pernah diminta.
	 *
	 * WADAH .ath-specimen-v7 WAJIB ADA. specimen.js hanya menjalankan
	 * initRoot() pada elemen berkelas itu (baris 1273), dan initRoot itulah
	 * yang memasang IntersectionObserver yang me-render canvas. Tanpa wadah
	 * ini canvas tidak pernah dirender — bukan gagal, tapi diam.
	 *
	 * data-font-post-id berisi ID ath_font YANG DITAUTKAN, bukan ID free
	 * download ini: nilai itu dikirim sebagai post_id ke endpoint render, dan
	 * endpoint menolak apa pun yang bukan ath_font berstatus publish.
	 *
	 * Warnanya tidak perlu diatur di sini. initRoot() menimpa textColor dan
	 * bgColor tanpa syarat ke #111111 di atas #ffffff (specimen.js:1259-1260)
	 * — tinta hitam di atas kertas putih, persis yang diminta untuk arsip ini.
	 */
	?>
	<div class="foundry-specimen">
		<?php if ( $fd_specimen ) : ?>
			<span class="ath-specimen ath-specimen-v7" data-font-post-id="<?php echo esc_attr( $fd_specimen['font_id'] ); ?>">
				<canvas class="ath-server-canvas"
					data-font-token="<?php echo esc_attr( $fd_specimen['token'] ); ?>"
					data-mode="style-text"
					data-text="<?php echo esc_attr( get_the_title() ); ?>"
					data-font-size="120"
					data-fit-single-line="1"
					aria-label="<?php echo esc_attr( sprintf( __( '%s specimen', 'aksara' ), get_the_title() ) ); ?>"></canvas>
				<?php
				/*
				 * Tiga keadaan gagal, satu komponen, keterangan berbeda.
				 * Placeholder-nya sengaja terbaca SEBAGAI placeholder supaya
				 * tidak ada pengunjung yang mengira nama dalam font UI itu
				 * wujud font yang sedang ditawarkan.
				 */
				aksara_foundry_placeholder( get_the_title(), __( 'Preview unavailable', 'aksara' ), true );
				?>
				<noscript><?php aksara_foundry_placeholder( get_the_title(), __( 'Preview needs JavaScript', 'aksara' ) ); ?></noscript>
			</span>
		<?php else : ?>
			<?php
			/*
			 * Tidak ada ath_font yang ditautkan lewat
			 * _ath_free_download_related_font, jadi tidak ada token preview.
			 * Ini keadaan data, bukan kegagalan teknis — keterangannya
			 * dibedakan.
			 */
			aksara_foundry_placeholder( get_the_title(), __( 'No specimen linked', 'aksara' ) );
			?>
		<?php endif; ?>
	</div>

	<?php if ( has_excerpt() ) : ?>
		<p class="foundry-band__note"><?php echo esc_html( get_the_excerpt() ); ?></p>
	<?php endif; ?>
</article>
