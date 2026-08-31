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
			__( 'Info Canva', 'aksara-marketplace' ),
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
			echo '<p>' . esc_html__( 'Set "Product type" ke Canva Template atau Canva Element untuk mengisi info ini.', 'aksara-marketplace' ) . '</p>';
			return;
		}

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		$link       = get_post_meta( $post->ID, '_aksara_canva_link', true );
		$dimensions = get_post_meta( $post->ID, '_aksara_dimensions', true );
		?>
		<p>
			<label for="aksara_canva_link"><strong><?php esc_html_e( 'Tautan Canva', 'aksara-marketplace' ); ?></strong></label><br>
			<input type="url" id="aksara_canva_link" name="aksara_canva_link" class="widefat" placeholder="https://www.canva.com/design/..." value="<?php echo esc_attr( $link ); ?>">
			<span class="description"><?php esc_html_e( 'Link "duplicate template" atau berkas terkait yang dikirim ke pembeli setelah pembayaran berhasil.', 'aksara-marketplace' ); ?></span>
		</p>
		<p>
			<label for="aksara_dimensions"><strong><?php esc_html_e( 'Dimensi', 'aksara-marketplace' ); ?></strong></label><br>
			<input type="text" id="aksara_dimensions" name="aksara_dimensions" class="widefat" placeholder="1080 x 1080 px" value="<?php echo esc_attr( $dimensions ); ?>">
		</p>
		<p class="description">
			<?php esc_html_e( 'Kategori diatur lewat kotak "Product categories" di sidebar kanan (taksonomi bawaan WooCommerce), dipakai untuk filter di halaman Templates/Elements.', 'aksara-marketplace' ); ?>
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
			update_post_meta( $post_id, '_aksara_canva_link', esc_url_raw( wp_unslash( $_POST['aksara_canva_link'] ) ) );
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
		$terms = get_the_terms( $post_id, 'product_type' );
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return 'simple';
		}
		return $terms[0]->slug;
	}
}
