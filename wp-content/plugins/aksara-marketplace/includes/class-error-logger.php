<?php
/**
 * Logging ringan untuk endpoint kritis (preview, unduhan, checkout) —
 * tanpa dependency eksternal (bukan Sentry PHP SDK, konsisten dengan
 * gaya plugin ini yang murni PHP tanpa Composer/vendor).
 *
 * Dua hal terjadi tiap kali sesuatu di-log:
 * 1. Ditulis ke error_log() PHP — otomatis masuk ke debug.log kalau
 *    WP_DEBUG_LOG aktif, tanpa perlu konfigurasi tambahan.
 * 2. Action hook `aksara_error` di-fire — di sinilah integrasi Sentry
 *    (atau layanan monitoring lain) dipasang. Contoh di wp-config.php
 *    atau mu-plugin terpisah (BUKAN di plugin ini, supaya plugin ini
 *    tetap tidak membundel SDK apa pun):
 *
 *     add_action( 'aksara_error', function( $context, $message, $data, $severity ) {
 *         if ( function_exists( '\Sentry\captureMessage' ) ) {
 *             \Sentry\captureMessage( "[$context] $message", $severity );
 *         }
 *     }, 10, 4 );
 *
 * @package Aksara_Marketplace
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Aksara_Error_Logger.
 */
class Aksara_Error_Logger {

	/**
	 * Pasang hook.
	 */
	public static function init() {
		// Tangkap semua WP_Error yang dikembalikan endpoint aksara/v1 di satu
		// tempat — daripada menyisipkan pemanggilan log() di tiap handler
		// REST controller satu-satu (rawan ada yang lupa ditambahkan).
		add_filter( 'rest_request_after_callbacks', array( __CLASS__, 'log_rest_errors' ), 10, 3 );

		add_action( 'woocommerce_order_status_failed', array( __CLASS__, 'log_failed_order' ) );
	}

	/**
	 * Log setiap WP_Error dari endpoint aksara/v1 (preview, unduhan, cart, dst.).
	 *
	 * @param mixed           $response Hasil dari callback endpoint.
	 * @param array           $handler  Info handler (tidak dipakai).
	 * @param WP_REST_Request $request  Request REST.
	 * @return mixed
	 */
	public static function log_rest_errors( $response, $handler, $request ) {
		if ( ! is_wp_error( $response ) || 0 !== strpos( $request->get_route(), '/aksara/v1/' ) ) {
			return $response;
		}

		$error_data = $response->get_error_data();
		$status     = is_array( $error_data ) && isset( $error_data['status'] ) ? (int) $error_data['status'] : 0;

		self::log(
			'rest:' . self::redact_route( $request->get_route() ),
			$response->get_error_message(),
			array(
				'code'   => $response->get_error_code(),
				'status' => $status,
				'method' => $request->get_method(),
			),
			self::severity_for_status( $status )
		);

		return $response;
	}

	/**
	 * Buang token bearer dari rute sebelum dicatat.
	 *
	 * Rute unduhan berbentuk /aksara/v1/download/<48 hex>, dan token itu
	 * ADALAH kredensialnya — siapa pun yang memegangnya bisa mengunduh
	 * berkasnya. Mencatatnya apa adanya berarti menulis kredensial hidup ke
	 * debug.log (yang di sebagian server bisa dibaca lewat web) dan
	 * mengirimkannya ke layanan monitoring lewat hook `aksara_error`.
	 *
	 * Ini bukan soal token yang sudah mati saja: error seperti
	 * `aksara_missing_resource` justru terjadi pada token yang masih
	 * berlaku, belum kedaluwarsa, dan kuotanya belum habis.
	 *
	 * @param string $route Rute REST asli.
	 * @return string
	 */
	private static function redact_route( $route ) {
		return preg_replace( '#(/download/)[a-f0-9]{16,}#i', '$1[redacted]', (string) $route );
	}

	/**
	 * Log saat order jatuh ke status "failed" — sinyal penting untuk
	 * masalah payment gateway (PayPal) yang butuh perhatian.
	 *
	 * @param int $order_id ID order.
	 */
	public static function log_failed_order( $order_id ) {
		$order = wc_get_order( $order_id );

		self::log(
			'checkout:order_failed',
			sprintf( 'Order #%d berstatus failed', $order_id ),
			array(
				'order_id'       => $order_id,
				'payment_method' => $order ? $order->get_payment_method() : null,
				'total'          => $order ? $order->get_total() : null,
			),
			'error'
		);
	}

	/**
	 * Tentukan tingkat keparahan dari kode status HTTP — supaya konsumen
	 * `aksara_error` (mis. Sentry) bisa memilah mana yang butuh perhatian
	 * segera (rate limit/validasi input yang salah itu wajar terjadi terus,
	 * beda dengan error server).
	 *
	 * @param int $status Kode status HTTP.
	 * @return string 'error'|'warning'|'notice'
	 */
	private static function severity_for_status( $status ) {
		if ( $status >= 500 || 0 === $status ) {
			return 'error';
		}
		if ( in_array( $status, array( 401, 403, 429 ), true ) ) {
			return 'warning';
		}
		return 'notice';
	}

	/**
	 * Catat satu peristiwa.
	 *
	 * @param string $context  Identitas ringkas sumber peristiwa (mis. 'rest:/aksara/v1/download/...').
	 * @param string $message  Pesan manusiawi.
	 * @param array  $data     Data tambahan (tidak boleh berisi data sensitif seperti nomor kartu/token mentah).
	 * @param string $severity 'error'|'warning'|'notice'.
	 */
	public static function log( $context, $message, $data = array(), $severity = 'error' ) {
		$entry = array(
			'time'     => current_time( 'mysql' ),
			'severity' => $severity,
			'context'  => $context,
			'message'  => $message,
			'data'     => $data,
		);

		error_log( '[aksara] ' . wp_json_encode( $entry ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- satu-satunya sink logging bawaan tanpa dependency; lihat docblock file ini untuk cara menyalurkannya ke Sentry/dst.

		/**
		 * Titik ekstensi untuk memasang integrasi monitoring eksternal
		 * (Sentry, dsb.) tanpa plugin ini membundel SDK apa pun.
		 *
		 * @param string $context  Identitas sumber peristiwa.
		 * @param string $message  Pesan.
		 * @param array  $data     Data tambahan.
		 * @param string $severity 'error'|'warning'|'notice'.
		 */
		do_action( 'aksara_error', $context, $message, $data, $severity );
	}
}
