<?php
/**
 * Form beli untuk produk Font: pilih 1 style + 1 lisensi (versi statis Fase 1,
 * belum ada typing tool/kalkulator multi-style — lihat mockup-font-product.html
 * untuk UI lengkap yang menyusul di Fase 2).
 *
 * Variabel yang tersedia: $product (WC_Product_Font), $styles (array of
 * stdClass dari aksara_font_styles), $licenses (array dari aksara_font_licenses),
 * $matrix (style_id => [license_id => price]).
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

if ( ! $product->is_purchasable() ) {
	return;
}
?>
<form class="cart aksara-font-purchase-form" action="<?php echo esc_url( apply_filters( 'woocommerce_add_to_cart_form_action', $product->get_permalink() ) ); ?>" method="post" enctype="multipart/form-data"
	data-price-matrix="<?php echo esc_attr( wp_json_encode( $matrix ) ); ?>">

	<p>
		<label for="aksara_style_id"><?php esc_html_e( 'Pilih Style', 'aksara-marketplace' ); ?></label>
		<select name="aksara_style_id" id="aksara_style_id" required>
			<option value=""><?php esc_html_e( '— Pilih style —', 'aksara-marketplace' ); ?></option>
			<?php foreach ( $styles as $style ) : ?>
				<?php if ( empty( $matrix[ $style->id ] ) ) : ?>
					<?php continue; // Style tanpa harga lisensi apa pun belum siap dijual. ?>
				<?php endif; ?>
				<option value="<?php echo esc_attr( $style->id ); ?>">
					<?php echo esc_html( $style->style_name ); ?>
					(<?php echo esc_html( $style->font_weight ); ?><?php echo $style->is_italic ? ' ' . esc_html__( 'Italic', 'aksara-marketplace' ) : ''; ?>)
				</option>
			<?php endforeach; ?>
		</select>
	</p>

	<p>
		<label for="aksara_license_id"><?php esc_html_e( 'Pilih Jenis Lisensi', 'aksara-marketplace' ); ?></label>
		<select name="aksara_license_id" id="aksara_license_id" required>
			<option value=""><?php esc_html_e( '— Pilih style dulu —', 'aksara-marketplace' ); ?></option>
			<?php foreach ( $licenses as $license ) : ?>
				<option value="<?php echo esc_attr( $license->id ); ?>"><?php echo esc_html( $license->name ); ?></option>
			<?php endforeach; ?>
		</select>
	</p>

	<p class="aksara-selected-price">
		<strong><?php esc_html_e( 'Harga:', 'aksara-marketplace' ); ?></strong>
		<span id="aksara_selected_price"><?php esc_html_e( '—', 'aksara-marketplace' ); ?></span>
	</p>

	<input type="hidden" name="quantity" value="1">
	<button type="submit" name="add-to-cart" value="<?php echo esc_attr( $product->get_id() ); ?>" class="single_add_to_cart_button button alt">
		<?php esc_html_e( 'Tambah ke Keranjang', 'aksara-marketplace' ); ?>
	</button>
</form>
