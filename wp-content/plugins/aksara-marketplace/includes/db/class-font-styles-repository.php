<?php
/**
 * Akses data untuk tabel aksara_font_styles.
 *
 * @package Aksara_Marketplace
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Aksara_Font_Styles_Repository.
 */
class Aksara_Font_Styles_Repository {

	/**
	 * Ambil semua style milik satu produk font, urut sort_order.
	 *
	 * @param int $product_id ID produk.
	 * @return array
	 */
	public static function get_by_product( $product_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'aksara_font_styles';

		return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE product_id = %d ORDER BY sort_order ASC, id ASC",
				$product_id
			)
		);
	}

	/**
	 * Ambil satu style berdasarkan ID.
	 *
	 * @param int $id ID style.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'aksara_font_styles';
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Tambahkan style baru.
	 *
	 * @param array $data product_id, style_name, font_weight, is_italic, file_path, sort_order.
	 * @return int|false ID style baru, atau false jika gagal.
	 */
	public static function insert( $data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'aksara_font_styles';

		$inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			array(
				'product_id'  => (int) $data['product_id'],
				'style_name'  => sanitize_text_field( $data['style_name'] ),
				'font_weight' => (int) ( $data['font_weight'] ?? 400 ),
				'is_italic'   => empty( $data['is_italic'] ) ? 0 : 1,
				'file_path'   => sanitize_text_field( $data['file_path'] ),
				'sort_order'  => (int) ( $data['sort_order'] ?? 0 ),
			)
		);

		return $inserted ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Perbarui metadata style (nama, weight, italic, urutan) — bukan file-nya.
	 *
	 * @param int   $id   ID style.
	 * @param array $data style_name, font_weight, is_italic, sort_order.
	 */
	public static function update_meta( $id, $data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'aksara_font_styles';

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			array(
				'style_name'  => sanitize_text_field( $data['style_name'] ),
				'font_weight' => (int) ( $data['font_weight'] ?? 400 ),
				'is_italic'   => empty( $data['is_italic'] ) ? 0 : 1,
				'sort_order'  => (int) ( $data['sort_order'] ?? 0 ),
			),
			array( 'id' => (int) $id )
		);
	}

	/**
	 * Hapus style beserta harga terkait (file fisik ditangani pemanggil).
	 *
	 * @param int $id ID style.
	 */
	public static function delete( $id ) {
		global $wpdb;
		$id = (int) $id;

		$wpdb->delete( $wpdb->prefix . 'aksara_style_prices', array( 'style_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $wpdb->prefix . 'aksara_font_styles', array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Ambil style yang paling mewakili sebuah font family untuk ditampilkan
	 * sebagai specimen: prioritas Regular tegak (weight 400, non-italic),
	 * lalu weight terdekat ke 400, baru style pertama apa pun.
	 *
	 * @param int $product_id ID produk font.
	 * @return object|null
	 */
	public static function get_representative( $product_id ) {
		$styles = self::get_by_product( $product_id );
		if ( empty( $styles ) ) {
			return null;
		}

		$upright = array_values( array_filter( $styles, function ( $style ) {
			return empty( $style->is_italic );
		} ) );

		$candidates = ! empty( $upright ) ? $upright : $styles;

		usort( $candidates, function ( $a, $b ) {
			return abs( (int) $a->font_weight - 400 ) <=> abs( (int) $b->font_weight - 400 );
		} );

		return $candidates[0];
	}

	/**
	 * Hitung jumlah style pada sebuah produk.
	 *
	 * @param int $product_id ID produk.
	 * @return int
	 */
	public static function count_by_product( $product_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'aksara_font_styles';

		return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE product_id = %d", $product_id )
		);
	}
}
