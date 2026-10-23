<?php
/**
 * Cron pembersihan: cache pratinjau font (aksara_cleanup_preview_cache) &
 * token unduh kedaluwarsa (aksara_cleanup_download_tokens).
 *
 * Transient WordPress kedaluwarsa "secara malas" (baris di wp_options baru
 * benar-benar dihapus saat ada yang mencoba membacanya lagi setelah lewat
 * waktu). Untuk cache pratinjau yang jumlahnya bisa banyak (tiap kombinasi
 * style+teks unik dapat baris sendiri) dan sebagian besar TIDAK PERNAH
 * dibaca ulang, itu berarti baris kedaluwarsa bisa menumpuk di wp_options
 * tanpa batas. Cron harian ini membersihkannya secara proaktif.
 *
 * Token unduh (aksara_download_tokens) sendiri TIDAK diberi expires_at
 * secara default (lihat Aksara_Download_Manager — lisensi yang sudah
 * dibeli tidak semestinya berhenti bisa diunduh), jadi cron kedua ini
 * biasanya no-op hari ini — tapi tetap disiapkan sesuai Breakdown Task
 * Fase 3, siap dipakai begitu ada mekanisme yang memberi expiry (mis.
 * kebijakan retensi khusus di masa depan) tanpa perlu kode baru.
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

	const HOOK_PREVIEW_CACHE   = 'aksara_cleanup_preview_cache';
	const HOOK_DOWNLOAD_TOKENS = 'aksara_cleanup_download_tokens';

	/**
	 * Pasang hook & jadwalkan cron kalau belum terjadwal.
	 */
	public static function init() {
		add_action( self::HOOK_PREVIEW_CACHE, array( __CLASS__, 'run_preview_cache_cleanup' ) );
		add_action( self::HOOK_DOWNLOAD_TOKENS, array( __CLASS__, 'run_download_tokens_cleanup' ) );

		if ( ! wp_next_scheduled( self::HOOK_PREVIEW_CACHE ) ) {
			wp_schedule_event( time(), 'daily', self::HOOK_PREVIEW_CACHE );
		}
		if ( ! wp_next_scheduled( self::HOOK_DOWNLOAD_TOKENS ) ) {
			wp_schedule_event( time(), 'daily', self::HOOK_DOWNLOAD_TOKENS );
		}
	}

	/**
	 * Batalkan jadwal cron (dipanggil saat deaktivasi plugin).
	 */
	public static function unschedule() {
		foreach ( array( self::HOOK_PREVIEW_CACHE, self::HOOK_DOWNLOAD_TOKENS ) as $hook ) {
			$timestamp = wp_next_scheduled( $hook );
			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, $hook );
			}
		}
	}

	/**
	 * Hapus baris transient _transient_aksara_preview_* & pasangan timeout-nya
	 * yang sudah lewat masa berlaku, langsung lewat query — bukan lewat
	 * get_transient() satu-satu (yang membaca ribuan baris hanya untuk
	 * memicu penghapusan lazy WordPress, boros untuk cron).
	 */
	public static function run_preview_cache_cleanup() {
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

	/**
	 * Hapus baris aksara_download_tokens yang expires_at-nya sudah lewat.
	 */
	public static function run_download_tokens_cleanup() {
		if ( class_exists( 'Aksara_Download_Tokens_Repository' ) ) {
			Aksara_Download_Tokens_Repository::delete_expired();
		}
	}
}
