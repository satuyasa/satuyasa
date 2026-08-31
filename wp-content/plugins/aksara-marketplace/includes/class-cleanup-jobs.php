<?php
/**
 * Cron pembersihan cache pratinjau font (aksara_cleanup_preview_cache).
 *
 * Transient WordPress kedaluwarsa "secara malas" (baris di wp_options baru
 * benar-benar dihapus saat ada yang mencoba membacanya lagi setelah lewat
 * waktu). Untuk cache pratinjau yang jumlahnya bisa banyak (tiap kombinasi
 * style+teks unik dapat baris sendiri) dan sebagian besar TIDAK PERNAH
 * dibaca ulang, itu berarti baris kedaluwarsa bisa menumpuk di wp_options
 * tanpa batas. Cron harian ini membersihkannya secara proaktif.
 *
 * @package Aksara_Marketplace
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Aksara_Cleanup_Jobs.
 */
class Aksara_Cleanup_Jobs {

	const HOOK = 'aksara_cleanup_preview_cache';

	/**
	 * Pasang hook & jadwalkan cron kalau belum terjadwal.
	 */
	public static function init() {
		add_action( self::HOOK, array( __CLASS__, 'run' ) );

		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time(), 'daily', self::HOOK );
		}
	}

	/**
	 * Batalkan jadwal cron (dipanggil saat deaktivasi plugin).
	 */
	public static function unschedule() {
		$timestamp = wp_next_scheduled( self::HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::HOOK );
		}
	}

	/**
	 * Hapus baris transient _transient_aksara_preview_* & pasangan timeout-nya
	 * yang sudah lewat masa berlaku, langsung lewat query — bukan lewat
	 * get_transient() satu-satu (yang membaca ribuan baris hanya untuk
	 * memicu penghapusan lazy WordPress, boros untuk cron).
	 */
	public static function run() {
		global $wpdb;

		$now = time();

		$expired_timeout_keys = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options}
				 WHERE option_name LIKE %s AND option_value < %d",
				$wpdb->esc_like( '_transient_timeout_aksara_preview_' ) . '%',
				$now
			)
		);

		if ( empty( $expired_timeout_keys ) ) {
			return;
		}

		foreach ( $expired_timeout_keys as $timeout_key ) {
			$transient_key = str_replace( '_transient_timeout_', '', $timeout_key );
			delete_transient( $transient_key );
		}
	}
}
