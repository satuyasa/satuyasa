<?php
/**
 * Logika dasar tambah-ke-cart untuk produk Font: pilih 1 style + 1 lisensi,
 * harga diambil dari matriks aksara_style_prices. Belum ada kalkulator
 * multi-style/bundle — itu menyusul di Fase 2 bersama typing tool
 * (lihat mockup-font-product.html untuk UI lengkapnya nanti).
 *
 * 1 cart item = 1 kombinasi style + lisensi, sesuai aturan di PRD Bagian 5:
 * WooCommerce otomatis memperlakukan kombinasi data custom yang berbeda
 * sebagai baris cart terpisah (lihat generate_cart_id()), jadi memilih
 * 2 lisensi berbeda untuk style yang sama otomatis jadi 2 baris invoice.
 *
 * @package Aksara_Marketplace
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Aksara_Cart_Handler.
 */
class Aksara_Cart_Handler {

	/**
	 * Pasang hook.
	 */
	public static function init() {
		add_action( 'woocommerce_font_add_to_cart', array( __CLASS__, 'render_add_to_cart_form' ) );
		add_filter( 'woocommerce_add_to_cart_validation', array( __CLASS__, 'validate_add_to_cart' ), 10, 3 );
		add_filter( 'woocommerce_add_cart_item_data', array( __CLASS__, 'add_cart_item_data' ), 10, 3 );
		add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'apply_custom_price' ) );
		add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'display_item_data' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'persist_order_item_meta' ), 10, 4 );
	}

	/**
	 * Tampilkan form pilih style + lisensi di halaman single product font.
	 */
	public static function render_add_to_cart_form() {
		global $product;

		if ( ! $product instanceof WC_Product_Font ) {
			return;
		}

		$styles   = Aksara_Font_Styles_Repository::get_by_product( $product->get_id() );
		$licenses = Aksara_Font_Licenses_Repository::get_all();
		$matrix   = Aksara_Font_Licenses_Repository::get_price_matrix_for_product( $product->get_id() );

		wc_get_template(
			'single-product/add-to-cart/font.php',
			array(
				'product'  => $product,
				'styles'   => $styles,
				'licenses' => $licenses,
				'matrix'   => $matrix,
			),
			'',
			AKSARA_MARKETPLACE_DIR . 'templates/'
		);
	}

	/**
	 * Pastikan kombinasi style+lisensi valid & punya harga sebelum ditambahkan ke cart.
	 *
	 * @param bool $passed     Status validasi saat ini.
	 * @param int  $product_id ID produk yang ditambahkan.
	 * @param int  $quantity   Jumlah.
	 * @return bool
	 */
	public static function validate_add_to_cart( $passed, $product_id, $quantity ) {
		$product = wc_get_product( $product_id );
		if ( ! $product instanceof WC_Product_Font ) {
			return $passed;
		}

		$combo = self::get_requested_combo( $product_id );
		if ( is_wp_error( $combo ) ) {
			wc_add_notice( $combo->get_error_message(), 'error' );
			return false;
		}

		return $passed;
	}

	/**
	 * Ambil & validasi style_id/license_id dari request saat ini.
	 *
	 * @param int $product_id ID produk font.
	 * @return array|WP_Error {style, license, price} atau WP_Error.
	 */
	private static function get_requested_combo( $product_id ) {
		$style_id   = isset( $_REQUEST['aksara_style_id'] ) ? absint( $_REQUEST['aksara_style_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- WooCommerce add-to-cart itself is not nonce-protected by default.
		$license_id = isset( $_REQUEST['aksara_license_id'] ) ? absint( $_REQUEST['aksara_license_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! $style_id || ! $license_id ) {
			return new WP_Error( 'aksara_missing_selection', __( 'Pilih style dan jenis lisensi terlebih dahulu.', 'aksara-marketplace' ) );
		}

		$style = Aksara_Font_Styles_Repository::get( $style_id );
		if ( ! $style || (int) $style->product_id !== (int) $product_id ) {
			return new WP_Error( 'aksara_invalid_style', __( 'Style yang dipilih tidak valid.', 'aksara-marketplace' ) );
		}

		$license = Aksara_Font_Licenses_Repository::get( $license_id );
		if ( ! $license ) {
			return new WP_Error( 'aksara_invalid_license', __( 'Jenis lisensi yang dipilih tidak valid.', 'aksara-marketplace' ) );
		}

		$price = Aksara_Font_Licenses_Repository::get_style_price( $style_id, $license_id );
		if ( null === $price ) {
			return new WP_Error( 'aksara_price_unset', __( 'Kombinasi style dan lisensi ini belum memiliki harga.', 'aksara-marketplace' ) );
		}

		return array(
			'style'   => $style,
			'license' => $license,
			'price'   => $price,
		);
	}

	/**
	 * Sisipkan style/license/price terpilih ke data cart item.
	 *
	 * @param array $cart_item_data Data cart item saat ini.
	 * @param int   $product_id     ID produk.
	 * @param int   $variation_id   ID variasi (tidak dipakai, produk font bukan variable product).
	 * @return array
	 */
	public static function add_cart_item_data( $cart_item_data, $product_id, $variation_id ) {
		$product = wc_get_product( $product_id );
		if ( ! $product instanceof WC_Product_Font ) {
			return $cart_item_data;
		}

		$combo = self::get_requested_combo( $product_id );
		if ( is_wp_error( $combo ) ) {
			return $cart_item_data;
		}

		$cart_item_data['aksara'] = array(
			'style_id'     => (int) $combo['style']->id,
			'style_name'   => $combo['style']->style_name,
			'license_id'   => (int) $combo['license']->id,
			'license_name' => $combo['license']->name,
			'price'        => (float) $combo['price'],
		);

		return $cart_item_data;
	}

	/**
	 * Terapkan harga kombinasi style+lisensi ke item cart (bukan harga produk default).
	 *
	 * @param WC_Cart $cart Objek cart WooCommerce.
	 */
	public static function apply_custom_price( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		foreach ( $cart->get_cart() as $cart_item ) {
			if ( isset( $cart_item['aksara']['price'] ) ) {
				$cart_item['data']->set_price( $cart_item['aksara']['price'] );
			}
		}
	}

	/**
	 * Tampilkan "Style" & "Lisensi" sebagai meta tambahan di cart/checkout.
	 *
	 * @param array $item_data  Data item yang sudah ada.
	 * @param array $cart_item  Data cart item saat ini.
	 * @return array
	 */
	public static function display_item_data( $item_data, $cart_item ) {
		if ( empty( $cart_item['aksara'] ) ) {
			return $item_data;
		}

		$item_data[] = array(
			'name'  => __( 'Style', 'aksara-marketplace' ),
			'value' => $cart_item['aksara']['style_name'],
		);
		$item_data[] = array(
			'name'  => __( 'Lisensi', 'aksara-marketplace' ),
			'value' => $cart_item['aksara']['license_name'],
		);

		return $item_data;
	}

	/**
	 * Simpan style/lisensi sebagai meta pada order line item, supaya tetap
	 * tercatat di order walau data cart sudah hilang (dipakai lagi untuk
	 * generate sertifikat lisensi & pengiriman file di Fase 3).
	 *
	 * @param WC_Order_Item_Product $item          Order line item.
	 * @param string                $cart_item_key Kunci cart item.
	 * @param array                 $values        Data cart item.
	 * @param WC_Order              $order         Order terkait.
	 */
	public static function persist_order_item_meta( $item, $cart_item_key, $values, $order ) {
		if ( empty( $values['aksara'] ) ) {
			return;
		}

		$item->add_meta_data( __( 'Style', 'aksara-marketplace' ), $values['aksara']['style_name'] );
		$item->add_meta_data( __( 'Lisensi', 'aksara-marketplace' ), $values['aksara']['license_name'] );
		$item->add_meta_data( '_aksara_style_id', $values['aksara']['style_id'] );
		$item->add_meta_data( '_aksara_license_id', $values['aksara']['license_id'] );
	}
}
