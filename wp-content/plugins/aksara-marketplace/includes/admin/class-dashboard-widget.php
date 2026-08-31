<?php
/**
 * Widget WP Dashboard: "Font Terlaris" 30 hari terakhir.
 *
 * Dibangun manual lewat wc_get_orders() + loop item (bukan query SQL
 * langsung ke tabel order) supaya tetap benar baik di setup HPOS
 * (custom order tables) maupun penyimpanan order lama berbasis post —
 * WooCommerce Analytics bawaan sudah menangani laporan umum, widget ini
 * cuma melengkapi dengan sudut pandang "per product_type = font" yang
 * tidak dipisahkan WooCommerce Analytics secara default.
 *
 * @package Aksara_Marketplace
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Aksara_Dashboard_Widget.
 */
class Aksara_Dashboard_Widget {

	const CACHE_KEY = 'aksara_bestselling_fonts';
	const CACHE_TTL = 12 * HOUR_IN_SECONDS;

	/**
	 * Pasang hook.
	 */
	public static function init() {
		add_action( 'wp_dashboard_setup', array( __CLASS__, 'register_widget' ) );
	}

	/**
	 * Daftarkan widget (hanya untuk yang punya akses kelola WooCommerce).
	 */
	public static function register_widget() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'aksara_bestselling_fonts',
			__( 'Aksara: Best-Selling Fonts (30 Days)', 'aksara-marketplace' ),
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Cetak isi widget.
	 */
	public static function render() {
		$rows = self::get_bestsellers();

		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'No font sales in the last 30 days.', 'aksara-marketplace' ) . '</p>';
			return;
		}

		echo '<table class="widefat striped"><thead><tr><th>' .
			esc_html__( 'Font', 'aksara-marketplace' ) . '</th><th>' .
			esc_html__( 'Sold', 'aksara-marketplace' ) . '</th></tr></thead><tbody>';

		foreach ( $rows as $row ) {
			printf(
				'<tr><td><a href="%1$s">%2$s</a></td><td>%3$d</td></tr>',
				esc_url( get_edit_post_link( $row['product_id'], '' ) ),
				esc_html( $row['name'] ),
				(int) $row['count']
			);
		}

		echo '</tbody></table>';
	}

	/**
	 * Hitung & cache produk font terlaris 30 hari terakhir.
	 *
	 * @return array[] {product_id, name, count}
	 */
	private static function get_bestsellers() {
		$cached = get_transient( self::CACHE_KEY );
		if ( false !== $cached ) {
			return $cached;
		}

		$orders = wc_get_orders( array(
			'status'       => array( 'completed', 'processing' ),
			'date_created' => '>' . ( time() - 30 * DAY_IN_SECONDS ),
			'limit'        => -1,
			'return'       => 'objects',
		) );

		$counts = array();

		foreach ( $orders as $order ) {
			foreach ( $order->get_items() as $item ) {
				if ( ! $item->get_meta( '_aksara_style_ids' ) ) {
					continue; // Bukan line item font.
				}

				$product_id = $item->get_product_id();
				if ( ! isset( $counts[ $product_id ] ) ) {
					$counts[ $product_id ] = array(
						'product_id' => $product_id,
						'name'       => $item->get_name(),
						'count'      => 0,
					);
				}
				$counts[ $product_id ]['count'] += $item->get_quantity();
			}
		}

		usort( $counts, function ( $a, $b ) {
			return $b['count'] <=> $a['count'];
		} );

		$top = array_slice( array_values( $counts ), 0, 5 );

		set_transient( self::CACHE_KEY, $top, self::CACHE_TTL );

		return $top;
	}
}
