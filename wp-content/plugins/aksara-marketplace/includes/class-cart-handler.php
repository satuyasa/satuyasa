<?php
/**
 * Tambah-ke-cart untuk produk Font: pilih BANYAK style + 1 lisensi sekaligus,
 * harga dijumlah dari matriks aksara_style_prices (dengan diskon paket
 * lengkap opsional). Ini versi Fase 2 — jalur penambahan utamanya sekarang
 * lewat endpoint REST `POST /aksara/v1/cart/add-font` yang dipanggil
 * typing tool interaktif (assets/js/font-typing-tool.js), bukan form HTML
 * biasa lagi seperti Fase 1.
 *
 * 1 cart item = 1 kombinasi (array style_id + 1 license_id), sesuai aturan
 * di PRD Bagian 5: WooCommerce otomatis memperlakukan kombinasi data custom
 * yang berbeda sebagai baris cart terpisah (lihat generate_cart_id()), jadi
 * memilih 2 lisensi berbeda otomatis jadi 2 baris invoice terpisah.
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
		add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'apply_custom_price' ) );
		add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'display_item_data' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'persist_order_item_meta' ), 10, 4 );
	}

	/**
	 * Render typing tool + kalkulator lisensi interaktif di halaman single product font.
	 */
	public static function render_add_to_cart_form() {
		global $product;

		if ( ! $product instanceof WC_Product_Font ) {
			return;
		}

		$styles   = Aksara_Font_Styles_Repository::get_by_product( $product->get_id() );
		$licenses = Aksara_Font_Licenses_Repository::get_all();
		$matrix   = Aksara_Font_Licenses_Repository::get_price_matrix_for_product( $product->get_id() );

		wp_enqueue_script( 'aksara-font-typing-tool' );
		wp_localize_script( 'aksara-font-typing-tool', 'aksaraFontTool', self::build_js_config( $product, $styles, $licenses, $matrix ) );

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
	 * Siapkan data yang dibutuhkan assets/js/font-typing-tool.js: daftar
	 * style/lisensi, matriks harga (mentah + terformat), endpoint REST,
	 * dan pengaturan mata uang supaya JS bisa memformat harga tanpa
	 * menduplikasi logika currency WooCommerce.
	 *
	 * @param WC_Product_Font $product  Produk font.
	 * @param array            $styles   Baris dari aksara_font_styles.
	 * @param array            $licenses Baris dari aksara_font_licenses.
	 * @param array            $matrix   style_id => [license_id => price].
	 * @return array
	 */
	private static function build_js_config( $product, $styles, $licenses, $matrix ) {
		$preview_text = class_exists( 'Aksara_Specimen_Image' )
			? Aksara_Specimen_Image::get_default_preview_text()
			: __( 'Morning coffee, bold new ideas', 'aksara-marketplace' );

		$styles_payload = array();
		foreach ( $styles as $style ) {
			$styles_payload[] = array(
				'id'       => (int) $style->id,
				'name'     => $style->style_name,
				'weight'   => (int) $style->font_weight,
				'italic'   => (bool) $style->is_italic,
				// Gambar specimen statis: dipakai JS sebagai fallback kalau
				// microservice pratinjau sedang tidak bisa dihubungi, supaya
				// pengunjung tetap melihat wujud font aslinya alih-alih cuma
				// pesan error. Kosong kalau style tidak bisa dirender (.woff2).
				'specimen' => class_exists( 'Aksara_Specimen_Image' )
					? Aksara_Specimen_Image::get_url( $style, $preview_text, 40 )
					: '',
			);
		}

		$licenses_payload = array();
		foreach ( $licenses as $license ) {
			$licenses_payload[] = array(
				'id'   => (int) $license->id,
				'name' => $license->name,
			);
		}

		$price_payload = array();
		foreach ( $matrix as $style_id => $by_license ) {
			foreach ( $by_license as $license_id => $price ) {
				$price_payload[ $style_id ][ $license_id ] = array(
					'price'     => (float) $price,
					'formatted' => wp_strip_all_tags( wc_price( $price ) ),
				);
			}
		}

		return array(
			'productId'          => $product->get_id(),
			'styles'             => $styles_payload,
			'licenses'           => $licenses_payload,
			'prices'             => $price_payload,
			'bundleDiscount'     => (float) get_post_meta( $product->get_id(), '_aksara_bundle_discount_percent', true ),
			'currency'           => array(
				'symbol'            => get_woocommerce_currency_symbol(),
				'decimals'          => wc_get_price_decimals(),
				'decimalSeparator'  => wc_get_price_decimal_separator(),
				'thousandSeparator' => wc_get_price_thousand_separator(),
				'position'          => get_option( 'woocommerce_currency_pos', 'left' ),
			),
			'restUrl'            => esc_url_raw( rest_url( 'aksara/v1' ) ),
			'nonce'              => wp_create_nonce( 'wp_rest' ),
			'cartUrl'            => function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : '',
			'debounceMs'         => 1000, // Keputusan produk: debounce ~1 detik, bukan real-time per-karakter.
			'maxPreviewChars'    => 100,
			'defaultPreviewText' => $preview_text,
			'i18n'               => array(
				'selectStyle'    => __( 'Select at least one style.', 'aksara-marketplace' ),
				'selectLicense'  => __( 'Choose a license type.', 'aksara-marketplace' ),
				'adding'         => __( 'Adding…', 'aksara-marketplace' ),
				'added'          => __( 'Added to cart!', 'aksara-marketplace' ),
				'error'          => __( 'Something went wrong, please try again.', 'aksara-marketplace' ),
				'selectAll'      => __( 'Select All (Complete Family)', 'aksara-marketplace' ),
				'previewUnavailable' => __( 'Preview unavailable', 'aksara-marketplace' ),
				'previewFallback'    => __( 'Showing a static specimen — live typing preview is unavailable right now.', 'aksara-marketplace' ),
				// Cadangan sisi klien untuk kuota codepoint. Normalnya pesan
				// dari server (yang lebih spesifik) yang dipakai; ini hanya
				// terpakai kalau body respons gagal diurai.
				'previewBudget'      => __( 'You have reached the preview limit for this style. Try again tomorrow, or buy the style to use its full character set.', 'aksara-marketplace' ),
				'loading'        => __( 'Loading preview…', 'aksara-marketplace' ),
			),
		);
	}

	/**
	 * Validasi kombinasi style_ids + license_id, hitung total harga (dengan
	 * diskon paket lengkap bila berlaku). Dipakai REST controller sebelum
	 * memasukkan item ke cart — SATU-SATUNYA tempat harga final dihitung,
	 * supaya nilai dari klien tidak pernah dipercaya langsung.
	 *
	 * @param int   $product_id ID produk font.
	 * @param array $style_ids  Daftar ID style yang dipilih.
	 * @param int   $license_id ID lisensi yang dipilih.
	 * @return array|WP_Error {style_ids,style_names,license_id,license_name,price,is_bundle} atau WP_Error.
	 */
	public static function validate_combo( $product_id, $style_ids, $license_id ) {
		$product = wc_get_product( $product_id );
		if ( ! $product instanceof WC_Product_Font ) {
			return new WP_Error( 'aksara_invalid_product', __( 'Invalid product.', 'aksara-marketplace' ), array( 'status' => 400 ) );
		}

		$style_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $style_ids ) ) ) );
		if ( empty( $style_ids ) ) {
			return new WP_Error( 'aksara_missing_style', __( 'Select at least one style.', 'aksara-marketplace' ), array( 'status' => 400 ) );
		}

		$license = Aksara_Font_Licenses_Repository::get( $license_id );
		if ( ! $license ) {
			return new WP_Error( 'aksara_invalid_license', __( 'Invalid license type.', 'aksara-marketplace' ), array( 'status' => 400 ) );
		}

		$all_styles     = Aksara_Font_Styles_Repository::get_by_product( $product_id );
		$styles_by_id   = wp_list_pluck( $all_styles, null, 'id' );
		$priced_all_ids = array();

		$total       = 0.0;
		$style_names = array();

		foreach ( $all_styles as $style ) {
			if ( null !== Aksara_Font_Licenses_Repository::get_style_price( $style->id, $license_id ) ) {
				$priced_all_ids[] = (int) $style->id;
			}
		}

		foreach ( $style_ids as $style_id ) {
			if ( ! isset( $styles_by_id[ $style_id ] ) ) {
				return new WP_Error( 'aksara_invalid_style', __( 'One of the selected styles is not valid.', 'aksara-marketplace' ), array( 'status' => 400 ) );
			}

			$price = Aksara_Font_Licenses_Repository::get_style_price( $style_id, $license_id );
			if ( null === $price ) {
				return new WP_Error(
					'aksara_price_unset',
					sprintf(
						/* translators: %s: nama style. */
						__( 'Style "%s" has no price for this license yet.', 'aksara-marketplace' ),
						$styles_by_id[ $style_id ]->style_name
					),
					array( 'status' => 400 )
				);
			}

			$total       += (float) $price;
			$style_names[] = $styles_by_id[ $style_id ]->style_name;
		}

		// Diskon paket lengkap: hanya berlaku kalau SELURUH style yang punya
		// harga untuk lisensi ini ikut dipilih (bukan sebagian).
		$is_bundle = ! empty( $priced_all_ids )
			&& empty( array_diff( $priced_all_ids, $style_ids ) )
			&& empty( array_diff( $style_ids, $priced_all_ids ) );

		$discount_percent = (float) get_post_meta( $product_id, '_aksara_bundle_discount_percent', true );
		if ( $is_bundle && $discount_percent > 0 ) {
			$total = $total * ( 1 - ( $discount_percent / 100 ) );
		}

		return array(
			'style_ids'      => $style_ids,
			'style_names'    => $style_names,
			'license_id'     => (int) $license->id,
			'license_name'   => $license->name,
			'price'          => round( $total, 2 ),
			'is_bundle'      => $is_bundle,
		);
	}

	/**
	 * Terapkan harga kombinasi ke item cart (bukan harga produk default).
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
	 * @param array $item_data Data item yang sudah ada.
	 * @param array $cart_item Data cart item saat ini.
	 * @return array
	 */
	public static function display_item_data( $item_data, $cart_item ) {
		if ( empty( $cart_item['aksara'] ) ) {
			return $item_data;
		}

		$item_data[] = array(
			'name'  => _n( 'Style', 'Styles', count( $cart_item['aksara']['style_names'] ), 'aksara-marketplace' ),
			'value' => implode( ', ', $cart_item['aksara']['style_names'] ),
		);
		$item_data[] = array(
			'name'  => __( 'License', 'aksara-marketplace' ),
			'value' => $cart_item['aksara']['license_name'] . ( ! empty( $cart_item['aksara']['is_bundle'] ) ? ' — ' . __( 'Complete Family', 'aksara-marketplace' ) : '' ),
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

		$item->add_meta_data( __( 'Style', 'aksara-marketplace' ), implode( ', ', $values['aksara']['style_names'] ) );
		$item->add_meta_data( __( 'License', 'aksara-marketplace' ), $values['aksara']['license_name'] );
		$item->add_meta_data( '_aksara_style_ids', implode( ',', $values['aksara']['style_ids'] ) );
		$item->add_meta_data( '_aksara_license_id', $values['aksara']['license_id'] );
	}
}
