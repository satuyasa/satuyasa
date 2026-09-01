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
	 * Batas jumlah codepoint BERBEDA yang boleh diterima satu klien untuk
	 * satu style, dalam satu jendela waktu (lihat PREVIEW_BUDGET_WINDOW).
	 *
	 * Kenapa ini ada — rate limit per menit saja TIDAK cukup. Setiap
	 * respons pratinjau adalah subset berisi glyph yang diketik. Masing-
	 * masing tidak berguna, tapi beberapa subset bisa digabung kembali
	 * jadi font yang makin lengkap. Diukur dengan font contoh di repo ini
	 * (Bricolage Grotesque, 527 codepoint): pada batas 100 karakter per
	 * request, seluruh charset bisa dipanen hanya dengan 6 request —
	 * sekitar 9 detik di bawah rate limit 40/menit. Jadi tanpa batas ini,
	 * rate limit hanya memperlambat pengunduhan font utuh selama 9 detik.
	 *
	 * Kenapa 120 — ini angka yang memisahkan pemakaian wajar dari pemanenan,
	 * diukur bukan ditebak:
	 *
	 *   kalimat pendek biasa .................. 13 codepoint unik
	 *   pangram Inggris ....................... 28
	 *   pangram + KAPITAL + angka ............. 50
	 *   seluruh ASCII yang bisa dicetak ....... 95
	 *   ---------------------------------------------
	 *   batas di sini ......................... 120  (ruang lega untuk (4))
	 *   seluruh charset font contoh ........... 527  (ini yang dilindungi)
	 *
	 * Jadi pengunjung yang mengetik sewajarnya tidak akan pernah
	 * menyentuhnya, sementara pemanen hanya mendapat ~23% charset per IP
	 * per hari — dan bagian yang justru bernilai (432 codepoint aksen,
	 * simbol, mata uang di luar ASCII) tetap tidak terjangkau.
	 *
	 * Bisa disetel lewat filter 'aksara_preview_codepoint_budget'; set 0
	 * untuk mematikan sepenuhnya.
	 */
	const PREVIEW_CODEPOINT_BUDGET = 120;

	/**
	 * Jendela waktu untuk PREVIEW_CODEPOINT_BUDGET. Transient-nya kedaluwarsa
	 * sendiri, jadi kuota pengunjung pulih otomatis keesokan harinya.
	 */
	const PREVIEW_BUDGET_WINDOW = DAY_IN_SECONDS;

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
		if ( ! function_exists( 'aksara_marketplace_uses_authentype' ) || ! aksara_marketplace_uses_authentype() ) {
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
		}

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
				__( 'Too many preview requests. Please try again shortly.', 'aksara-marketplace' ),
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
			return new WP_Error( 'aksara_empty_text', __( 'Preview text cannot be empty.', 'aksara-marketplace' ), array( 'status' => 400 ) );
		}

		if ( function_exists( 'mb_strlen' ) ? mb_strlen( $text ) > 100 : strlen( $text ) > 100 ) {
			return new WP_Error( 'aksara_text_too_long', __( 'Preview text is limited to 100 characters.', 'aksara-marketplace' ), array( 'status' => 400 ) );
		}

		$style = Aksara_Font_Styles_Repository::get( $style_id );
		if ( ! $style ) {
			return new WP_Error( 'aksara_invalid_style', __( 'Style not found.', 'aksara-marketplace' ), array( 'status' => 404 ) );
		}

		// Hanya style milik produk font yang sudah publish yang boleh dipratinjaukan
		// — mencegah endpoint ini dipakai mengintip style produk draft/privat.
		if ( 'publish' !== get_post_status( $style->product_id ) ) {
			return new WP_Error( 'aksara_product_not_published', __( 'This product is not published.', 'aksara-marketplace' ), array( 'status' => 403 ) );
		}

		// Dicek PALING AKHIR, setelah style dipastikan valid: kuota
		// dihitung per style, jadi tidak masuk akal membebaninya untuk
		// request yang memang akan ditolak karena alasan lain.
		$budget = self::check_codepoint_budget( $style->id, $text );
		if ( is_wp_error( $budget ) ) {
			return $budget;
		}

		return array(
			'style' => $style,
			'text'  => $text,
		);
	}

	/**
	 * Pecah teks jadi daftar codepoint unik.
	 *
	 * Memakai preg_split('//u') dan menyimpan KARAKTERNYA, bukan nilai
	 * numerik lewat mb_ord(): hasilnya identik untuk keperluan di sini
	 * (satu potongan //u = satu codepoint) tapi tidak menambah
	 * ketergantungan pada ekstensi mbstring, yang tidak selalu ada di
	 * hosting murah.
	 *
	 * @param string $text Teks pratinjau.
	 * @return string[]
	 */
	private static function unique_codepoints( $text ) {
		$chars = preg_split( '//u', $text, -1, PREG_SPLIT_NO_EMPTY );
		if ( ! is_array( $chars ) ) {
			// preg_split mengembalikan false kalau teksnya bukan UTF-8 valid.
			// Perlakukan sebagai byte tunggal supaya kuota tetap terhitung
			// alih-alih diam-diam terlewat.
			$chars = str_split( (string) $text );
		}

		return array_values( array_unique( $chars ) );
	}

	/**
	 * Batasi berapa banyak codepoint BERBEDA yang boleh dikumpulkan satu
	 * klien untuk satu style. Lihat PREVIEW_CODEPOINT_BUDGET untuk angka &
	 * alasannya.
	 *
	 * Disimpan sebagai gabungan himpunan (union), bukan penghitung: mengetik
	 * ulang teks yang sama tidak menambah kuota sama sekali, sehingga
	 * pengunjung yang memperbaiki salah ketik atau menekan ulang tidak
	 * dihukum. Yang dihitung hanya karakter yang benar-benar BARU.
	 *
	 * Cache subset (transient 10 menit) sengaja TIDAK melewati pemeriksaan
	 * ini: cache itu global lintas pengunjung, jadi klien yang kena cache
	 * hit tetap baru pertama kali menerima glyph tersebut.
	 *
	 * @param int    $style_id ID style.
	 * @param string $text     Teks pratinjau yang sudah divalidasi.
	 * @return true|WP_Error
	 */
	private static function check_codepoint_budget( $style_id, $text ) {
		$budget = (int) apply_filters( 'aksara_preview_codepoint_budget', self::PREVIEW_CODEPOINT_BUDGET, $style_id );
		if ( $budget <= 0 ) {
			return true;
		}

		$key  = 'aksara_cpb_' . md5( self::get_client_ip() . '|' . $style_id );
		$seen = get_transient( $key );
		if ( ! is_array( $seen ) ) {
			$seen = array();
		}

		$union = array_values( array_unique( array_merge( $seen, self::unique_codepoints( $text ) ) ) );

		if ( count( $union ) > $budget ) {
			return new WP_Error(
				'aksara_preview_budget_reached',
				__( 'You have tried a lot of different characters for this style. Live preview is limited to protect the font file — try again tomorrow, or buy the style to use its full character set.', 'aksara-marketplace' ),
				array( 'status' => 429 )
			);
		}

		// Ditulis ulang setiap kali (bukan cuma saat bertambah) supaya
		// jendela waktunya bergulir mengikuti aktivitas terakhir.
		set_transient( $key, $union, self::PREVIEW_BUDGET_WINDOW );

		return true;
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
			return new WP_Error( 'aksara_empty_style_ids', __( 'style_ids cannot be empty.', 'aksara-marketplace' ), array( 'status' => 400 ) );
		}

		$results       = array();
		$budget_error  = null;
		$service_error = null;

		foreach ( $style_ids as $style_id ) {
			$validated = self::validate_preview_request( $style_id, $request->get_param( 'text' ) );
			if ( is_wp_error( $validated ) ) {
				// Kuota codepoint diingat, tidak sekadar dilewati. Style yang
				// tidak valid (draft, id salah) memang pantas dilewati diam-
				// diam, tapi kuota adalah keputusan yang HARUS sampai ke
				// pengunjung: kalau ikut dilewati, respons jadi 200 dengan
				// hasil kosong dan typing tool tidak menampilkan apa-apa —
				// terlihat seperti kerusakan tanpa penjelasan.
				if ( 'aksara_preview_budget_reached' === $validated->get_error_code() ) {
					$budget_error = $validated;
				}
				continue; // Lewati style yang tidak valid, jangan gagalkan seluruh batch.
			}

			$woff2 = Aksara_Preview_Service_Client::get_subset( $validated['style'], $validated['text'] );
			if ( is_wp_error( $woff2 ) ) {
				$service_error = $woff2;
				continue;
			}

			$results[ $style_id ] = 'data:font/woff2;base64,' . base64_encode( $woff2 ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		}

		// Hanya kalau TIDAK ADA satu pun style yang bisa dilayani. Selama
		// masih ada yang berhasil, batch tetap 200 — pengunjung melihat
		// pratinjau untuk style yang kuotanya masih ada, dan style yang
		// kehabisan cukup tidak ikut diperbarui.
		if ( empty( $results ) && $budget_error ) {
			return $budget_error;
		}
		if ( empty( $results ) && $service_error ) {
			return new WP_Error( 'aksara_preview_unavailable', __( 'The live font preview service is unavailable.', 'aksara-marketplace' ), array( 'status' => 503 ) );
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
		$style_id   = absint( $request->get_param( 'style_id' ) );
		$license_id = absint( $request->get_param( 'license_id' ) );
		$price      = (float) $request->get_param( 'price' );
		if ( ! Aksara_Font_Styles_Repository::get( $style_id ) || ! Aksara_Font_Licenses_Repository::get( $license_id ) ) {
			return new WP_Error( 'aksara_invalid_price_target', __( 'The selected style or license does not exist.', 'aksara-marketplace' ), array( 'status' => 404 ) );
		}
		if ( $price < 0 ) {
			return new WP_Error( 'aksara_invalid_price', __( 'Price cannot be negative.', 'aksara-marketplace' ), array( 'status' => 400 ) );
		}
		Aksara_Font_Licenses_Repository::set_style_price(
			$style_id,
			$license_id,
			$price
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
			return new WP_Error( 'aksara_cart_unavailable', __( 'The shopping cart is unavailable.', 'aksara-marketplace' ), array( 'status' => 500 ) );
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
			return new WP_Error( 'aksara_add_to_cart_failed', __( 'Could not add to cart.', 'aksara-marketplace' ), array( 'status' => 500 ) );
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
			wp_redirect( esc_url_raw( $result['url'] ), 302, 'Aksara Marketplace' ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- host was strictly allowlisted in Download Manager.
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
			return new WP_Error( 'aksara_invalid_order', __( 'Order not found.', 'aksara-marketplace' ), array( 'status' => 404 ) );
		}

		$is_owner = get_current_user_id() && (int) $order->get_customer_id() === get_current_user_id();
		if ( ! $is_owner && ! current_user_can( 'manage_woocommerce' ) ) {
			return new WP_Error( 'aksara_forbidden', __( 'You are not allowed to access this certificate.', 'aksara-marketplace' ), array( 'status' => 403 ) );
		}

		$certificate = Aksara_License_Certificates_Repository::get_by_order( $order_id );
		if ( ! $certificate ) {
			return new WP_Error( 'aksara_no_certificate', __( 'This order has no license certificate.', 'aksara-marketplace' ), array( 'status' => 404 ) );
		}

		$path = Aksara_File_Storage::get_absolute_path( $certificate->file_path );
		if ( ! file_exists( $path ) ) {
			return new WP_Error( 'aksara_missing_resource', __( 'Certificate file not found on the server.', 'aksara-marketplace' ), array( 'status' => 404 ) );
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
