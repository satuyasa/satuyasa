<?php
/**
 * Satu baris di daftar font (Home & halaman Fonts).
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
 * baris ini otomatis mundur ke teks biasa dalam font tema.
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

$specimen = function_exists( 'aksara_font_specimen' )
	? aksara_font_specimen( $product->get_id(), get_the_title(), 34 )
	: '';
?>
<div class="specimen-row">
	<div>
		<div class="sp-name">
			<?php if ( $specimen ) : ?>
				<?php echo wp_kses_post( $specimen ); ?>
			<?php else : ?>
				<?php the_title(); ?>
			<?php endif; ?>
		</div>
		<div class="sp-meta">
			<?php
			printf(
				/* translators: %d: jumlah style. */
				esc_html( _n( '%d style', '%d style', $style_count, 'aksara' ) ),
				$style_count
			);
			if ( $categories ) {
				echo ' · ' . wp_kses_post( $categories );
			}
			?>
		</div>
	</div>
	<div class="sp-price"><?php echo wp_kses_post( $product->get_price_html() ); ?></div>
	<a class="sp-view" href="<?php the_permalink(); ?>"><?php esc_html_e( 'Lihat →', 'aksara' ); ?></a>
	<?php if ( function_exists( 'aksara_wishlist_button' ) ) : ?>
		<?php aksara_wishlist_button( $product->get_id() ); ?>
	<?php endif; ?>
</div>
