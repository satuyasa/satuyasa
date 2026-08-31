<?php
/**
 * Pemantauan status microservice pratinjau font di sisi admin.
 *
 * Sebelum ini, kalau service pratinjau mati, tidak ada yang tahu sampai
 * ada pelanggan yang komplain — `Aksara_Preview_Service_Client::is_healthy()`
 * sudah ada sejak Fase 2 tapi tidak pernah dipanggil dari mana pun.
 * Class ini yang memakainya.
 *
 * Hasil pengecekan di-cache (transient) supaya tidak menembak service
 * tiap kali halaman admin dibuka: satu HTTP request per beberapa menit
 * sudah cukup untuk kebutuhan "beri tahu admin kalau ada yang mati",
 * dan cache-nya dibersihkan tiap kali admin membuka halaman status
 * supaya bisa memaksa cek ulang dengan me-refresh halaman.
 *
 * @package Aksara_Marketplace
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Aksara_Service_Health.
 */
class Aksara_Service_Health {

	const TRANSIENT_KEY = 'aksara_preview_service_health';
	const CACHE_TTL     = 5 * MINUTE_IN_SECONDS;

	/**
	 * Pasang hook.
	 */
	public static function init() {
		add_action( 'admin_notices', array( __CLASS__, 'maybe_render_notice' ) );
		add_action( 'admin_menu', array( __CLASS__, 'add_status_page' ) );
	}

	/**
	 * Status service, di-cache.
	 *
	 * @param bool $force Abaikan cache dan cek ulang sekarang.
	 * @return bool
	 */
	public static function is_up( $force = false ) {
		if ( ! $force ) {
			$cached = get_transient( self::TRANSIENT_KEY );
			if ( false !== $cached ) {
				return 'up' === $cached;
			}
		}

		$healthy = class_exists( 'Aksara_Preview_Service_Client' )
			&& Aksara_Preview_Service_Client::is_healthy();

		set_transient( self::TRANSIENT_KEY, $healthy ? 'up' : 'down', self::CACHE_TTL );

		return $healthy;
	}

	/**
	 * Tampilkan peringatan di admin kalau service sedang mati — hanya di
	 * halaman yang relevan (daftar/edit produk & halaman Aksara sendiri),
	 * supaya tidak mengganggu seluruh wp-admin.
	 */
	public static function maybe_render_notice() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		$relevant = in_array( $screen->id, array( 'product', 'edit-product', 'dashboard' ), true )
			|| false !== strpos( $screen->id, 'aksara' );

		if ( ! $relevant || self::is_up() ) {
			return;
		}
		?>
		<div class="notice notice-warning">
			<p>
				<strong><?php esc_html_e( 'Aksara: layanan pratinjau font sedang tidak aktif.', 'aksara-marketplace' ); ?></strong>
			</p>
			<p>
				<?php esc_html_e( 'Typing tool di halaman produk font untuk sementara menampilkan gambar contoh statis alih-alih pratinjau ketik langsung. Sisi toko lain (harga, keranjang, checkout, unduhan) tidak terpengaruh.', 'aksara-marketplace' ); ?>
			</p>
			<p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=aksara-service-status' ) ); ?>">
					<?php esc_html_e( 'Lihat detail & cara menjalankannya kembali →', 'aksara-marketplace' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Daftarkan halaman status di bawah menu WooCommerce.
	 */
	public static function add_status_page() {
		add_submenu_page(
			'woocommerce',
			__( 'Status Layanan Aksara', 'aksara-marketplace' ),
			__( 'Status Layanan Aksara', 'aksara-marketplace' ),
			'manage_woocommerce',
			'aksara-service-status',
			array( __CLASS__, 'render_status_page' )
		);
	}

	/**
	 * Halaman status: kondisi service, konfigurasi yang dipakai, dan
	 * langkah perbaikan kalau mati.
	 */
	public static function render_status_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Anda tidak memiliki izin untuk mengakses halaman ini.', 'aksara-marketplace' ) );
		}

		// Membuka halaman ini dianggap sebagai permintaan cek ulang.
		$is_up       = self::is_up( true );
		$service_url = class_exists( 'Aksara_Preview_Service_Client' )
			? Aksara_Preview_Service_Client::get_base_url()
			: '-';

		$gd_ok = function_exists( 'imagettftext' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Status Layanan Aksara', 'aksara-marketplace' ); ?></h1>

			<table class="widefat striped aksara-health-table">
				<tbody>
					<tr>
						<td class="aksara-health-label"><strong><?php esc_html_e( 'Layanan pratinjau font', 'aksara-marketplace' ); ?></strong><br>
							<span class="description"><?php esc_html_e( 'Dibutuhkan untuk typing tool interaktif di halaman produk font.', 'aksara-marketplace' ); ?></span>
						</td>
						<td>
							<?php if ( $is_up ) : ?>
								<span class="aksara-status-up">● <?php esc_html_e( 'Aktif', 'aksara-marketplace' ); ?></span>
							<?php else : ?>
								<span class="aksara-status-down">● <?php esc_html_e( 'Tidak aktif', 'aksara-marketplace' ); ?></span>
							<?php endif; ?>
							<br><code><?php echo esc_html( $service_url ); ?></code>
						</td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Render specimen (PHP GD)', 'aksara-marketplace' ); ?></strong><br>
							<span class="description"><?php esc_html_e( 'Dipakai untuk gambar contoh font di listing & sebagai cadangan typing tool. Tidak butuh layanan eksternal.', 'aksara-marketplace' ); ?></span>
						</td>
						<td>
							<?php if ( $gd_ok ) : ?>
								<span class="aksara-status-up">● <?php esc_html_e( 'Tersedia', 'aksara-marketplace' ); ?></span>
							<?php else : ?>
								<span class="aksara-status-down">● <?php esc_html_e( 'Tidak tersedia', 'aksara-marketplace' ); ?></span>
								<br><span class="description"><?php esc_html_e( 'Ekstensi GD dengan dukungan FreeType tidak aktif di server ini — hubungi penyedia hosting Anda.', 'aksara-marketplace' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
				</tbody>
			</table>

			<?php if ( ! $is_up ) : ?>
				<h2><?php esc_html_e( 'Cara menjalankannya kembali', 'aksara-marketplace' ); ?></h2>
				<p><?php esc_html_e( 'Kalau sudah dipasang sebagai layanan systemd (cara yang disarankan):', 'aksara-marketplace' ); ?></p>
				<pre class="aksara-code-block">sudo systemctl status aksara-font-preview
sudo systemctl restart aksara-font-preview
sudo journalctl -u aksara-font-preview -n 50</pre>
				<p><?php esc_html_e( 'Kalau belum dipasang sebagai layanan, lihat panduan di services/font-preview-service/README.md pada repositori proyek.', 'aksara-marketplace' ); ?></p>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Yang terpengaruh saat layanan mati', 'aksara-marketplace' ); ?></h2>
			<ul class="aksara-bullet-list">
				<li><?php esc_html_e( 'Typing tool halaman produk font: pengunjung tidak bisa mengetik teks sendiri, tapi tetap melihat gambar contoh font (specimen).', 'aksara-marketplace' ); ?></li>
				<li><?php esc_html_e( 'TIDAK terpengaruh: listing font, kalkulator harga, keranjang, checkout, unduhan setelah pembelian, sertifikat lisensi — semuanya tidak memakai layanan ini.', 'aksara-marketplace' ); ?></li>
			</ul>
		</div>
		<?php
	}
}
