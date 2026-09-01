<?php
/**
 * Daftarkan 3 product type kustom (font, canva_template, canva_element)
 * sebagai WooCommerce product type — tetap WooCommerce Product biasa,
 * dibedakan lewat term taksonomi `product_type` (bukan CPT terpisah),
 * sesuai keputusan di Starter Brief Bagian 1.
 *
 * @package Aksara_Marketplace
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Aksara_Product_Type_Registrar.
 */
class Aksara_Product_Type_Registrar {

	/**
	 * Pasang hook.
	 */
	public static function init() {
		if ( ! function_exists( 'aksara_marketplace_uses_authentype' ) || ! aksara_marketplace_uses_authentype() ) {
			require_once AKSARA_MARKETPLACE_DIR . 'includes/product-types/class-wc-product-font.php';
		}
		require_once AKSARA_MARKETPLACE_DIR . 'includes/product-types/class-wc-product-canva-template.php';
		require_once AKSARA_MARKETPLACE_DIR . 'includes/product-types/class-wc-product-canva-element.php';

		add_filter( 'product_type_selector', array( __CLASS__, 'add_to_type_selector' ) );
		add_filter( 'woocommerce_product_class', array( __CLASS__, 'map_product_class' ), 10, 2 );

		// WooCommerce memanggil `woocommerce_{type}_add_to_cart` di halaman single
		// product untuk tiap product type — termasuk type kustom. Untuk Canva
		// Template/Element yang extend WC_Product_Simple, cukup pakai template
		// add-to-cart simple bawaan WooCommerce. Untuk 'font', form kustomnya
		// didaftarkan oleh Aksara_Cart_Handler (perlu pilih style + lisensi dulu).
		add_action( 'woocommerce_canva_template_add_to_cart', array( __CLASS__, 'render_simple_add_to_cart' ) );
		add_action( 'woocommerce_canva_element_add_to_cart', array( __CLASS__, 'render_simple_add_to_cart' ) );
	}

	/**
	 * Tambahkan 3 opsi ke dropdown "Product type" di admin edit produk.
	 *
	 * @param array $types Daftar product type yang sudah ada.
	 * @return array
	 */
	public static function add_to_type_selector( $types ) {
		if ( ! function_exists( 'aksara_marketplace_uses_authentype' ) || ! aksara_marketplace_uses_authentype() ) {
			$types['font'] = __( 'Font (Aksara)', 'aksara-marketplace' );
		}
		$types['canva_template'] = __( 'Canva Template (Aksara)', 'aksara-marketplace' );
		$types['canva_element']  = __( 'Canva Element (Aksara)', 'aksara-marketplace' );
		return $types;
	}

	/**
	 * Petakan product type ke nama class PHP-nya.
	 *
	 * @param string $classname    Nama class default dari WooCommerce.
	 * @param string $product_type Slug product type.
	 * @return string
	 */
	public static function map_product_class( $classname, $product_type ) {
		$map = array(
			'canva_template' => 'WC_Product_Canva_Template',
			'canva_element'  => 'WC_Product_Canva_Element',
		);
		if ( ! function_exists( 'aksara_marketplace_uses_authentype' ) || ! aksara_marketplace_uses_authentype() ) {
			$map['font'] = 'WC_Product_Font';
		}

		return isset( $map[ $product_type ] ) ? $map[ $product_type ] : $classname;
	}

	/**
	 * Render tombol/form beli standar (sama seperti simple product) untuk
	 * Canva Template & Canva Element.
	 */
	public static function render_simple_add_to_cart() {
		wc_get_template( 'single-product/add-to-cart/simple.php' );
	}
}
