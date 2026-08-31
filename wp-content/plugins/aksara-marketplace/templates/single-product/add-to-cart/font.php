<?php
/**
 * Typing tool + kalkulator lisensi interaktif untuk produk Font (Fase 2),
 * strukturnya mengikuti mockup-font-product.html — bedanya di sini semua
 * data (style, lisensi, harga) nyata dari database, dan pratinjau font
 * TIDAK memuat file font asli lewat @font-face publik: JS mengambilnya
 * lewat REST endpoint aksara/v1/font-preview yang hanya mengirim subset
 * glyph terbatas (lihat assets/js/font-typing-tool.js).
 *
 * Variabel yang tersedia: $product (WC_Product_Font), $styles, $licenses, $matrix.
 * Konfigurasi JS (aksaraFontTool) disiapkan & di-localize oleh
 * Aksara_Cart_Handler::render_add_to_cart_form() sebelum template ini dimuat.
 *
 * Theme boleh override template ini lewat woocommerce/single-product/add-to-cart/font.php.
 *
 * @package Aksara_Marketplace
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $styles ) ) {
	echo '<p class="aksara-no-styles">' . esc_html__( 'Produk ini belum memiliki style. Silakan cek kembali nanti.', 'aksara-marketplace' ) . '</p>';
	return;
}

if ( empty( $licenses ) || ! $product->is_purchasable() ) {
	echo '<p class="aksara-no-styles">' . esc_html__( 'Produk ini belum siap dijual (harga belum diatur).', 'aksara-marketplace' ) . '</p>';
	return;
}
?>
<noscript>
	<p class="aksara-no-styles"><?php esc_html_e( 'Aktifkan JavaScript untuk mencoba pratinjau interaktif dan membeli font ini.', 'aksara-marketplace' ); ?></p>
</noscript>

<div class="aksara-font-tool" id="aksaraFontTool" hidden>
	<div class="aksara-ft-main">
		<div class="aksara-ft-toolbar">
			<div class="aksara-ft-weight-tabs" id="aksaraWeightTabs"></div>
			<div class="aksara-ft-toolbar-right">
				<button type="button" class="aksara-ft-italic-toggle" id="aksaraItalicToggle"><?php esc_html_e( 'Italic', 'aksara-marketplace' ); ?></button>
				<label class="aksara-ft-size-slider">
					<?php esc_html_e( 'Ukuran', 'aksara-marketplace' ); ?>
					<input type="range" id="aksaraSizeSlider" min="24" max="96" value="52">
				</label>
			</div>
		</div>

		<div class="aksara-ft-preview-box">
			<div class="aksara-ft-preview-text" id="aksaraPreviewText" contenteditable="true" spellcheck="false"></div>
		</div>
		<p class="aksara-ft-preview-hint">
			<?php esc_html_e( 'Ketik teksmu sendiri untuk melihat tampilan font ini — ini hanya pratinjau, file font tidak dapat diunduh dari sini.', 'aksara-marketplace' ); ?>
			<span id="aksaraPreviewStatus"></span>
		</p>

		<div class="aksara-ft-styles-head">
			<h3><?php esc_html_e( 'Pilih style', 'aksara-marketplace' ); ?></h3>
			<span id="aksaraSelectedCount"></span>
		</div>
		<div class="aksara-ft-style-list" id="aksaraStyleList"></div>
		<button type="button" class="aksara-ft-select-all" id="aksaraSelectAll"></button>
	</div>

	<div class="aksara-ft-side">
		<h3><?php esc_html_e( 'Lisensi', 'aksara-marketplace' ); ?></h3>
		<p class="aksara-ft-side-hint"><?php esc_html_e( 'Pilih sesuai penggunaanmu.', 'aksara-marketplace' ); ?></p>
		<div id="aksaraLicenseList"></div>

		<div class="aksara-ft-price-summary">
			<div class="aksara-ft-price-row">
				<span id="aksaraStyleCountLabel"></span>
				<span id="aksaraStyleSubtotal"></span>
			</div>
			<div class="aksara-ft-price-total">
				<span><?php esc_html_e( 'Total', 'aksara-marketplace' ); ?></span>
				<span id="aksaraTotalPrice">—</span>
			</div>
		</div>

		<button type="button" class="button alt aksara-ft-cta" id="aksaraAddToCart" disabled>
			<?php esc_html_e( 'Tambah ke Keranjang', 'aksara-marketplace' ); ?>
		</button>
		<p class="aksara-ft-cta-note" id="aksaraCtaMessage"></p>
	</div>
</div>
