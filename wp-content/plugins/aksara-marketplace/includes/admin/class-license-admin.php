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
			__( 'Lisensi Font — Aksara', 'aksara-marketplace' ),
			__( 'Lisensi Font', 'aksara-marketplace' ),
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
			wp_die( esc_html__( 'Anda tidak memiliki izin untuk mengakses halaman ini.', 'aksara-marketplace' ) );
		}

		self::maybe_handle_form();

		$editing = null;
		if ( isset( $_GET['edit'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only, only selects which record to prefill.
			$editing = Aksara_Font_Licenses_Repository::get( absint( $_GET['edit'] ) );
		}

		$licenses = Aksara_Font_Licenses_Repository::get_all();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Lisensi Font', 'aksara-marketplace' ); ?></h1>
			<p><?php esc_html_e( 'Kelola jenis lisensi (Desktop, Web, App, dst). Deskripsi di sini otomatis dirender di halaman "License" pada situs.', 'aksara-marketplace' ); ?></p>

			<div style="display:flex; gap:32px; align-items:flex-start;">
				<div style="flex:1;">
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Nama', 'aksara-marketplace' ); ?></th>
								<th><?php esc_html_e( 'Slug', 'aksara-marketplace' ); ?></th>
								<th><?php esc_html_e( 'Urutan', 'aksara-marketplace' ); ?></th>
								<th></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( empty( $licenses ) ) : ?>
								<tr><td colspan="4"><?php esc_html_e( 'Belum ada lisensi.', 'aksara-marketplace' ); ?></td></tr>
							<?php endif; ?>
							<?php foreach ( $licenses as $license ) : ?>
								<tr>
									<td><?php echo esc_html( $license->name ); ?></td>
									<td><code><?php echo esc_html( $license->slug ); ?></code></td>
									<td><?php echo esc_html( $license->sort_order ); ?></td>
									<td>
										<a href="<?php echo esc_url( add_query_arg( 'edit', $license->id ) ); ?>"><?php esc_html_e( 'Sunting', 'aksara-marketplace' ); ?></a>
										|
										<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'aksara_delete_license' => $license->id ) ), 'aksara_delete_license_' . $license->id ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Hapus lisensi ini? Harga style yang memakainya juga akan terhapus.', 'aksara-marketplace' ) ); ?>');"><?php esc_html_e( 'Hapus', 'aksara-marketplace' ); ?></a>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>

				<div style="width:360px;">
					<h2><?php echo $editing ? esc_html__( 'Sunting Lisensi', 'aksara-marketplace' ) : esc_html__( 'Tambah Lisensi', 'aksara-marketplace' ); ?></h2>
					<form method="post">
						<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>
						<input type="hidden" name="license_id" value="<?php echo esc_attr( $editing->id ?? '' ); ?>">
						<p>
							<label><?php esc_html_e( 'Nama', 'aksara-marketplace' ); ?></label><br>
							<input type="text" class="widefat" name="name" required value="<?php echo esc_attr( $editing->name ?? '' ); ?>">
						</p>
						<p>
							<label><?php esc_html_e( 'Slug', 'aksara-marketplace' ); ?></label><br>
							<input type="text" class="widefat" name="slug" placeholder="<?php esc_attr_e( 'kosongkan untuk otomatis dari nama', 'aksara-marketplace' ); ?>" value="<?php echo esc_attr( $editing->slug ?? '' ); ?>">
						</p>
						<p>
							<label><?php esc_html_e( 'Urutan', 'aksara-marketplace' ); ?></label><br>
							<input type="number" name="sort_order" value="<?php echo esc_attr( $editing->sort_order ?? 0 ); ?>">
						</p>
						<p>
							<label><?php esc_html_e( 'Deskripsi (legal, tampil di halaman License)', 'aksara-marketplace' ); ?></label><br>
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
							<button type="submit" class="button button-primary"><?php echo $editing ? esc_html__( 'Simpan Perubahan', 'aksara-marketplace' ) : esc_html__( 'Tambah Lisensi', 'aksara-marketplace' ); ?></button>
							<?php if ( $editing ) : ?>
								<a class="button" href="<?php echo esc_url( remove_query_arg( 'edit' ) ); ?>"><?php esc_html_e( 'Batal', 'aksara-marketplace' ); ?></a>
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
	 */
	private static function maybe_handle_form() {
		if ( isset( $_GET['aksara_delete_license'] ) ) {
			$id = absint( $_GET['aksara_delete_license'] );
			check_admin_referer( 'aksara_delete_license_' . $id );
			Aksara_Font_Licenses_Repository::delete( $id );
			wp_safe_redirect( remove_query_arg( array( 'aksara_delete_license', '_wpnonce' ) ) );
			exit;
		}

		if ( empty( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( wp_unslash( $_POST[ self::NONCE_NAME ] ), self::NONCE_ACTION ) ) {
			return;
		}

		if ( empty( $_POST['name'] ) ) {
			return;
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

		wp_safe_redirect( remove_query_arg( 'edit' ) );
		exit;
	}
}
