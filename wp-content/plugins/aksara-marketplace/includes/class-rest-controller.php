<?php
/**
 * REST API `aksara/v1` — endpoint yang dipakai typing tool & kalkulator
 * lisensi interaktif di halaman single produk font (Fase 2).
 *
 * @package Aksara_Marketplace
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Aksara_Rest_Controller.
 */
class Aksara_Rest_Controller {

	const NAMESPACE_ = 'aksara/v1';

	/**
	 * Maksimal request preview per IP per menit. Ini lapisan pertahanan
	 * yang DURABLE (transient, tersimpan di DB/object cache — bertahan
	 * lintas worker & restart PHP-FPM), berbeda dari rate limiter
	 * in-memory di font-preview-service (yang cuma backstop POC-grade).
	 * Lihat catatan di services/font-preview-service/README.md.
	 */
	const PREVIEW_RATE_LIMIT = 40;

	/**
	 * Pasang hook.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Daftarkan seluruh route.
	 */
	public static function register_routes() {
		register_rest_route(
			self::NAMESPACE_,
			'/font-preview',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'handle_font_preview' ),
				'permission_callback' => array( __CLASS__, 'check_preview_rate_limit' ),
				'args'                => array(
					'style_id' => array( 'required' => true, 'type' => 'integer' ),
					'text'     => array( 'required' => true, 'type' => 'string' ),
				),
			)
		);

		// Batch, bukan bagian literal dari daftar endpoint di PRD — ditambahkan
		// supaya grid daftar style (tiap baris render preview sendiri) tidak
		// perlu N request HTTP terpisah tiap kali user berhenti mengetik.
		register_rest_route(
			self::NAMESPACE_,
			'/font-preview-batch',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'handle_font_preview_batch' ),
				'permission_callback' => array( __CLASS__, 'check_preview_rate_limit' ),
				'args'                => array(
					'style_ids' => array( 'required' => true, 'type' => 'array' ),
					'text'      => array( 'required' => true, 'type' => 'string' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_,
			'/admin/style-prices',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( __CLASS__, 'handle_update_style_price' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_woocommerce' );
				},
				'args'                => array(
					'style_id'   => array( 'required' => true, 'type' => 'integer' ),
					'license_id' => array( 'required' => true, 'type' => 'integer' ),
					'price'      => array( 'required' => true, 'type' => 'number' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_,
			'/cart/add-font',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'handle_add_font_to_cart' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'product_id' => array( 'required' => true, 'type' => 'integer' ),
					'style_ids'  => array( 'required' => true, 'type' => 'array' ),
					'license_id' => array( 'required' => true, 'type' => 'integer' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_,
			'/download/(?P<token>[a-f0-9]{48})',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'handle_download' ),
				'permission_callback' => '__return_true', // Token itu sendiri adalah kredensialnya.
				'args'                => array(
					'token' => array( 'required' => true, 'type' => 'string' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_,
			'/certificate/(?P<order_id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'handle_certificate_download' ),
				'permission_callback' => function () {
					return is_user_logged_in();
				},
				'args'                => array(
					'order_id' => array( 'required' => true, 'type' => 'integer' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_,
			'/wishlist/toggle',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'handle_wishlist_toggle' ),
				'permission_callback' => function () {
					return is_user_logged_in();
				},
				'args'                => array(
					'product_id' => array( 'required' => true, 'type' => 'integer' ),
				),
			)
		);
	}

	/**
	 * permission_callback bersama untuk endpoint preview: menolak lebih awal
	 * kalau IP sudah melebihi kuota, sebelum microservice sempat dipanggil.
	 *
	 * @return true|WP_Error
	 */
	public static function check_preview_rate_limit() {
		$ip  = self::get_client_ip();
		$key = 'aksara_rl_' . md5( $ip ) . '_' . gmdate( 'YmdHi' ); // bucket per menit berjalan.

		$count = (int) get_transient( $key );
		if ( $count >= self::PREVIEW_RATE_LIMIT ) {
			return new WP_Error(
				'aksara_rate_limited',
				__( 'Terlalu banyak permintaan pratinjau. Coba lagi sebentar.', 'aksara-marketplace' ),
				array( 'status' => 429 )
			);
		}

		set_transient( $key, $count + 1, MINUTE_IN_SECONDS );
		return true;
	}

	/**
	 * Ambil IP klien. Bisa dioverride lewat filter 'aksara_client_ip' kalau
	 * situs berjalan di belakang reverse proxy tepercaya (X-Forwarded-For
	 * TIDAK dipercaya secara default karena mudah dipalsukan klien).
	 *
	 * @return string
	 */
	private static function get_client_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0.0.0.0';
		return apply_filters( 'aksara_client_ip', $ip );
	}

	/**
	 * Validasi style_id & teks, kembalikan objek style atau WP_Error.
	 * Dipakai bersama oleh handler single & batch supaya aturan validasinya
	 * tidak diduplikasi.
	 *
	 * @param int    $style_id ID style.
	 * @param string $text     Teks pratinjau mentah.
	 * @return array{style:object,text:string}|WP_Error
	 */
	private static function validate_preview_request( $style_id, $text ) {
		$text = trim( wp_strip_all_tags( (string) $text ) );

		if ( '' === $text ) {
			return new WP_Error( 'aksara_empty_text', __( 'Teks pratinjau tidak boleh kosong.', 'aksara-marketplace' ), array( 'status' => 400 ) );
		}

		if ( function_exists( 'mb_strlen' ) ? mb_strlen( $text ) > 100 : strlen( $text ) > 100 ) {
			return new WP_Error( 'aksara_text_too_long', __( 'Teks pratinjau maksimal 100 karakter.', 'aksara-marketplace' ), array( 'status' => 400 ) );
		}

		$style = Aksara_Font_Styles_Repository::get( $style_id );
		if ( ! $style ) {
			return new WP_Error( 'aksara_invalid_style', __( 'Style tidak ditemukan.', 'aksara-marketplace' ), array( 'status' => 404 ) );
		}

		// Hanya style milik produk font yang sudah publish yang boleh dipratinjaukan
		// — mencegah endpoint ini dipakai mengintip style produk draft/privat.
		if ( 'publish' !== get_post_status( $style->product_id ) ) {
			return new WP_Error( 'aksara_product_not_published', __( 'Produk belum dipublikasikan.', 'aksara-marketplace' ), array( 'status' => 403 ) );
		}

		return array(
			'style' => $style,
			'text'  => $text,
		);
	}

	/**
	 * POST /aksara/v1/font-preview — kembalikan woff2 subset untuk 1 style.
	 *
	 * Dikembalikan sebagai JSON {data_uri: "data:font/woff2;base64,..."},
	 * BUKAN bytes woff2 mentah dengan Content-Type font/woff2 — WP REST API
	 * selalu men-JSON-encode body respons lewat wp_json_encode() sebelum
	 * dikirim (lihat WP_REST_Server::serve_request()), jadi binary mentah
	 * akan rusak (ter-escape jadi string JSON) walau header Content-Type
	 * diset manual. Base64 di dalam JSON adalah cara aman & konsisten
	 * dengan /font-preview-batch di bawah.
	 *
	 * @param WP_REST_Request $request Request REST.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_font_preview( WP_REST_Request $request ) {
		$validated = self::validate_preview_request( $request->get_param( 'style_id' ), $request->get_param( 'text' ) );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		$woff2 = Aksara_Preview_Service_Client::get_subset( $validated['style'], $validated['text'] );
		if ( is_wp_error( $woff2 ) ) {
			return $woff2;
		}

		return new WP_REST_Response( array(
			'data_uri' => 'data:font/woff2;base64,' . base64_encode( $woff2 ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		) );
	}

	/**
	 * POST /aksara/v1/font-preview-batch — kembalikan beberapa style sekaligus
	 * sebagai JSON {style_id: "data:font/woff2;base64,..."}.
	 *
	 * @param WP_REST_Request $request Request REST.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_font_preview_batch( WP_REST_Request $request ) {
		$style_ids = array_map( 'absint', (array) $request->get_param( 'style_ids' ) );
		$style_ids = array_slice( array_unique( array_filter( $style_ids ) ), 0, 30 ); // batas wajar per batch.

		if ( empty( $style_ids ) ) {
			return new WP_Error( 'aksara_empty_style_ids', __( 'style_ids tidak boleh kosong.', 'aksara-marketplace' ), array( 'status' => 400 ) );
		}

		$results = array();
		foreach ( $style_ids as $style_id ) {
			$validated = self::validate_preview_request( $style_id, $request->get_param( 'text' ) );
			if ( is_wp_error( $validated ) ) {
				continue; // Lewati style yang tidak valid, jangan gagalkan seluruh batch.
			}

			$woff2 = Aksara_Preview_Service_Client::get_subset( $validated['style'], $validated['text'] );
			if ( is_wp_error( $woff2 ) ) {
				continue;
			}

			$results[ $style_id ] = 'data:font/woff2;base64,' . base64_encode( $woff2 ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		}

		return new WP_REST_Response( $results );
	}

	/**
	 * PUT /aksara/v1/admin/style-prices — perbarui satu harga style x lisensi.
	 *
	 * @param WP_REST_Request $request Request REST.
	 * @return WP_REST_Response
	 */
	public static function handle_update_style_price( WP_REST_Request $request ) {
		Aksara_Font_Licenses_Repository::set_style_price(
			$request->get_param( 'style_id' ),
			$request->get_param( 'license_id' ),
			$request->get_param( 'price' )
		);

		return new WP_REST_Response( array( 'success' => true ) );
	}

	/**
	 * POST /aksara/v1/cart/add-font — tambahkan kombinasi beberapa style +
	 * 1 lisensi ke cart WooCommerce sebagai satu baris.
	 *
	 * @param WP_REST_Request $request Request REST.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_add_font_to_cart( WP_REST_Request $request ) {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return new WP_Error( 'aksara_cart_unavailable', __( 'Keranjang belanja tidak tersedia.', 'aksara-marketplace' ), array( 'status' => 500 ) );
		}

		$product_id = absint( $request->get_param( 'product_id' ) );
		$license_id = absint( $request->get_param( 'license_id' ) );
		$style_ids  = array_map( 'absint', (array) $request->get_param( 'style_ids' ) );

		$combo = Aksara_Cart_Handler::validate_combo( $product_id, $style_ids, $license_id );
		if ( is_wp_error( $combo ) ) {
			return $combo;
		}

		$cart_item_key = WC()->cart->add_to_cart( $product_id, 1, 0, array(), array( 'aksara' => $combo ) );

		if ( ! $cart_item_key ) {
			return new WP_Error( 'aksara_add_to_cart_failed', __( 'Gagal menambahkan ke keranjang.', 'aksara-marketplace' ), array( 'status' => 500 ) );
		}

		return new WP_REST_Response( array(
			'success'         => true,
			'cart_item_key'   => $cart_item_key,
			'cart_count'      => WC()->cart->get_cart_contents_count(),
			'cart_total_html' => WC()->cart->get_cart_subtotal(),
			'cart_url'        => wc_get_cart_url(),
		) );
	}

	/**
	 * GET /aksara/v1/download/{token} — validasi token lalu stream file font
	 * asli atau redirect ke tautan Canva.
	 *
	 * CATATAN TEKNIS: untuk kasus 'stream', handler ini mengirim header +
	 * isi berkas langsung lalu memanggil exit() — BUKAN mengembalikan
	 * WP_REST_Response seperti endpoint lain. Ini disengaja: WP REST API
	 * selalu wp_json_encode() body respons (lihat catatan di
	 * handle_font_preview()), yang tidak cocok untuk mengirim berkas biner
	 * berukuran besar sebagai unduhan asli (Content-Disposition: attachment)
	 * dengan Save-As dialog browser yang benar. Pola "keluar dari siklus
	 * REST normal untuk endpoint file" ini umum dipakai plugin WordPress lain.
	 *
	 * @param WP_REST_Request $request Request REST.
	 * @return WP_Error Hanya kalau token TIDAK valid (kasus valid tidak pernah return).
	 */
	public static function handle_download( WP_REST_Request $request ) {
		$result = Aksara_Download_Manager::resolve( $request->get_param( 'token' ) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( 'redirect' === $result['type'] ) {
			wp_safe_redirect( $result['url'] );
			exit;
		}

		nocache_headers();
		header( 'Content-Type: application/octet-stream' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $result['filename'] ) . '"' );
		header( 'Content-Length: ' . filesize( $result['path'] ) );
		header( 'X-Content-Type-Options: nosniff' );
		readfile( $result['path'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_readfile
		exit;
	}

	/**
	 * GET /aksara/v1/certificate/{order_id} — unduh ulang PDF sertifikat lisensi.
	 *
	 * Beda dari /download/{token}: akses di sini digerbangi oleh status
	 * login (harus pemilik order atau admin), bukan token bearer — karena
	 * halaman ini memang hanya dipakai dari dalam My Account yang sudah
	 * mengharuskan login.
	 *
	 * @param WP_REST_Request $request Request REST.
	 * @return WP_Error Hanya kalau gagal (kasus sukses langsung stream & exit).
	 */
	public static function handle_certificate_download( WP_REST_Request $request ) {
		$order_id = absint( $request->get_param( 'order_id' ) );
		$order    = wc_get_order( $order_id );

		if ( ! $order ) {
			return new WP_Error( 'aksara_invalid_order', __( 'Order tidak ditemukan.', 'aksara-marketplace' ), array( 'status' => 404 ) );
		}

		$is_owner = get_current_user_id() && (int) $order->get_customer_id() === get_current_user_id();
		if ( ! $is_owner && ! current_user_can( 'manage_woocommerce' ) ) {
			return new WP_Error( 'aksara_forbidden', __( 'Anda tidak berhak mengakses sertifikat ini.', 'aksara-marketplace' ), array( 'status' => 403 ) );
		}

		$certificate = Aksara_License_Certificates_Repository::get_by_order( $order_id );
		if ( ! $certificate ) {
			return new WP_Error( 'aksara_no_certificate', __( 'Order ini tidak memiliki sertifikat lisensi.', 'aksara-marketplace' ), array( 'status' => 404 ) );
		}

		$path = Aksara_File_Storage::get_absolute_path( $certificate->file_path );
		if ( ! file_exists( $path ) ) {
			return new WP_Error( 'aksara_missing_resource', __( 'Berkas sertifikat tidak ditemukan di server.', 'aksara-marketplace' ), array( 'status' => 404 ) );
		}

		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: attachment; filename="sertifikat-order-' . $order_id . '.pdf"' );
		header( 'Content-Length: ' . filesize( $path ) );
		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_readfile
		exit;
	}

	/**
	 * POST /aksara/v1/wishlist/toggle — tambah/hapus produk dari wishlist user yang login.
	 *
	 * @param WP_REST_Request $request Request REST.
	 * @return WP_REST_Response
	 */
	public static function handle_wishlist_toggle( WP_REST_Request $request ) {
		$in_wishlist = Aksara_Wishlist_Repository::toggle( get_current_user_id(), absint( $request->get_param( 'product_id' ) ) );

		return new WP_REST_Response( array(
			'success'     => true,
			'in_wishlist' => $in_wishlist,
		) );
	}
}
