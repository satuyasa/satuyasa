<?php
/**
 * Klien HTTP untuk memanggil font-preview-service (microservice Fase 0,
 * lihat services/font-preview-service/). Satu-satunya tempat di plugin
 * yang tahu cara bicara ke microservice — endpoint REST & cart handler
 * memanggil lewat class ini, bukan wp_remote_post() langsung.
 *
 * Keputusan arsitektur (Starter Brief): microservice jalan di server yang
 * sama, port internal. URL default mengasumsikan itu; bisa dioverride
 * lewat konstanta AKSARA_PREVIEW_SERVICE_URL di wp-config.php kalau
 * suatu saat dipindah ke server terpisah.
 *
 * @package Aksara_Marketplace
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Aksara_Preview_Service_Client.
 */
class Aksara_Preview_Service_Client {

	/**
	 * Cache hasil subset selama 10 menit — meniru masa berlaku "signed URL
	 * ~10 menit" di PRD Bagian 4.3, hanya saja di sini bentuknya cache
	 * server-side (bukan URL statis yang bisa dibagikan ulang) karena
	 * endpoint REST kita mengembalikan bytes langsung, tidak pernah menulis
	 * file ke direktori yang bisa diakses publik. Lihat catatan di
	 * class-rest-controller.php.
	 */
	const CACHE_TTL = 10 * MINUTE_IN_SECONDS;

	/**
	 * Ambil URL dasar microservice.
	 *
	 * @return string
	 */
	public static function get_base_url() {
		if ( defined( 'AKSARA_PREVIEW_SERVICE_URL' ) ) {
			return untrailingslashit( AKSARA_PREVIEW_SERVICE_URL );
		}
		return 'http://127.0.0.1:5055';
	}

	/**
	 * Minta subset woff2 untuk satu style + teks, dengan cache lokal.
	 *
	 * @param object $style Baris dari aksara_font_styles (butuh ->file_path).
	 * @param string $text  Teks pratinjau (sudah divalidasi panjangnya oleh pemanggil).
	 * @return string|WP_Error Isi biner woff2, atau WP_Error.
	 */
	public static function get_subset( $style, $text ) {
		$cache_key = 'aksara_preview_' . md5( $style->id . '|' . $text );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return base64_decode( $cached ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- transients only accept scalar/serializable strings, not raw binary reliably.
		}

		$response = wp_remote_post(
			self::get_base_url() . '/v1/subset',
			array(
				'timeout' => 5,
				'body'    => array(
					'font_path' => $style->file_path,
					'text'      => $text,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return new WP_Error(
				'aksara_preview_service_error',
				sprintf(
					/* translators: %d: kode HTTP dari microservice. */
					__( 'The font preview service returned status %d.', 'aksara-marketplace' ),
					$code
				)
			);
		}

		$body = wp_remote_retrieve_body( $response );
		set_transient( $cache_key, base64_encode( $body ), self::CACHE_TTL ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode

		return $body;
	}

	/**
	 * Cek apakah microservice hidup (dipakai halaman status admin bila perlu).
	 *
	 * @return bool
	 */
	public static function is_healthy() {
		$response = wp_remote_get( self::get_base_url() . '/health', array( 'timeout' => 2 ) );
		return ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response );
	}
}
