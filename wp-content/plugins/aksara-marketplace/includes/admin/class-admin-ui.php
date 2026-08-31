<?php
/**
 * Perkakas bersama untuk seluruh UI admin plugin: form tag upload,
 * stylesheet admin, dan antrean notice lintas-redirect.
 *
 * @package Aksara_Marketplace
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Aksara_Admin_UI.
 */
class Aksara_Admin_UI {

	/**
	 * Nama transient tempat notice diparkir sampai halaman berikutnya dimuat.
	 */
	const NOTICE_TRANSIENT = 'aksara_admin_notices_';

	/**
	 * Pasang hook.
	 */
	public static function init() {
		add_action( 'post_edit_form_tag', array( __CLASS__, 'add_multipart_enctype' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_notices', array( __CLASS__, 'print_queued_notices' ) );
	}

	/**
	 * Tambahkan enctype multipart ke form editor post.
	 *
	 * PERBAIKAN BUG — ini bukan sekadar rapi-rapi. Form editor post bawaan
	 * WordPress dicetak sebagai:
	 *
	 *     <form name="post" action="post.php" method="post" id="post">
	 *
	 * tanpa enctype sama sekali, jadi defaultnya application/x-www-form-
	 * urlencoded. Dengan enctype itu, browser hanya mengirimkan NAMA berkas
	 * dari <input type="file">, bukan isinya — sehingga $_FILES di sisi
	 * server SELALU kosong.
	 *
	 * Akibatnya bulk upload style di metabox "Font Styles" tidak pernah
	 * benar-benar bekerja: admin memilih berkas, menekan Update, halaman
	 * tersimpan tanpa error apa pun, dan tidak ada satu pun style yang
	 * bertambah. Gagal diam-diam — jenis kegagalan yang paling lama tidak
	 * ketahuan.
	 *
	 * Hook `post_edit_form_tag` adalah satu-satunya titik yang disediakan
	 * WordPress untuk menyisipkan atribut ke dalam tag <form> tersebut.
	 *
	 * @param WP_Post|null $post Post yang sedang disunting.
	 */
	public static function add_multipart_enctype( $post = null ) {
		// Hanya untuk layar yang memang punya field upload (produk), supaya
		// tidak mengubah perilaku form editor lain tanpa alasan.
		if ( $post instanceof WP_Post && 'product' !== $post->post_type ) {
			return;
		}

		echo ' enctype="multipart/form-data"';
	}

	/**
	 * Muat stylesheet admin di layar yang memakainya saja.
	 *
	 * @param string $hook_suffix Hook suffix layar admin saat ini.
	 */
	public static function enqueue_assets( $hook_suffix ) {
		$screens = array( 'post.php', 'post-new.php', 'index.php' );

		$is_plugin_page = isset( $_GET['page'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- hanya menentukan apakah CSS dimuat.
			&& 0 === strpos( sanitize_key( wp_unslash( $_GET['page'] ) ), 'aksara-' );

		if ( ! in_array( $hook_suffix, $screens, true ) && ! $is_plugin_page ) {
			return;
		}

		wp_enqueue_style(
			'aksara-admin',
			AKSARA_MARKETPLACE_URL . 'assets/css/admin.css',
			array(),
			AKSARA_MARKETPLACE_VERSION
		);
	}

	/**
	 * Antrekan sebuah notice untuk ditampilkan setelah redirect berikutnya.
	 *
	 * Kenapa lewat transient dan bukan langsung dicetak: penyimpanan di
	 * admin selalu diikuti redirect (pola Post/Redirect/Get), jadi apa pun
	 * yang dicetak saat memproses POST akan hilang sebelum sempat terlihat.
	 * Transient di-scope per user supaya notice milik satu admin tidak
	 * muncul di layar admin lain yang kebetulan sedang bekerja bersamaan.
	 *
	 * @param string $message Pesan (teks biasa).
	 * @param string $type    'success' | 'warning' | 'error' | 'info'.
	 */
	public static function queue_notice( $message, $type = 'success' ) {
		$key      = self::NOTICE_TRANSIENT . get_current_user_id();
		$existing = get_transient( $key );
		if ( ! is_array( $existing ) ) {
			$existing = array();
		}

		$existing[] = array(
			'message' => (string) $message,
			'type'    => in_array( $type, array( 'success', 'warning', 'error', 'info' ), true ) ? $type : 'info',
		);

		set_transient( $key, $existing, MINUTE_IN_SECONDS * 5 );
	}

	/**
	 * Cetak notice yang mengantre, lalu kosongkan antreannya.
	 */
	public static function print_queued_notices() {
		$key     = self::NOTICE_TRANSIENT . get_current_user_id();
		$notices = get_transient( $key );

		if ( empty( $notices ) || ! is_array( $notices ) ) {
			return;
		}

		delete_transient( $key );

		foreach ( $notices as $notice ) {
			printf(
				'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
				esc_attr( $notice['type'] ),
				esc_html( $notice['message'] )
			);
		}
	}
}
