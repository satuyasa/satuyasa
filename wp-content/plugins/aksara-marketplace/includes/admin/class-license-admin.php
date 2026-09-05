<?php
/**
 * Halaman admin CRUD sederhana untuk jenis lisensi font (aksara_font_licenses).
 *
 * Data di sini juga dipakai untuk merender halaman "License" di frontend
 * (referensi hukum untuk semua produk) dan matriks harga di metabox Font Styles.
 *
 * @package Aksara_Marketplace
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Aksara_License_Admin.
 */
class Aksara_License_Admin {

	const NONCE_ACTION = 'aksara_save_license';
	const NONCE_NAME    = 'aksara_license_nonce';

	/**
	 * Pasang hook.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
	}

	/**
	 * Tambahkan submenu di bawah menu WooCommerce.
	 */
	public static function add_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Font Licenses — Aksara', 'aksara-marketplace' ),
			__( 'Font Licenses', 'aksara-marketplace' ),
			'manage_woocommerce',
			'aksara-font-licenses',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Proses form (jika ada) lalu cetak halaman.
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'aksara-marketplace' ) );
		}

		/*
		 * Kenapa error dikembalikan lalu dicetak inline, bukan diantrekan
		 * seperti pesan sukses: maybe_handle_form() berjalan dari dalam
		 * render_page(), yang di wp-admin dipanggil SETELAH hook
		 * admin_notices selesai. Jalur yang diakhiri redirect aman (notice
		 * tampil di pemuatan berikutnya), tapi jalur yang cuma return —
		 * seperti validasi gagal di bawah — notice-nya akan tertunda satu
		 * halaman dan muncul di layar yang sama sekali tidak berhubungan.
		 */
		$form_error = self::maybe_handle_form();

		$editing = null;
		if ( isset( $_GET['edit'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only, only selects which record to prefill.
			$editing = Aksara_Font_Licenses_Repository::get( absint( $_GET['edit'] ) );
		}

		$licenses = Aksara_Font_Licenses_Repository::get_all();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Font Licenses', 'aksara-marketplace' ); ?></h1>

			<?php if ( $form_error ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( $form_error ); ?></p></div>
			<?php endif; ?>

			<p><?php esc_html_e( 'Manage license types (Desktop, Web, App, and so on). The descriptions here are rendered automatically on the site\'s "License" page.', 'aksara-marketplace' ); ?></p>

			<div class="aksara-admin-columns">
				<div class="aksara-admin-col-main">
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Name', 'aksara-marketplace' ); ?></th>
								<th><?php esc_html_e( 'Slug', 'aksara-marketplace' ); ?></th>
								<th><?php esc_html_e( 'Order', 'aksara-marketplace' ); ?></th>
								<th></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( empty( $licenses ) ) : ?>
								<tr><td colspan="4"><?php esc_html_e( 'No licenses yet.', 'aksara-marketplace' ); ?></td></tr>
							<?php endif; ?>
							<?php foreach ( $licenses as $license ) : ?>
								<tr>
									<td><?php echo esc_html( $license->name ); ?></td>
									<td><code><?php echo esc_html( $license->slug ); ?></code></td>
									<td><?php echo esc_html( $license->sort_order ); ?></td>
									<td>
										<a href="<?php echo esc_url( add_query_arg( 'edit', $license->id ) ); ?>"><?php esc_html_e( 'Edit', 'aksara-marketplace' ); ?></a>
										|
										<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'aksara_delete_license' => $license->id ) ), 'aksara_delete_license_' . $license->id ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this license? Style prices that use it will be deleted too.', 'aksara-marketplace' ) ); ?>');"><?php esc_html_e( 'Delete', 'aksara-marketplace' ); ?></a>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>

				<div class="aksara-admin-col-side">
					<h2><?php echo $editing ? esc_html__( 'Edit License', 'aksara-marketplace' ) : esc_html__( 'Add License', 'aksara-marketplace' ); ?></h2>
					<form method="post">
						<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>
						<input type="hidden" name="license_id" value="<?php echo esc_attr( $editing->id ?? '' ); ?>">
						<p>
							<label><?php esc_html_e( 'Name', 'aksara-marketplace' ); ?></label><br>
							<input type="text" class="widefat" name="name" required value="<?php echo esc_attr( $editing->name ?? '' ); ?>">
						</p>
						<p>
							<label><?php esc_html_e( 'Slug', 'aksara-marketplace' ); ?></label><br>
							<input type="text" class="widefat" name="slug" placeholder="<?php esc_attr_e( 'leave empty to generate from the name', 'aksara-marketplace' ); ?>" value="<?php echo esc_attr( $editing->slug ?? '' ); ?>">
						</p>
						<p>
							<label><?php esc_html_e( 'Order', 'aksara-marketplace' ); ?></label><br>
							<input type="number" name="sort_order" value="<?php echo esc_attr( $editing->sort_order ?? 0 ); ?>">
						</p>
						<p>
							<label><?php esc_html_e( 'Description (legal text, shown on the License page)', 'aksara-marketplace' ); ?></label><br>
							<?php
							wp_editor(
								$editing->description ?? '',
								'aksara_license_description',
								array(
									'textarea_name' => 'description',
									'textarea_rows' => 6,
									'media_buttons' => false,
									'teeny'         => true,
								)
							);
							?>
						</p>
						<p>
							<button type="submit" class="button button-primary"><?php echo $editing ? esc_html__( 'Save Changes', 'aksara-marketplace' ) : esc_html__( 'Add License', 'aksara-marketplace' ); ?></button>
							<?php if ( $editing ) : ?>
								<a class="button" href="<?php echo esc_url( remove_query_arg( 'edit' ) ); ?>"><?php esc_html_e( 'Cancel', 'aksara-marketplace' ); ?></a>
							<?php endif; ?>
						</p>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Proses submit form tambah/edit, dan aksi hapus lewat query string.
	 *
	 * @return string Pesan error untuk dicetak inline, atau string kosong.
	 */
	private static function maybe_handle_form() {
		if ( isset( $_GET['aksara_delete_license'] ) ) {
			$id = absint( $_GET['aksara_delete_license'] );
			check_admin_referer( 'aksara_delete_license_' . $id );
			Aksara_Font_Licenses_Repository::delete( $id );
			Aksara_Admin_UI::queue_notice( __( 'License deleted.', 'aksara-marketplace' ), 'success' );
			wp_safe_redirect( remove_query_arg( array( 'aksara_delete_license', '_wpnonce' ) ) );
			exit;
		}

		if ( empty( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( wp_unslash( $_POST[ self::NONCE_NAME ] ), self::NONCE_ACTION ) ) {
			return '';
		}

		if ( empty( $_POST['name'] ) ) {
			// Nama kosong dulu berarti form diam saja: tidak tersimpan, tidak
			// ada pesan, dan isian yang sudah diketik tetap terlihat — mudah
			// disangka sudah tersimpan padahal tidak.
			return __( 'The license name is required — nothing was saved.', 'aksara-marketplace' );
		}

		$id = ! empty( $_POST['license_id'] ) ? absint( $_POST['license_id'] ) : null;

		Aksara_Font_Licenses_Repository::save(
			array(
				'name'        => wp_unslash( $_POST['name'] ),
				'slug'        => wp_unslash( $_POST['slug'] ?? '' ),
				'description' => wp_unslash( $_POST['description'] ?? '' ),
				'sort_order'  => wp_unslash( $_POST['sort_order'] ?? 0 ),
			),
			$id
		);

		Aksara_Admin_UI::queue_notice(
			$id ? __( 'License changes saved.', 'aksara-marketplace' ) : __( 'New license added.', 'aksara-marketplace' ),
			'success'
		);

		wp_safe_redirect( remove_query_arg( 'edit' ) );
		exit;
	}
}
