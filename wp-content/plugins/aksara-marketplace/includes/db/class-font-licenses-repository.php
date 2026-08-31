<?php
/**
 * Akses data untuk tabel aksara_font_licenses & aksara_style_prices.
 *
 * @package Aksara_Marketplace
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Aksara_Font_Licenses_Repository.
 */
class Aksara_Font_Licenses_Repository {

	/**
	 * Ambil semua jenis lisensi, urut sesuai sort_order.
	 *
	 * @return array
	 */
	public static function get_all() {
		global $wpdb;
		$table = $wpdb->prefix . 'aksara_font_licenses';
		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY sort_order ASC, id ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Ambil satu lisensi berdasarkan ID.
	 *
	 * @param int $id ID lisensi.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'aksara_font_licenses';
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Simpan (insert/update) lisensi.
	 *
	 * @param array    $data Data lisensi (name, slug, description, sort_order).
	 * @param int|null $id   ID untuk update, null untuk insert baru.
	 * @return int|false ID lisensi atau false jika gagal.
	 */
	public static function save( $data, $id = null ) {
		global $wpdb;
		$table = $wpdb->prefix . 'aksara_font_licenses';

		$payload = array(
			'name'        => sanitize_text_field( $data['name'] ),
			'slug'        => sanitize_title( $data['slug'] ?? $data['name'] ),
			'description' => wp_kses_post( $data['description'] ?? '' ),
			'sort_order'  => isset( $data['sort_order'] ) ? (int) $data['sort_order'] : 0,
		);

		if ( $id ) {
			$updated = $wpdb->update( $table, $payload, array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			return false === $updated ? false : (int) $id;
		}

		$inserted = $wpdb->insert( $table, $payload ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $inserted ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Hapus lisensi beserta harga style yang terkait.
	 *
	 * @param int $id ID lisensi.
	 */
	public static function delete( $id ) {
		global $wpdb;
		$id = (int) $id;

		$wpdb->delete( $wpdb->prefix . 'aksara_style_prices', array( 'license_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $wpdb->prefix . 'aksara_font_licenses', array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Ambil peta harga (style_id => [license_id => price]) untuk sebuah produk.
	 *
	 * @param int $product_id ID produk font.
	 * @return array
	 */
	public static function get_price_matrix_for_product( $product_id ) {
		global $wpdb;
		$prices_table = $wpdb->prefix . 'aksara_style_prices';
		$styles_table = $wpdb->prefix . 'aksara_font_styles';

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT p.style_id, p.license_id, p.price
				 FROM {$prices_table} p
				 INNER JOIN {$styles_table} s ON s.id = p.style_id
				 WHERE s.product_id = %d",
				$product_id
			)
		);

		$matrix = array();
		foreach ( $rows as $row ) {
			$matrix[ (int) $row->style_id ][ (int) $row->license_id ] = (float) $row->price;
		}
		return $matrix;
	}

	/**
	 * Ambil harga terendah dari seluruh kombinasi style x lisensi milik produk.
	 * Dipakai untuk menampilkan "mulai dari Rp X" pada listing/single product.
	 *
	 * @param int $product_id ID produk font.
	 * @return float|null
	 */
	public static function get_min_price_for_product( $product_id ) {
		global $wpdb;
		$prices_table = $wpdb->prefix . 'aksara_style_prices';
		$styles_table = $wpdb->prefix . 'aksara_font_styles';

		$min = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT MIN(p.price)
				 FROM {$prices_table} p
				 INNER JOIN {$styles_table} s ON s.id = p.style_id
				 WHERE s.product_id = %d",
				$product_id
			)
		);

		return null === $min ? null : (float) $min;
	}

	/**
	 * Simpan harga satu kombinasi style x lisensi.
	 *
	 * @param int   $style_id   ID style.
	 * @param int   $license_id ID lisensi.
	 * @param float $price      Harga.
	 */
	public static function set_style_price( $style_id, $license_id, $price ) {
		global $wpdb;
		$table = $wpdb->prefix . 'aksara_style_prices';

		$existing_id = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE style_id = %d AND license_id = %d",
				$style_id,
				$license_id
			)
		);

		$payload = array(
			'style_id'   => (int) $style_id,
			'license_id' => (int) $license_id,
			'price'      => (float) $price,
		);

		if ( $existing_id ) {
			$wpdb->update( $table, array( 'price' => $payload['price'] ), array( 'id' => (int) $existing_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		} else {
			$wpdb->insert( $table, $payload ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
	}

	/**
	 * Ambil harga satu kombinasi style x lisensi, atau null jika belum diatur.
	 *
	 * @param int $style_id   ID style.
	 * @param int $license_id ID lisensi.
	 * @return float|null
	 */
	public static function get_style_price( $style_id, $license_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'aksara_style_prices';

		$price = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT price FROM {$table} WHERE style_id = %d AND license_id = %d",
				$style_id,
				$license_id
			)
		);

		return null === $price ? null : (float) $price;
	}
}
