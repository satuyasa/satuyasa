<?php
/**
 * Meta box detail portofolio (klien, tautan proyek, tahun).
 *
 * @package Satuyasa_Toolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Satuyasa_Metaboxes.
 */
class Satuyasa_Metaboxes {

	const NONCE_ACTION = 'satuyasa_save_portfolio_details';
	const NONCE_NAME   = 'satuyasa_portfolio_nonce';

	/**
	 * Pasang hook.
	 */
	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'register' ) );
		add_action( 'save_post_portfolio', array( __CLASS__, 'save' ) );
	}

	/**
	 * Daftarkan meta box.
	 */
	public static function register() {
		add_meta_box(
			'satuyasa_portfolio_details',
			__( 'Detail Portofolio', 'satuyasa-toolkit' ),
			array( __CLASS__, 'render' ),
			'portfolio',
			'side',
			'default'
		);
	}

	/**
	 * Cetak field meta box.
	 *
	 * @param WP_Post $post Objek post saat ini.
	 */
	public static function render( $post ) {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		$client = get_post_meta( $post->ID, '_satuyasa_client', true );
		$url    = get_post_meta( $post->ID, '_satuyasa_project_url', true );
		$year   = get_post_meta( $post->ID, '_satuyasa_year', true );
		?>
		<p>
			<label for="satuyasa_client"><?php esc_html_e( 'Nama Klien', 'satuyasa-toolkit' ); ?></label>
			<input type="text" id="satuyasa_client" name="satuyasa_client" class="widefat" value="<?php echo esc_attr( $client ); ?>">
		</p>
		<p>
			<label for="satuyasa_project_url"><?php esc_html_e( 'Tautan Proyek', 'satuyasa-toolkit' ); ?></label>
			<input type="url" id="satuyasa_project_url" name="satuyasa_project_url" class="widefat" placeholder="https://" value="<?php echo esc_attr( $url ); ?>">
		</p>
		<p>
			<label for="satuyasa_year"><?php esc_html_e( 'Tahun Pengerjaan', 'satuyasa-toolkit' ); ?></label>
			<input type="text" id="satuyasa_year" name="satuyasa_year" class="widefat" maxlength="4" value="<?php echo esc_attr( $year ); ?>">
		</p>
		<?php
	}

	/**
	 * Simpan data meta box.
	 *
	 * @param int $post_id ID post.
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

		if ( isset( $_POST['satuyasa_client'] ) ) {
			update_post_meta( $post_id, '_satuyasa_client', sanitize_text_field( wp_unslash( $_POST['satuyasa_client'] ) ) );
		}

		if ( isset( $_POST['satuyasa_project_url'] ) ) {
			update_post_meta( $post_id, '_satuyasa_project_url', esc_url_raw( wp_unslash( $_POST['satuyasa_project_url'] ) ) );
		}

		if ( isset( $_POST['satuyasa_year'] ) ) {
			update_post_meta( $post_id, '_satuyasa_year', sanitize_text_field( wp_unslash( $_POST['satuyasa_year'] ) ) );
		}
	}
}
