<?php
/**
 * Satu baris di daftar font (Home & halaman Fonts) — unit konten inti
 * sistem visual ini (DESIGN.md > Components > "Font Specimen Row").
 *
 * Strukturnya: strip kontrol tipis di atas (nama, meta, harga, tombol
 * Trial/View), lalu huruf raksasa di bawahnya edge-to-edge. Dipisahkan
 * dari baris berikutnya oleh satu hairline — tanpa card, tanpa kotak,
 * tanpa shadow.
 *
 * CATATAN KEAMANAN: nama font di sini ditampilkan dalam font ASLINYA,
 * tapi sebagai GAMBAR hasil render server (PHP GD), bukan dengan memuat
 * berkas font ke browser lewat @font-face. Yang sampai ke pengunjung
 * hanya piksel — berkas fontnya tidak pernah meninggalkan server. Ini
 * persis pendekatan yang diminta PRD Bagian 4.3 poin 3 untuk mode
 * display/listing, dan berbeda dari typing tool di halaman produk yang
 * memang butuh subset .woff2 sungguhan lewat microservice.
 *
 * Kalau specimen tidak bisa dibuat (style diunggah sebagai .woff2 yang
 * tidak terbaca FreeType, GD tidak tersedia, atau plugin nonaktif),
 * baris ini otomatis mundur ke teks biasa dalam font tema pada ukuran
 * display yang sama — jadi tata letaknya tidak berubah, cuma hurufnya.
 *
 * @package Aksara
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$product = wc_get_product( get_the_ID() );
if ( ! $product ) {
	return;
}

$style_count = class_exists( 'Aksara_Font_Styles_Repository' )
	? count( Aksara_Font_Styles_Repository::get_by_product( $product->get_id() ) )
	: 0;

$categories = wc_get_product_category_list( $product->get_id() );

/*
 * Ukuran spesimen = 115px, token --text-display di DESIGN.md.
 *
 * DESIGN.md menyebut dua ukuran kanonik (115px & 158px) dan melarang nilai
 * di luar rentang itu. Untuk baris listing dipilih yang 115px karena di
 * sini gambarnya adalah PNG hasil render, bukan teks: pada 158px dengan
 * SCALE 2x, nama font yang panjang menghasilkan PNG selebar beberapa ribu
 * piksel per baris, dikali 6 baris di Home. 115px tetap "spesimen, bukan
 * logo" sesuai filosofi DESIGN.md, dengan berat berkas yang masuk akal.
 * Skala 158px dipakai di halaman produk tunggal, tempat cuma ada satu.
 */
$specimen = function_exists( 'aksara_font_specimen' )
	? aksara_font_specimen( $product->get_id(), get_the_title(), 115 )
	: '';
?>
<div class="specimen-row">
	<div class="sp-controls">
		<div class="sp-label">
			<span class="sp-name-text"><?php the_title(); ?></span>
			<span class="sp-meta">
				<?php
				printf(
					/* translators: %d: jumlah style. */
					esc_html( _n( '%d style', '%d styles', $style_count, 'aksara' ) ),
					absint( $style_count )
				);
				if ( $categories ) {
					echo ' · ' . wp_kses_post( $categories );
				}
				?>
			</span>
		</div>

		<div class="sp-actions">
			<span class="sp-price"><?php echo wp_kses_post( $product->get_price_html() ); ?></span>
			<?php if ( function_exists( 'aksara_wishlist_button' ) ) : ?>
				<?php aksara_wishlist_button( $product->get_id() ); ?>
			<?php endif; ?>
			<a class="btn-trial" href="<?php echo esc_url( get_permalink() . '#aksara-font-tool' ); ?>"><?php esc_html_e( 'Try', 'aksara' ); ?></a>
			<a class="btn-view" href="<?php the_permalink(); ?>"><?php esc_html_e( 'View', 'aksara' ); ?></a>
		</div>
	</div>

	<a class="sp-specimen" href="<?php the_permalink(); ?>" aria-hidden="true" tabindex="-1">
		<?php if ( $specimen ) : ?>
			<?php echo wp_kses_post( $specimen ); ?>
		<?php else : ?>
			<span class="sp-specimen-fallback"><?php the_title(); ?></span>
		<?php endif; ?>
	</a>
</div>
