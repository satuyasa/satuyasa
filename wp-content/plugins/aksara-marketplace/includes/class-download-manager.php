<?php
/**
 * Generate & validasi token unduhan aman setelah order selesai dibayar.
 *
 * Satu token = izin mengakses SATU resource (satu style font, atau satu
 * produk Canva). Token adalah kredensial pembawa acak (bearer), sama
 * seperti pola `download_permissions` bawaan WooCommerce sendiri — siapa
 * pun yang memegang token bisa memakainya, jadi token hanya dikirim lewat
 * email pembeli & ditampilkan di My Account milik pembeli yang sedang login.
 *
 * @package Aksara_Marketplace
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Aksara_Download_Manager.
 */
class Aksara_Download_Manager {

	/**
	 * Pasang hook.
	 */
	public static function init() {
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'generate_tokens_for_order' ) );
		// Beberapa toko menganggap "processing" (mis. pembayaran instan tanpa
		// perlu fulfillment fisik) sudah cukup untuk produk digital.
		add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'generate_tokens_for_order' ) );

		add_action( 'woocommerce_order_status_refunded', array( __CLASS__, 'revoke_tokens_for_order' ) );
		add_action( 'woocommerce_order_status_cancelled', array( __CLASS__, 'revoke_tokens_for_order' ) );
	}

	/**
	 * Buat token untuk setiap item yang bisa diunduh di sebuah order —
	 * idempotent (aman dipanggil ulang; tidak akan menduplikasi token untuk
	 * item yang sudah pernah diproses).
	 *
	 * @param int $order_id ID order.
	 */
	public static function generate_tokens_for_order( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		// Idempotency guard: jangan generate ulang kalau order ini sudah pernah diproses
		// (mis. status completed dipicu lagi lewat perubahan manual admin).
		if ( 'yes' === $order->get_meta( '_aksara_tokens_generated' ) ) {
			return;
		}

		$user_id = $order->get_customer_id();

		foreach ( $order->get_items() as $item_id => $item ) {
			$style_ids_raw = $item->get_meta( '_aksara_style_ids' );

			if ( $style_ids_raw ) {
				foreach ( array_filter( explode( ',', $style_ids_raw ) ) as $style_id ) {
					Aksara_Download_Tokens_Repository::create( array(
						'order_id'      => $order_id,
						'order_item_id' => $item_id,
						'user_id'       => $user_id,
						'resource_type' => Aksara_Download_Tokens_Repository::RESOURCE_FONT_STYLE,
						'resource_id'   => (int) $style_id,
					) );
				}
				continue;
			}

			$product = $item->get_product();
			if ( $product && in_array( $product->get_type(), array( 'canva_template', 'canva_element' ), true ) ) {
				Aksara_Download_Tokens_Repository::create( array(
					'order_id'      => $order_id,
					'order_item_id' => $item_id,
					'user_id'       => $user_id,
					'resource_type' => Aksara_Download_Tokens_Repository::RESOURCE_CANVA,
					'resource_id'   => $product->get_id(),
				) );
			}
		}

		$order->update_meta_data( '_aksara_tokens_generated', 'yes' );
		$order->save();

		if ( class_exists( 'Aksara_Invoice_Generator' ) ) {
			Aksara_Invoice_Generator::maybe_generate_for_order( $order );
		}

		/**
		 * Dipicu setelah token unduhan sebuah order selesai dibuat — dipakai
		 * class-order-emails.php untuk menyisipkan tautan unduh ke email.
		 *
		 * @param WC_Order $order Order terkait.
		 */
		do_action( 'aksara_download_tokens_ready', $order );
	}

	/**
	 * Cabut seluruh token milik order (refund/cancel).
	 *
	 * @param int $order_id ID order.
	 */
	public static function revoke_tokens_for_order( $order_id ) {
		Aksara_Download_Tokens_Repository::revoke_by_order( $order_id );
	}

	/**
	 * URL unduh publik untuk sebuah token.
	 *
	 * @param string $token Token.
	 * @return string
	 */
	public static function get_download_url( $token ) {
		return rest_url( 'aksara/v1/download/' . $token );
	}

	/**
	 * Validasi token & siapkan aksinya. TIDAK langsung stream/redirect di
	 * sini — mengembalikan instruksi supaya pemanggil (REST controller)
	 * yang menentukan cara meresponsnya (memudahkan testing & pemisahan
	 * tanggung jawab).
	 *
	 * @param string $token Token dari URL.
	 * @return array|WP_Error {type:'stream'|'redirect', ...} atau WP_Error.
	 */
	public static function resolve( $token ) {
		$row = Aksara_Download_Tokens_Repository::get( $token );

		if ( ! $row ) {
			return new WP_Error( 'aksara_invalid_token', __( 'Tautan unduh tidak valid.', 'aksara-marketplace' ), array( 'status' => 404 ) );
		}

		if ( $row->is_revoked ) {
			return new WP_Error( 'aksara_token_revoked', __( 'Tautan unduh ini sudah tidak berlaku.', 'aksara-marketplace' ), array( 'status' => 403 ) );
		}

		if ( $row->expires_at && strtotime( $row->expires_at ) < time() ) {
			return new WP_Error( 'aksara_token_expired', __( 'Tautan unduh ini sudah kedaluwarsa.', 'aksara-marketplace' ), array( 'status' => 403 ) );
		}

		if ( $row->download_count >= $row->download_limit ) {
			return new WP_Error( 'aksara_token_limit', __( 'Batas jumlah unduhan untuk tautan ini sudah tercapai.', 'aksara-marketplace' ), array( 'status' => 403 ) );
		}

		if ( Aksara_Download_Tokens_Repository::RESOURCE_FONT_STYLE === $row->resource_type ) {
			$style = Aksara_Font_Styles_Repository::get( $row->resource_id );
			if ( ! $style ) {
				return new WP_Error( 'aksara_missing_resource', __( 'Berkas tidak ditemukan.', 'aksara-marketplace' ), array( 'status' => 404 ) );
			}

			$path = Aksara_File_Storage::get_absolute_path( $style->file_path );
			if ( ! file_exists( $path ) ) {
				return new WP_Error( 'aksara_missing_resource', __( 'Berkas tidak ditemukan di server.', 'aksara-marketplace' ), array( 'status' => 404 ) );
			}

			Aksara_Download_Tokens_Repository::increment_download_count( $row->id );

			return array(
				'type'     => 'stream',
				'path'     => $path,
				'filename' => $style->style_name . '.' . strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ),
			);
		}

		if ( Aksara_Download_Tokens_Repository::RESOURCE_CANVA === $row->resource_type ) {
			$link = get_post_meta( $row->resource_id, '_aksara_canva_link', true );
			if ( ! $link ) {
				return new WP_Error( 'aksara_missing_resource', __( 'Tautan Canva belum diatur oleh penjual.', 'aksara-marketplace' ), array( 'status' => 404 ) );
			}

			Aksara_Download_Tokens_Repository::increment_download_count( $row->id );

			return array(
				'type' => 'redirect',
				'url'  => $link,
			);
		}

		return new WP_Error( 'aksara_unknown_resource', __( 'Jenis resource tidak dikenali.', 'aksara-marketplace' ), array( 'status' => 500 ) );
	}
}
