<?php
/**
 * Akses data untuk tabel aksara_wishlist_items.
 *
 * @package Aksara_Marketplace
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Aksara_Wishlist_Repository.
 */
class Aksara_Wishlist_Repository {

	/**
	 * Cek apakah produk ada di wishlist user.
	 *
	 * @param int $user_id    ID user.
	 * @param int $product_id ID produk.
	 * @return bool
	 */
	public static function has( $user_id, $product_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'aksara_wishlist_items';

		return (bool) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "SELECT id FROM {$table} WHERE user_id = %d AND product_id = %d", $user_id, $product_id )
		);
	}

	/**
	 * Tambah/hapus produk dari wishlist (toggle).
	 *
	 * @param int $user_id    ID user.
	 * @param int $product_id ID produk.
	 * @return bool true kalau sekarang ADA di wishlist, false kalau sekarang TIDAK ADA.
	 */
	public static function toggle( $user_id, $product_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'aksara_wishlist_items';

		if ( self::has( $user_id, $product_id ) ) {
			$wpdb->delete( $table, array( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				'user_id'    => (int) $user_id,
				'product_id' => (int) $product_id,
			) );
			return false;
		}

		$wpdb->insert( $table, array( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			'user_id'    => (int) $user_id,
			'product_id' => (int) $product_id,
		) );
		return true;
	}

	/**
	 * Ambil daftar ID produk di wishlist seorang user.
	 *
	 * @param int $user_id ID user.
	 * @return int[]
	 */
	public static function get_product_ids( $user_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'aksara_wishlist_items';

		$ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "SELECT product_id FROM {$table} WHERE user_id = %d ORDER BY id DESC", $user_id )
		);

		return array_map( 'intval', $ids );
	}
}
