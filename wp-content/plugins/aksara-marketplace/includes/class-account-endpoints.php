<?php
/**
 * 3 tab baru di My Account: Downloads, Sertifikat Lisensi, Wishlist.
 *
 * Didaftarkan lewat mekanisme rewrite endpoint resmi WooCommerce
 * (add_rewrite_endpoint + woocommerce_get_query_vars + woocommerce_account_menu_items
 * + woocommerce_account_{endpoint}_endpoint) — bukan halaman/Page terpisah —
 * supaya otomatis tampil di sidebar navigasi My Account bawaan tema apa pun.
 *
 * @package Aksara_Marketplace
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Aksara_Account_Endpoints.
 */
class Aksara_Account_Endpoints {

	const ENDPOINTS_VERSION_OPTION = 'aksara_marketplace_endpoints_version';
	const ENDPOINTS_VERSION        = '1.0';

	const ENDPOINTS = array(
		'aksara-downloads'    => 'Unduhan Saya',
		'aksara-certificates' => 'Sertifikat Lisensi',
		'aksara-wishlist'     => 'Wishlist',
	);

	/**
	 * Pasang hook.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_endpoints' ) );
		add_filter( 'woocommerce_get_query_vars', array( __CLASS__, 'add_query_vars' ) );
		add_filter( 'woocommerce_account_menu_items', array( __CLASS__, 'add_menu_items' ) );

		add_action( 'woocommerce_account_aksara-downloads_endpoint', array( __CLASS__, 'render_downloads' ) );
		add_action( 'woocommerce_account_aksara-certificates_endpoint', array( __CLASS__, 'render_certificates' ) );
		add_action( 'woocommerce_account_aksara-wishlist_endpoint', array( __CLASS__, 'render_wishlist' ) );
	}

	/**
	 * Daftarkan rewrite endpoint & siram ulang rewrite rules SEKALI saja
	 * kalau ada endpoint baru dibanding versi sebelumnya (bukan tiap request —
	 * flush_rewrite_rules() berat dan tidak boleh dipanggil tiap page load).
	 */
	public static function register_endpoints() {
		foreach ( array_keys( self::ENDPOINTS ) as $endpoint ) {
			add_rewrite_endpoint( $endpoint, EP_ROOT | EP_PAGES );
		}

		if ( get_option( self::ENDPOINTS_VERSION_OPTION ) !== self::ENDPOINTS_VERSION ) {
			update_option( self::ENDPOINTS_VERSION_OPTION, self::ENDPOINTS_VERSION );
			flush_rewrite_rules();
		}
	}

	/**
	 * Kenalkan endpoint ke WC_Query supaya query var-nya dikenali.
	 *
	 * @param array $vars Query var yang sudah ada.
	 * @return array
	 */
	public static function add_query_vars( $vars ) {
		foreach ( array_keys( self::ENDPOINTS ) as $endpoint ) {
			$vars[ $endpoint ] = $endpoint;
		}
		return $vars;
	}

	/**
	 * Sisipkan menu baru sebelum "Keluar" di navigasi My Account.
	 *
	 * @param array $items Menu yang sudah ada.
	 * @return array
	 */
	public static function add_menu_items( $items ) {
		$new_items = array();

		foreach ( $items as $key => $label ) {
			if ( 'customer-logout' === $key ) {
				foreach ( self::ENDPOINTS as $endpoint => $endpoint_label ) {
					$new_items[ $endpoint ] = self::translated_label( $endpoint );
				}
			}
			$new_items[ $key ] = $label;
		}

		return $new_items;
	}

	/**
	 * Label menu yang sudah diterjemahkan (dipisah dari konstanta karena
	 * __() butuh string literal, tidak bisa dipanggil langsung di dalam const array).
	 *
	 * @param string $endpoint Slug endpoint.
	 * @return string
	 */
	private static function translated_label( $endpoint ) {
		$labels = array(
			'aksara-downloads'    => __( 'Unduhan Saya', 'aksara-marketplace' ),
			'aksara-certificates' => __( 'Sertifikat Lisensi', 'aksara-marketplace' ),
			'aksara-wishlist'     => __( 'Wishlist', 'aksara-marketplace' ),
		);
		return $labels[ $endpoint ] ?? $endpoint;
	}

