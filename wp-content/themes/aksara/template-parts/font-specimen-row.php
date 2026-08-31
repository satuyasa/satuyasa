<?php
/**
 * Satu baris di daftar "Font pilihan" pada Home.
 *
 * CATATAN KEAMANAN: baris ini SENGAJA menampilkan nama produk dalam font
 * UI tema (Fraunces), BUKAN dalam font asli yang dijual. Merender teks
 * memakai file font produk lewat @font-face publik di sini akan
 * mengekspos file yang justru coba dilindungi seluruh sistem preview di
 * PRD Bagian 4.3. Pratinjau interaktif memakai font asli (typing tool)
 * baru masuk di Fase 2, lewat microservice subsetting
 * (services/font-preview-service/) yang hanya mengirim glyph terbatas
 * dengan token kedaluwarsa — bukan file font utuh seperti di sini.
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
?>
<div class="specimen-row">
	<div>
		<div class="sp-name"><?php the_title(); ?></div>
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
</div>
