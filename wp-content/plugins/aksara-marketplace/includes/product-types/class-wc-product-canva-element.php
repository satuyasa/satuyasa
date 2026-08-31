<?php
/**
 * Product type: Canva Element — extend WC_Product_Simple, sama seperti
 * Canva Template tapi jenis produk terpisah agar bisa difilter/dilaporkan
 * berbeda (ikon, ilustrasi, shape, dst.).
 *
 * @package Aksara_Marketplace
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WC_Product_Canva_Element.
 */
class WC_Product_Canva_Element extends WC_Product_Simple {

	/**
	 * @return string
	 */
	public function get_type() {
		return 'canva_element';
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
