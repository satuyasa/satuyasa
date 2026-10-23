<?php
/**
 * Metabox "Info Canva" untuk produk Canva Template & Canva Element.
 *
 * Kategori produk sengaja TIDAK ditambahkan sebagai field baru di sini —
 * WooCommerce sudah punya taksonomi Product categories bawaan yang bisa
 * langsung dipakai untuk filter di halaman Templates/Elements (PRD
 * Bagian 5), jadi tidak perlu reinvent.
 *
 * @package Aksara_Marketplace
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Aksara_Canva_Info_Metabox.
 */
class Aksara_Canva_Info_Metabox {

	const NONCE_ACTION = 'aksara_save_canva_info';
	const NONCE_NAME   = 'aksara_canva_info_nonce';

	/**
	 * Pasang hook.
	 */
	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'register' ) );
		add_action( 'save_post_product', array( __CLASS__, 'save' ) );
	}

	/**
	 * Daftarkan meta box (tampil untuk semua produk; pesan penjelas muncul
	 * jika product type belum di-set ke Canva Template/Element).
	 */
	public static function register() {
		add_meta_box(
			'aksara_canva_info',
			__( 'Canva Info', 'aksara-marketplace' ),
			array( __CLASS__, 'render' ),
			'product',
			'normal',
			'default'
		);
	}

	/**
	 * Cetak field metabox.
	 *
	 * @param WP_Post $post Post produk saat ini.
	 */
	public static function render( $post ) {
		$product_type = self::get_current_product_type( $post->ID );

		if ( ! in_array( $product_type, array( 'canva_template', 'canva_element' ), true ) ) {
			echo '<p>' . esc_html__( 'Set "Product type" to Canva Template or Canva Element, then save the draft — these fields appear once the type is saved.', 'aksara-marketplace' ) . '</p>';
			return;
		}

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		$link       = get_post_meta( $post->ID, '_aksara_canva_link', true );
		$dimensions = get_post_meta( $post->ID, '_aksara_dimensions', true );

		/*
		 * Tautan Canva adalah SATU-SATUNYA hal yang diterima pembeli untuk
		 * produk ini (lihat Aksara_Download_Manager, yang membaca meta yang
		 * sama). Produk yang terbit tanpa tautan tetap bisa dibeli dan
		 * dibayar, lalu pembeli mendapat halaman unduhan kosong — kegagalan
		 * yang baru ketahuan setelah ada uang berpindah.
		 */
		if ( 'publish' === get_post_status( $post->ID ) && '' === trim( (string) $link ) ) {
			printf(
				'<div class="notice notice-warning inline aksara-inline-notice"><p>%s</p></div>',
				esc_html__( 'This product is published but has no Canva link. Buyers would pay and receive nothing — add the link below.', 'aksara-marketplace' )
			);
		}
		?>
		<p>
			<label for="aksara_canva_link"><strong><?php esc_html_e( 'Canva Link', 'aksara-marketplace' ); ?></strong></label><br>
			<input type="url" id="aksara_canva_link" name="aksara_canva_link" class="widefat" placeholder="https://www.canva.com/design/..." value="<?php echo esc_attr( $link ); ?>">
			<span class="description"><?php esc_html_e( 'The "duplicate template" link or related file sent to the buyer after successful payment.', 'aksara-marketplace' ); ?></span>
		</p>
		<p>
			<label for="aksara_dimensions"><strong><?php esc_html_e( 'Dimensions', 'aksara-marketplace' ); ?></strong></label><br>
			<input type="text" id="aksara_dimensions" name="aksara_dimensions" class="widefat" placeholder="1080 x 1080 px" value="<?php echo esc_attr( $dimensions ); ?>">
		</p>
		<p class="description">
			<?php esc_html_e( 'Categories are set in the "Product categories" box in the right sidebar (WooCommerce\'s own taxonomy). They drive the filters on the Templates and Elements pages.', 'aksara-marketplace' ); ?>
		</p>
		<?php
	}

	/**
	 * Simpan field metabox.
	 *
	 * @param int $post_id ID produk.
	 */
	public static function save( $post_id ) {
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( wp_unslash( $_POST[ self::NONCE_NAME ] ), self::NONCE_ACTION ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( isset( $_POST['aksara_canva_link'] ) ) {
			$link = esc_url_raw( wp_unslash( $_POST['aksara_canva_link'] ) );
			$host = strtolower( (string) wp_parse_url( $link, PHP_URL_HOST ) );
			$allowed = (array) apply_filters( 'aksara_allowed_canva_hosts', array( 'canva.com', 'www.canva.com' ) );
			if ( '' === $link || ( 'https' === wp_parse_url( $link, PHP_URL_SCHEME ) && in_array( $host, $allowed, true ) ) ) {
				update_post_meta( $post_id, '_aksara_canva_link', $link );
			} elseif ( class_exists( 'Aksara_Admin_UI' ) ) {
				Aksara_Admin_UI::queue_notice( __( 'Canva link was not saved. Use an HTTPS link hosted on canva.com.', 'aksara-marketplace' ), 'error' );
			}
		}

		if ( isset( $_POST['aksara_dimensions'] ) ) {
			update_post_meta( $post_id, '_aksara_dimensions', sanitize_text_field( wp_unslash( $_POST['aksara_dimensions'] ) ) );
		}
	}

	/**
	 * Ambil slug product type produk saat ini (dari taksonomi product_type).
	 *
	 * @param int $post_id ID produk.
	 * @return string
	 */
	public static function get_current_product_type( $post_id ) {
		/*
		 * During a save request, trust the submitted dropdown over the stored
		 * term. WordPress fires save_post_{post_type} before save_post, and
		 * WooCommerce writes the product_type term from its own save_post
		 * handler — so a metabox saving on save_post_product can still read
		 * the PREVIOUS type. Without this, the first save after switching a
		 * product to Font would silently drop everything submitted from the
		 * Font Styles box, with no error shown.
		 *
		 * Read-only type detection: every caller that writes anything checks
		 * its own nonce before doing so.
		 */
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- read-only; writers verify their own nonce.
		if ( isset( $_POST['product-type'] ) ) {
			$submitted = sanitize_key( wp_unslash( $_POST['product-type'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( '' !== $submitted ) {
				return $submitted;
			}
		}

		$terms = get_the_terms( $post_id, 'product_type' );
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return 'simple';
		}
		return $terms[0]->slug;
	}
}
