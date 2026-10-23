<?php
/**
 * Akses data untuk tabel aksara_license_certificates.
 *
 * @package Aksara_Marketplace
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Aksara_License_Certificates_Repository.
 */
class Aksara_License_Certificates_Repository {

	/**
	 * Simpan (insert/replace) path sertifikat untuk satu order. Satu order =
	 * satu sertifikat (lihat class-invoice-generator.php).
	 *
	 * @param int    $order_id  ID order.
	 * @param string $file_path Path relatif terhadap direktori privat.
	 * @return int ID baris.
	 */
	public static function save( $order_id, $file_path ) {
		global $wpdb;
		$table = $wpdb->prefix . 'aksara_license_certificates';

		$existing = self::get_by_order( $order_id );
		if ( $existing ) {
			$wpdb->update( $table, array( 'file_path' => $file_path ), array( 'id' => $existing->id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			return (int) $existing->id;
		}

		$wpdb->insert( $table, array( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			'order_id'  => (int) $order_id,
			'file_path' => $file_path,
		) );

		return (int) $wpdb->insert_id;
	}

	/**
	 * Ambil sertifikat milik satu order.
	 *
	 * @param int $order_id ID order.
	 * @return object|null
	 */
	public static function get_by_order( $order_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'aksara_license_certificates';
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE order_id = %d", $order_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Ambil semua sertifikat milik order-order tertentu (dipakai My Account,
	 * digabung dengan daftar order milik user yang sedang login).
	 *
	 * @param array $order_ids Daftar ID order.
	 * @return array
	 */
	public static function get_by_orders( $order_ids ) {
		global $wpdb;

		if ( empty( $order_ids ) ) {
			return array();
		}

		$table        = $wpdb->prefix . 'aksara_license_certificates';
		$placeholders = implode( ',', array_fill( 0, count( $order_ids ), '%d' ) );

		return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "SELECT * FROM {$table} WHERE order_id IN ({$placeholders}) ORDER BY id DESC", $order_ids ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);
	}
}
