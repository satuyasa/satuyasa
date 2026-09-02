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

	<?php /* Tester dan token raster dirender oleh shortcode Authentype. */ ?>
	<div class="foundry-specimen">
		<?php if ( $fd_specimen && shortcode_exists( 'authentype_free_font_preview' ) ) : ?>
			<?php
			echo do_shortcode( sprintf(
				'[authentype_free_font_preview id="%d" text="%s" size="120" min_size="36" max_size="180" text_color="#efefef" bg_color="#121212"]',
				(int) $fd_id,
				esc_attr( get_the_title() )
			) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- shortcode milik plugin meng-escape semua nilai.
			?>
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
	</div>

	<?php if ( has_excerpt() ) : ?>
		<p class="foundry-band__note"><?php echo esc_html( get_the_excerpt() ); ?></p>
	<?php endif; ?>
</article>
