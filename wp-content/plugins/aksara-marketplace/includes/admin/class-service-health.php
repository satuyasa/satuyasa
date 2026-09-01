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

	const EXPOSURE_TRANSIENT = 'aksara_private_dir_exposed';
	const EXPOSURE_TTL       = 12 * HOUR_IN_SECONDS;
	const CANARY_FILENAME    = 'exposure-check.txt';
	const CANARY_CONTENT     = 'aksara-private-dir-exposure-canary';

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
	/**
	 * Apakah folder privat benar-benar bisa diambil lewat HTTP publik?
	 *
	 * Seluruh model keamanan bertumpu pada folder ini tidak bisa dibaca dari
	 * web: berkas font asli ada di dalamnya, dan satu-satunya penghalang
	 * yang dipasang plugin adalah .htaccess. Nginx TIDAK PERNAH membaca
	 * .htaccess — jadi di stack Nginx (yang sangat umum) berkas font berbayar
	 * bisa diunduh langsung oleh siapa saja yang menebak URL-nya, tanpa
	 * membeli, dan tidak ada satu pun gejala yang terlihat dari dalam
	 * WordPress. Kondisi paling merusak yang mungkin terjadi di sistem ini
	 * juga yang paling sunyi.
	 *
	 * Karena itu tidak cukup mendokumentasikannya: di sini kondisinya
	 * benar-benar DIUJI. Sebuah berkas umpan berisi teks yang sudah diketahui
	 * (bukan rahasia apa pun) ditulis ke folder itu, lalu diambil lewat URL
	 * publiknya. Kalau isinya kembali, berarti folder itu memang terbuka.
	 *
	 * @param bool $force Abaikan cache.
	 * @return bool|null true = terbuka, false = aman, null = tidak bisa dipastikan.
	 */
	public static function is_private_dir_exposed( $force = false ) {
		if ( ! $force ) {
			$cached = get_transient( self::EXPOSURE_TRANSIENT );
			if ( false !== $cached ) {
				return 'unknown' === $cached ? null : ( 'exposed' === $cached );
			}
		}

		$result = self::probe_private_dir();

		set_transient(
			self::EXPOSURE_TRANSIENT,
			null === $result ? 'unknown' : ( $result ? 'exposed' : 'safe' ),
			self::EXPOSURE_TTL
		);

		return $result;
	}

	/**
	 * Tulis berkas umpan lalu coba ambil lewat HTTP.
	 *
	 * @return bool|null
	 */
	private static function probe_private_dir() {
		if ( ! class_exists( 'Aksara_File_Storage' ) ) {
			return null;
		}

		$dir = Aksara_File_Storage::ensure_protected_dir();
		if ( ! is_dir( $dir ) ) {
			return null;
		}

		$canary_path = trailingslashit( $dir ) . self::CANARY_FILENAME;

		if ( ! file_exists( $canary_path ) ) {
			$written = file_put_contents( $canary_path, self::CANARY_CONTENT ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			if ( false === $written ) {
				return null;
			}
		}

		$uploads = wp_upload_dir();
		if ( empty( $uploads['baseurl'] ) ) {
			return null;
		}

		$url = trailingslashit( $uploads['baseurl'] ) . Aksara_File_Storage::SUBDIR . '/' . self::CANARY_FILENAME;

		$response = wp_remote_get(
			$url,
			array(
				'timeout'   => 5,
				'sslverify' => false, // Situs staging sering memakai sertifikat self-signed; yang diuji di sini akses berkasnya, bukan rantai TLS-nya.
			)
		);

		if ( is_wp_error( $response ) ) {
			// Tidak bisa memanggil diri sendiri (loopback diblokir, DNS internal
			// aneh). Itu bukan bukti aman — jadi jangan laporkan sebagai aman.
			return null;
		}

		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}

		return false !== strpos( wp_remote_retrieve_body( $response ), self::CANARY_CONTENT );
	}

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

		/*
		 * Keterbukaan folder privat diperiksa lebih dulu, dan TIDAK dibatasi
		 * ke layar-layar tertentu seperti notice layanan pratinjau di bawah.
		 * Layanan pratinjau mati hanya menurunkan satu fitur; folder privat
		 * yang terbuka berarti seluruh katalog font berbayar bisa diunduh
		 * siapa saja. Itu harus terlihat di mana pun admin sedang berada.
		 */
		if ( true === self::is_private_dir_exposed() ) {
			?>
			<div class="notice notice-error">
				<p>
					<strong><?php esc_html_e( 'Aksara: your font files are publicly downloadable.', 'aksara-marketplace' ); ?></strong>
				</p>
				<p>
					<?php esc_html_e( 'The private uploads folder can be fetched directly over HTTP, so anyone who guesses a file URL can download your paid font files without buying them. This was verified by requesting a test file from the folder, not merely assumed.', 'aksara-marketplace' ); ?>
				</p>
				<p>
					<?php esc_html_e( 'This usually means the site runs on Nginx, which ignores .htaccess entirely. Add a matching deny rule to your server configuration.', 'aksara-marketplace' ); ?>
				</p>
				<p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=aksara-service-status' ) ); ?>">
						<?php esc_html_e( 'Show me the rule to add →', 'aksara-marketplace' ); ?>
					</a>
				</p>
			</div>
			<?php
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
		$exposed = self::is_private_dir_exposed( true );
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
					<tr>
						<td><strong><?php esc_html_e( 'Private file protection', 'aksara-marketplace' ); ?></strong><br>
							<span class="description"><?php esc_html_e( 'Whether the folder holding your original font files can be reached directly over HTTP.', 'aksara-marketplace' ); ?></span>
						</td>
						<td>
							<?php if ( true === $exposed ) : ?>
								<span class="aksara-status-down">● <?php esc_html_e( 'Publicly readable', 'aksara-marketplace' ); ?></span>
								<br><span class="description"><?php esc_html_e( 'Verified by fetching a test file from the folder over HTTP.', 'aksara-marketplace' ); ?></span>
							<?php elseif ( false === $exposed ) : ?>
								<span class="aksara-status-up">● <?php esc_html_e( 'Blocked', 'aksara-marketplace' ); ?></span>
							<?php else : ?>
								<span class="description"><?php esc_html_e( 'Could not be checked — this site could not make an HTTP request to itself. Verify manually before going live.', 'aksara-marketplace' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
				</tbody>
			</table>

			<?php if ( true === $exposed ) : ?>
				<h2><?php esc_html_e( 'Fix: block direct access to the private folder', 'aksara-marketplace' ); ?></h2>
				<p><?php esc_html_e( 'The plugin writes an .htaccess file, which Apache honours but Nginx ignores completely. On Nginx, add this inside your server block and reload:', 'aksara-marketplace' ); ?></p>
				<pre class="aksara-code-block">location ~* /wp-content/uploads/<?php echo esc_html( Aksara_File_Storage::SUBDIR ); ?>/ {
    deny all;
    return 403;
}</pre>
				<p><?php esc_html_e( 'On Apache, check that AllowOverride is enabled for the uploads directory — otherwise the .htaccess file is ignored there too.', 'aksara-marketplace' ); ?></p>
				<p><?php esc_html_e( 'Until this is fixed, anyone who guesses a file URL can download your paid font files without buying them.', 'aksara-marketplace' ); ?></p>
			<?php endif; ?>

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
