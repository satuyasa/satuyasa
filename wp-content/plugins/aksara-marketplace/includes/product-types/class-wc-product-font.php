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
			return '<span class="price aksara-price-unset">' . esc_html__( 'Harga belum diatur', 'aksara-marketplace' ) . '</span>';
		}

		return '<span class="price">' . sprintf(
			/* translators: %s: harga terendah. */
			esc_html__( 'Mulai dari %s', 'aksara-marketplace' ),
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
}
