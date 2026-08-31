<?php
/**
 * Product type: Font (family), harga dinamis dari matriks style x lisensi.
 *
 * @package Aksara_Marketplace
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WC_Product_Font.
 *
 * Tidak extend WC_Product_Simple karena font family TIDAK punya satu
 * harga tetap — harga tergantung kombinasi style + lisensi yang dipilih
 * pembeli (lihat PRD Bagian 4.1). Harga "resmi" produk (_price/_regular_price)
 * sengaja tidak dipakai; get_price()/get_price_html() dihitung dari tabel
 * aksara_style_prices agar selalu konsisten dengan data yang admin isi
 * lewat metabox Font Styles.
 */
class WC_Product_Font extends WC_Product {

	/**
	 * @return string
	 */
	public function get_type() {
		return 'font';
	}

	/**
	 * Harga terendah dari seluruh kombinasi style x lisensi milik produk ini.
	 *
	 * @param string $context Konteks (tidak dipakai, untuk kompatibilitas signature WC_Product).
	 * @return string
	 */
	public function get_price( $context = 'view' ) {
		$min = Aksara_Font_Licenses_Repository::get_min_price_for_product( $this->get_id() );
		return null === $min ? '' : (string) $min;
	}

	/**
	 * Tampilkan "Mulai dari Rp X" di listing & single product.
	 *
	 * @param string $price Harga yang akan ditampilkan (diabaikan, dihitung ulang).
	 * @return string
	 */
	public function get_price_html( $price = '' ) {
		$min = Aksara_Font_Licenses_Repository::get_min_price_for_product( $this->get_id() );

		if ( null === $min ) {
			return '<span class="price aksara-price-unset">' . esc_html__( 'Price not set', 'aksara-marketplace' ) . '</span>';
		}

		return '<span class="price">' . sprintf(
			/* translators: %s: harga terendah. */
			esc_html__( 'From %s', 'aksara-marketplace' ),
			wc_price( $min )
		) . '</span>';
	}

	/**
	 * Produk font hanya bisa dibeli jika sudah ada minimal 1 style dengan
	 * minimal 1 harga lisensi terisi.
	 *
	 * @return bool
	 */
	public function is_purchasable() {
		$has_price = null !== Aksara_Font_Licenses_Repository::get_min_price_for_product( $this->get_id() );
		return $has_price && parent::is_purchasable();
	}

	/**
	 * Font family tidak dijual per-stok unit fisik.
	 *
	 * @return bool
	 */
	public function is_sold_individually() {
		return false;
	}

	/**
	 * Digital goods: never shipped.
	 *
	 * WooCommerce decides whether checkout must collect a shipping address
	 * from WC_Product::needs_shipping(), which is simply !is_virtual().
	 * The "Virtual" checkbox that would normally set that flag is rendered
	 * with class `show_if_simple`, so WooCommerce hides it for custom
	 * product types — meaning nobody could ever tick it, and every order
	 * asked the buyer for a shipping address for a file that is delivered
	 * by download link. Forcing it here is also more honest than a
	 * checkbox: there is no configuration in which one of these products
	 * needs shipping.
	 *
	 * NOT marked downloadable on purpose: WooCommerce's own downloadable-
	 * files machinery is deliberately bypassed in favour of the bearer
	 * tokens in Aksara_Download_Manager (revocable on refund, never a
	 * guessable public path).
	 *
	 * @param string $context Unused; kept for signature compatibility.
	 * @return bool
	 */
	public function get_virtual( $context = 'view' ) {
		return true;
	}

	/**
	 * @return bool
	 */
	public function needs_shipping() {
		return false;
	}
}
