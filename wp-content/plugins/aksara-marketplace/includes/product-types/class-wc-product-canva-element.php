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
}
