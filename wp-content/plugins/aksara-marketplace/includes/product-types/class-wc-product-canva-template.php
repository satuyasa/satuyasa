<?php
/**
 * Product type: Canva Template — extend WC_Product_Simple agar UI harga,
 * stok, dan tombol beli standar WooCommerce langsung bisa dipakai.
 *
 * @package Aksara_Marketplace
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WC_Product_Canva_Template.
 */
class WC_Product_Canva_Template extends WC_Product_Simple {

	/**
	 * @return string
	 */
	public function get_type() {
		return 'canva_template';
	}
}
