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
				<strong><?php esc_html_e( 'Aksara: the font preview service is down.', 'aksara-marketplace' ); ?></strong>
			</p>
			<p>
				<?php esc_html_e( 'The typing tool on font product pages is temporarily showing static specimen images instead of a live typing preview. The rest of the store (prices, cart, checkout, downloads) is unaffected.', 'aksara-marketplace' ); ?>
			</p>
			<p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=aksara-service-status' ) ); ?>">
					<?php esc_html_e( 'See details and how to bring it back up →', 'aksara-marketplace' ); ?>
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
			__( 'Aksara Service Status', 'aksara-marketplace' ),
			__( 'Aksara Service Status', 'aksara-marketplace' ),
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
			wp_die( esc_html__( 'You do not have permission to access this page.', 'aksara-marketplace' ) );
		}

		// Membuka halaman ini dianggap sebagai permintaan cek ulang.
		$is_up       = self::is_up( true );
		$service_url = class_exists( 'Aksara_Preview_Service_Client' )
			? Aksara_Preview_Service_Client::get_base_url()
			: '-';

		$gd_ok = function_exists( 'imagettftext' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Aksara Service Status', 'aksara-marketplace' ); ?></h1>

			<table class="widefat striped aksara-health-table">
				<tbody>
					<tr>
						<td class="aksara-health-label"><strong><?php esc_html_e( 'Font preview service', 'aksara-marketplace' ); ?></strong><br>
							<span class="description"><?php esc_html_e( 'Required for the interactive typing tool on font product pages.', 'aksara-marketplace' ); ?></span>
						</td>
						<td>
							<?php if ( $is_up ) : ?>
								<span class="aksara-status-up">● <?php esc_html_e( 'Running', 'aksara-marketplace' ); ?></span>
							<?php else : ?>
								<span class="aksara-status-down">● <?php esc_html_e( 'Down', 'aksara-marketplace' ); ?></span>
							<?php endif; ?>
							<br><code><?php echo esc_html( $service_url ); ?></code>
						</td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Specimen rendering (PHP GD)', 'aksara-marketplace' ); ?></strong><br>
							<span class="description"><?php esc_html_e( 'Used for type specimen images in listings and as the typing tool fallback. Needs no external service.', 'aksara-marketplace' ); ?></span>
						</td>
						<td>
							<?php if ( $gd_ok ) : ?>
								<span class="aksara-status-up">● <?php esc_html_e( 'Available', 'aksara-marketplace' ); ?></span>
							<?php else : ?>
								<span class="aksara-status-down">● <?php esc_html_e( 'Unavailable', 'aksara-marketplace' ); ?></span>
								<br><span class="description"><?php esc_html_e( 'The GD extension with FreeType support is not enabled on this server — contact your hosting provider.', 'aksara-marketplace' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
				</tbody>
			</table>

			<?php if ( ! $is_up ) : ?>
				<h2><?php esc_html_e( 'How to bring it back up', 'aksara-marketplace' ); ?></h2>
				<p><?php esc_html_e( 'If it is installed as a systemd service (the recommended setup):', 'aksara-marketplace' ); ?></p>
				<pre class="aksara-code-block">sudo systemctl status aksara-font-preview
sudo systemctl restart aksara-font-preview
sudo journalctl -u aksara-font-preview -n 50</pre>
				<p><?php esc_html_e( 'If it has not been installed as a service yet, see the guide at services/font-preview-service/README.md in the project repository.', 'aksara-marketplace' ); ?></p>
			<?php endif; ?>

			<h2><?php esc_html_e( 'What is affected while it is down', 'aksara-marketplace' ); ?></h2>
			<ul class="aksara-bullet-list">
				<li><?php esc_html_e( 'Font product page typing tool: visitors cannot type their own text, but still see the font specimen image.', 'aksara-marketplace' ); ?></li>
				<li><?php esc_html_e( 'NOT affected: font listings, the price calculator, cart, checkout, post-purchase downloads, and license certificates — none of them use this service.', 'aksara-marketplace' ); ?></li>
			</ul>
		</div>
		<?php
	}
}