	/**
	 * Render tab "Unduhan Saya": seluruh token unduh milik user yang login,
	 * lengkap dengan sisa kuota unduhan.
	 */
	public static function render_downloads() {
		$tokens = Aksara_Download_Tokens_Repository::get_by_user( get_current_user_id() );
		?>
		<h2><?php esc_html_e( 'Unduhan Saya', 'aksara-marketplace' ); ?></h2>

		<?php if ( empty( $tokens ) ) : ?>
			<p><?php esc_html_e( 'Belum ada berkas yang bisa diunduh. Berkas akan muncul di sini setelah pembayaran Anda dikonfirmasi.', 'aksara-marketplace' ); ?></p>
		<?php else : ?>
			<table class="woocommerce-table shop_table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Berkas', 'aksara-marketplace' ); ?></th>
						<th><?php esc_html_e( 'Order', 'aksara-marketplace' ); ?></th>
						<th><?php esc_html_e( 'Sisa Unduhan', 'aksara-marketplace' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $tokens as $token_row ) : ?>
						<?php
						$label = self::resource_label( $token_row );
						if ( ! $label ) {
							continue;
						}
						$remaining = max( 0, $token_row->download_limit - $token_row->download_count );
						?>
						<tr>
							<td><?php echo esc_html( $label ); ?></td>
							<td>
								<a href="<?php echo esc_url( wc_get_endpoint_url( 'view-order', $token_row->order_id, wc_get_page_permalink( 'myaccount' ) ) ); ?>">
									#<?php echo esc_html( $token_row->order_id ); ?>
								</a>
							</td>
							<td><?php echo esc_html( $remaining ); ?></td>
							<td>
								<?php if ( $remaining > 0 ) : ?>
									<a class="button" href="<?php echo esc_url( Aksara_Download_Manager::get_download_url( $token_row->token ) ); ?>"><?php esc_html_e( 'Unduh', 'aksara-marketplace' ); ?></a>
								<?php else : ?>
									<span class="description"><?php esc_html_e( 'Batas tercapai', 'aksara-marketplace' ); ?></span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
		<?php
	}

	/**
	 * Label tampilan untuk satu baris token (nama style, atau judul produk Canva).
	 *
	 * @param object $token_row Baris dari aksara_download_tokens.
	 * @return string|null
	 */
	private static function resource_label( $token_row ) {
		if ( Aksara_Download_Tokens_Repository::RESOURCE_FONT_STYLE === $token_row->resource_type ) {
			$style = Aksara_Font_Styles_Repository::get( $token_row->resource_id );
			return $style ? $style->style_name : null;
		}

		$title = get_the_title( $token_row->resource_id );
		return $title ? $title : null;
	}

	/**
	 * Render tab "Sertifikat Lisensi": daftar PDF sertifikat dari seluruh
	 * order milik user yang login.
	 */
	public static function render_certificates() {
		$order_ids = wc_get_orders( array(
			'customer' => get_current_user_id(),
			'return'   => 'ids',
			'limit'    => -1,
		) );

		$certificates = Aksara_License_Certificates_Repository::get_by_orders( $order_ids );
		?>
		<h2><?php esc_html_e( 'Sertifikat Lisensi', 'aksara-marketplace' ); ?></h2>

		<?php if ( empty( $certificates ) ) : ?>
			<p><?php esc_html_e( 'Belum ada sertifikat. Sertifikat dibuat otomatis untuk order yang berisi produk font.', 'aksara-marketplace' ); ?></p>
		<?php else : ?>
			<table class="woocommerce-table shop_table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Order', 'aksara-marketplace' ); ?></th>
						<th><?php esc_html_e( 'Tanggal', 'aksara-marketplace' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $certificates as $certificate ) : ?>
						<tr>
							<td>#<?php echo esc_html( $certificate->order_id ); ?></td>
							<td><?php echo esc_html( mysql2date( get_option( 'date_format' ), $certificate->created_at ) ); ?></td>
							<td>
								<a class="button" href="<?php echo esc_url( rest_url( 'aksara/v1/certificate/' . $certificate->order_id ) ); ?>"><?php esc_html_e( 'Unduh PDF', 'aksara-marketplace' ); ?></a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render tab "Wishlist".
	 */
	public static function render_wishlist() {
		$product_ids = Aksara_Wishlist_Repository::get_product_ids( get_current_user_id() );
		?>
		<h2><?php esc_html_e( 'Wishlist', 'aksara-marketplace' ); ?></h2>

		<?php if ( empty( $product_ids ) ) : ?>
			<p><?php esc_html_e( 'Belum ada produk di wishlist Anda. Klik ikon hati pada produk untuk menyimpannya di sini.', 'aksara-marketplace' ); ?></p>
			<?php return; ?>
		<?php endif; ?>

		<div class="asset-grid aksara-wishlist-grid">
			<?php foreach ( $product_ids as $product_id ) : ?>
				<?php
				$product = wc_get_product( $product_id );
				if ( ! $product ) {
					continue;
				}
				?>
				<div class="asset-card">
					<a href="<?php echo esc_url( get_permalink( $product_id ) ); ?>">
						<div class="asset-thumb">
							<?php echo $product->get_image( 'medium' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_image() sudah menghasilkan markup img yang aman. ?>
						</div>
						<div class="asset-info">
							<h4><?php echo esc_html( $product->get_name() ); ?></h4>
							<span><?php echo wp_kses_post( $product->get_price_html() ); ?></span>
						</div>
					</a>
					<?php aksara_wishlist_button( $product_id ); ?>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}
}
