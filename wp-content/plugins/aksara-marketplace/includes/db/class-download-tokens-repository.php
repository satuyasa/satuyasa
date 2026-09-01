<?php
/**
 * Akses data untuk tabel aksara_download_tokens.
 *
 * @package Aksara_Marketplace
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Aksara_Download_Tokens_Repository.
 */
class Aksara_Download_Tokens_Repository {

	const RESOURCE_FONT_STYLE = 'font_style';
	const RESOURCE_CANVA      = 'canva_asset';

	/**
	 * Buat token baru. Token itu sendiri adalah kredensial pembawa (bearer) —
	 * pola yang sama dipakai sistem download permission bawaan WooCommerce —
	 * jadi harus acak & tidak bisa ditebak.
	 *
	 * @param array $data order_id, order_item_id, user_id, resource_type, resource_id.
	 * @return string|false Token yang baru dibuat, false jika gagal.
	 */
	public static function create( $data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'aksara_download_tokens';

		$token = bin2hex( random_bytes( 24 ) );

		$inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			array(
				'token'          => $token,
				'order_id'       => (int) $data['order_id'],
				'order_item_id'  => (int) $data['order_item_id'],
				'user_id'        => (int) ( $data['user_id'] ?? 0 ),
				'resource_type'  => sanitize_key( $data['resource_type'] ),
				'resource_id'    => (int) $data['resource_id'],
				'download_limit' => (int) ( $data['download_limit'] ?? 50 ),
			)
		);

		return false === $inserted ? false : $token;
	}

	public static function find_for_resource( $order_item_id, $resource_type, $resource_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'aksara_download_tokens';
		return $wpdb->get_var( $wpdb->prepare( "SELECT token FROM {$table} WHERE order_item_id = %d AND resource_type = %s AND resource_id = %d LIMIT 1", $order_item_id, $resource_type, $resource_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Ambil satu token.
	 *
	 * @param string $token Token.
	 * @return object|null
	 */
	public static function get( $token ) {
		global $wpdb;
		$table = $wpdb->prefix . 'aksara_download_tokens';
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE token = %s", $token ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Ambil seluruh token milik satu order (dipakai My Account > Downloads).
	 *
	 * @param int $order_id ID order.
	 * @return array
	 */
	public static function get_by_order( $order_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'aksara_download_tokens';
		return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "SELECT * FROM {$table} WHERE order_id = %d AND is_revoked = 0 ORDER BY id ASC", $order_id )
		);
	}

	/**
	 * Ambil seluruh token milik satu user lewat semua order-nya.
	 *
	 * @param int $user_id ID user.
	 * @return array
	 */
	public static function get_by_user( $user_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'aksara_download_tokens';
		return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d AND is_revoked = 0 ORDER BY id DESC", $user_id )
		);
	}

	/**
	 * Tambah hitungan unduhan.
	 *
	 * @param int $id ID baris token.
	 */
	public static function increment_download_count( $id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'aksara_download_tokens';
		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET download_count = download_count + 1 WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
	}

	/** Atomically reserve one download slot. */
	public static function claim_download( $id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'aksara_download_tokens';
		return 1 === (int) $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET download_count = download_count + 1 WHERE id = %d AND is_revoked = 0 AND download_count < download_limit", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Cabut (revoke) seluruh token milik satu order — dipakai saat order
	 * di-refund/dibatalkan.
	 *
	 * @param int $order_id ID order.
	 */
	public static function revoke_by_order( $order_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'aksara_download_tokens';
		$wpdb->update( $table, array( 'is_revoked' => 1 ), array( 'order_id' => (int) $order_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Hapus token yang sudah lewat expires_at (dipakai cron pembersihan).
	 *
	 * @return int Jumlah baris yang dihapus.
	 */
	public static function delete_expired() {
		global $wpdb;
		$table = $wpdb->prefix . 'aksara_download_tokens';
		return (int) $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"DELETE FROM {$table} WHERE expires_at IS NOT NULL AND expires_at < NOW()"
		);
	}
}
