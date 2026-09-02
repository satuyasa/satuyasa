<?php
/**
 * Plugin Name: Aksara Marketplace
 * Plugin URI: https://github.com/satuyasa/satuyasa
 * Description: Aksara storefront companion for WooCommerce. Authentype owns font generation, previews, pricing, variations and delivery; Aksara manages Canva Templates, Canva Elements, wishlists and shared storefront features.
 * Version: 0.8.3
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * WC requires at least: 8.0
 * Author: Aksara
 * Author URI: https://github.com/satuyasa
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: aksara-marketplace
 *
 * @package Aksara_Marketplace
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Akses langsung tidak diizinkan.
}

define( 'AKSARA_MARKETPLACE_VERSION', '0.8.3' );
define( 'AKSARA_MARKETPLACE_DIR', plugin_dir_path( __FILE__ ) );
define( 'AKSARA_MARKETPLACE_URL', plugin_dir_url( __FILE__ ) );
define( 'AKSARA_MARKETPLACE_FILE', __FILE__ );

/**
 * Select the font commerce owner. When Authentype is active it owns font
 * catalog, pricing, preview, cart and delivery; Aksara keeps Canva commerce.
 */
function aksara_marketplace_font_engine() {
	if ( defined( 'AKSARA_FONT_ENGINE' ) ) {
		return 'authentype' === strtolower( (string) AKSARA_FONT_ENGINE ) ? 'authentype' : 'aksara';
	}
	// Auto-select Authentype only when its complete plugin bootstrap is active.
	// Otherwise keep the legacy engine available instead of silently hiding
	// every existing Aksara font product during a dependency outage.
	return defined( 'AUTHENTYPE_SPECIMEN_VERSION' ) ? 'authentype' : 'aksara';
}

function aksara_marketplace_uses_authentype() {
	return 'authentype' === aksara_marketplace_font_engine();
}

/**
 * Deklarasikan kompatibilitas dengan HPOS (High-Performance Order Storage)
 * WooCommerce. Plugin ini tidak query langsung ke tabel order lama, jadi aman.
 */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				__FILE__,
				true
			);
		}
	}
);

/**
 * Muat seluruh file class. Dipanggil setelah WooCommerce dipastikan aktif.
 */
function aksara_marketplace_load_includes() {
	require_once AKSARA_MARKETPLACE_DIR . 'includes/db/class-db-installer.php';
	require_once AKSARA_MARKETPLACE_DIR . 'includes/db/class-font-styles-repository.php';
	require_once AKSARA_MARKETPLACE_DIR . 'includes/db/class-font-licenses-repository.php';
	require_once AKSARA_MARKETPLACE_DIR . 'includes/class-file-storage.php';
	require_once AKSARA_MARKETPLACE_DIR . 'includes/product-types/class-product-type-registrar.php';
	require_once AKSARA_MARKETPLACE_DIR . 'includes/admin/class-admin-ui.php';
	require_once AKSARA_MARKETPLACE_DIR . 'includes/admin/class-canva-info-metabox.php';
	require_once AKSARA_MARKETPLACE_DIR . 'includes/admin/class-font-styles-metabox.php';
	require_once AKSARA_MARKETPLACE_DIR . 'includes/admin/class-license-admin.php';
	require_once AKSARA_MARKETPLACE_DIR . 'includes/class-preview-service-client.php';
	require_once AKSARA_MARKETPLACE_DIR . 'includes/class-cart-handler.php';
	require_once AKSARA_MARKETPLACE_DIR . 'includes/class-rest-controller.php';
	require_once AKSARA_MARKETPLACE_DIR . 'includes/class-cleanup-jobs.php';
	require_once AKSARA_MARKETPLACE_DIR . 'includes/db/class-download-tokens-repository.php';
	require_once AKSARA_MARKETPLACE_DIR . 'includes/db/class-license-certificates-repository.php';
	require_once AKSARA_MARKETPLACE_DIR . 'includes/db/class-wishlist-repository.php';
	require_once AKSARA_MARKETPLACE_DIR . 'includes/class-pdf-writer.php';
	require_once AKSARA_MARKETPLACE_DIR . 'includes/class-invoice-generator.php';
	require_once AKSARA_MARKETPLACE_DIR . 'includes/class-download-manager.php';
	require_once AKSARA_MARKETPLACE_DIR . 'includes/class-account-endpoints.php';
	require_once AKSARA_MARKETPLACE_DIR . 'includes/class-order-emails.php';
	require_once AKSARA_MARKETPLACE_DIR . 'includes/admin/class-dashboard-widget.php';
	require_once AKSARA_MARKETPLACE_DIR . 'includes/class-error-logger.php';
	require_once AKSARA_MARKETPLACE_DIR . 'includes/class-specimen-image.php';
	require_once AKSARA_MARKETPLACE_DIR . 'includes/admin/class-service-health.php';
}

/**
 * Inisialisasi plugin setelah semua plugin lain dimuat, dengan pengecekan
 * dependency WooCommerce.
 */
function aksara_marketplace_init() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'aksara_marketplace_missing_woocommerce_notice' );
		return;
	}

	aksara_marketplace_load_includes();
	if ( aksara_marketplace_uses_authentype() ) {
		add_filter( 'woocommerce_product_is_visible', 'aksara_marketplace_hide_legacy_font_products', 10, 2 );
		add_action( 'admin_notices', 'aksara_marketplace_authentype_mode_notice' );
	}

	// Admin_UI lebih dulu: ia memasang enctype multipart pada form editor
	// post, yang tanpa itu bulk upload di metabox Font Styles tidak akan
	// pernah menerima berkas.
	Aksara_Admin_UI::init();
	Aksara_Product_Type_Registrar::init();
	Aksara_Canva_Info_Metabox::init();
	if ( ! aksara_marketplace_uses_authentype() ) {
		Aksara_Font_Styles_Metabox::init();
	}
	if ( ! aksara_marketplace_uses_authentype() ) {
		Aksara_License_Admin::init();
	}
	if ( ! aksara_marketplace_uses_authentype() ) {
		Aksara_Cart_Handler::init();
	}
	Aksara_Rest_Controller::init();
	Aksara_Cleanup_Jobs::init();
	Aksara_Download_Manager::init();
	Aksara_Account_Endpoints::init();
	Aksara_Order_Emails::init();
	if ( ! aksara_marketplace_uses_authentype() ) {
		Aksara_Dashboard_Widget::init();
	}
	Aksara_Error_Logger::init();
	if ( ! aksara_marketplace_uses_authentype() ) {
		Aksara_Service_Health::init();
	}

	Aksara_DB_Installer::maybe_upgrade();
}
add_action( 'plugins_loaded', 'aksara_marketplace_init' );

/** Nginx ignores the protection files written inside uploads. */
function aksara_marketplace_nginx_storage_notice() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) return;
	$software = isset( $_SERVER['SERVER_SOFTWARE'] ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) ) : '';
	if ( false === strpos( $software, 'nginx' ) ) return;
	echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'Verify private-download protection on Nginx.', 'aksara-marketplace' ) . '</strong> ';
	echo esc_html__( 'Nginx does not read .htaccess. Deny direct HTTP access to wp-content/uploads/aksara-private and use Force Downloads or X-Accel-Redirect before selling files.', 'aksara-marketplace' );
	echo '</p></div>';
}
add_action( 'admin_notices', 'aksara_marketplace_nginx_storage_notice' );

function aksara_marketplace_hide_legacy_font_products( $visible, $product_id ) {
	return has_term( 'font', 'product_type', $product_id ) ? false : $visible;
}

function aksara_marketplace_authentype_mode_notice() {
	if ( ! current_user_can( 'manage_woocommerce' ) || ! function_exists( 'get_current_screen' ) ) {
		return;
	}
	$screen = get_current_screen();
	if ( ! $screen || ! in_array( $screen->id, array( 'dashboard', 'plugins', 'edit-product', 'product' ), true ) ) {
		return;
	}
	if ( ! defined( 'AUTHENTYPE_SPECIMEN_VERSION' ) ) {
		echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'Authentype is required for font products.', 'aksara-marketplace' ) . '</strong> ' . esc_html__( 'Install and activate Authentype Font Specimen Commerce. Aksara font commerce remains disabled to prevent two competing font engines.', 'aksara-marketplace' ) . '</p></div>';
		return;
	}
	$font_admin_url = admin_url( 'edit.php?post_type=ath_font' );
	echo '<div class="notice notice-info"><p><strong>' . esc_html__( 'Aksara is using Authentype mode.', 'aksara-marketplace' ) . '</strong> ' . esc_html__( 'Create and edit fonts only in Authentype. It owns previews, prices, variations and delivery; Aksara manages Canva products and shared storefront features.', 'aksara-marketplace' ) . ' <a href="' . esc_url( $font_admin_url ) . '">' . esc_html__( 'Open Font Products', 'aksara-marketplace' ) . '</a></p></div>';
}

/**
 * Notice admin jika WooCommerce belum aktif.
 */
function aksara_marketplace_missing_woocommerce_notice() {
	echo '<div class="notice notice-error"><p>' .
		esc_html__( 'Aksara Marketplace requires the WooCommerce plugin to be active.', 'aksara-marketplace' ) .
		'</p></div>';
}

/**
 * Muat CSS di sisi depan, dan DAFTARKAN (belum enqueue) script typing tool.
 *
 * Typing tool baru benar-benar di-enqueue+localize oleh
 * Aksara_Cart_Handler::render_add_to_cart_form() saat produk font yang
 * sedang dilihat memang punya style — mendaftarkannya di sini (bukan di
 * sana) supaya handle-nya sudah dikenal WordPress sebelum dipanggil dari
 * dalam loop template WooCommerce.
 */
function aksara_marketplace_enqueue_assets() {
	if ( ! function_exists( 'is_product' ) ) {
		return;
	}

	wp_enqueue_style(
		'aksara-marketplace',
		AKSARA_MARKETPLACE_URL . 'assets/css/aksara-marketplace.css',
		array(),
		AKSARA_MARKETPLACE_VERSION
	);

	if ( ! aksara_marketplace_uses_authentype() ) {
		wp_register_script(
			'aksara-font-typing-tool',
			AKSARA_MARKETPLACE_URL . 'assets/js/font-typing-tool.js',
			array(),
			AKSARA_MARKETPLACE_VERSION,
			true
		);
	}

	// Wishlist adalah fitur khusus akun (lihat class-account-endpoints.php) —
	// tombolnya cuma dirender untuk user yang login (lihat aksara_wishlist_button()),
	// jadi script-nya juga cukup dimuat untuk mereka saja.
	if ( is_user_logged_in() ) {
		wp_enqueue_script(
			'aksara-wishlist',
			AKSARA_MARKETPLACE_URL . 'assets/js/wishlist.js',
			array(),
			AKSARA_MARKETPLACE_VERSION,
			true
		);
		// Tidak ada 'i18n' di sini: label tombol kini tetap ("Simpan ke
		// wishlist") dan status disampaikan lewat aria-pressed + bentuk
		// glif, jadi JS tidak lagi menukar teks label saat di-toggle.
		wp_localize_script( 'aksara-wishlist', 'aksaraWishlist', array(
			'restUrl' => esc_url_raw( rest_url( 'aksara/v1' ) ),
			'nonce'   => wp_create_nonce( 'wp_rest' ),
		) );
	}
}
add_action( 'wp_enqueue_scripts', 'aksara_marketplace_enqueue_assets' );

/**
 * Template tag untuk tema: cetak tombol wishlist (ikon hati) untuk sebuah produk.
 * Tidak menampilkan apa pun kalau user belum login (wishlist butuh akun).
 *
 * @param int $product_id ID produk.
 */
function aksara_wishlist_button( $product_id ) {
	if ( ! is_user_logged_in() || ! class_exists( 'Aksara_Wishlist_Repository' ) ) {
		return;
	}

	$is_active = Aksara_Wishlist_Repository::has( get_current_user_id(), $product_id );

	/*
	 * Glifnya BERBEDA antar status (hati penuh vs hati kosong), bukan cuma
	 * warnanya. Sebelumnya keduanya memakai &hearts; dan yang membedakan
	 * hanya warna merah dari CSS — begitu tema pindah ke palet monokrom
	 * (lihat DESIGN.md), statusnya jadi sama sekali tidak terlihat. Bentuk
	 * juga lebih baik daripada warna untuk pengguna buta warna, jadi ini
	 * perbaikan aksesibilitas sekaligus, bukan cuma penyesuaian gaya.
	 *
	 * aria-pressed menyampaikan status yang sama ke screen reader, jadi
	 * label tombol tidak perlu berubah-ubah untuk menjelaskannya.
	 */
	printf(
		'<button type="button" class="aksara-wishlist-toggle%1$s" data-product-id="%2$d" aria-pressed="%3$s" aria-label="%4$s">%5$s</button>',
		$is_active ? ' is-active' : '',
		(int) $product_id,
		$is_active ? 'true' : 'false',
		esc_attr__( 'Save to wishlist', 'aksara-marketplace' ),
		$is_active ? '&hearts;' : '&#9825;'
	);
}

/**
 * Template tag untuk tema: cetak specimen sebuah produk font sebagai
 * GAMBAR hasil render server (bukan memuat file font ke browser).
 *
 * Aman dipakai di listing publik: yang dikirim ke pengunjung cuma piksel,
 * file fontnya tidak pernah meninggalkan server (lihat penjelasan lengkap
 * di class-specimen-image.php). Kalau specimen tidak bisa dibuat — style
 * diunggah dalam format .woff2 yang tidak dibaca FreeType, GD tidak
 * tersedia, atau produk belum punya style — fungsi ini mengembalikan
 * string kosong supaya pemanggil bisa mundur ke teks biasa.
 *
 * @param int         $product_id ID produk font.
 * @param string|null $text       Teks yang dirender (default: judul produk).
 * @param int         $size       Tinggi tampilan dalam piksel.
 * @return string HTML <img>, atau string kosong.
 */
function aksara_font_specimen( $product_id, $text = null, $size = 40 ) {
	if ( ! class_exists( 'Aksara_Specimen_Image' ) || ! class_exists( 'Aksara_Font_Styles_Repository' ) ) {
		return '';
	}

	$style = Aksara_Font_Styles_Repository::get_representative( $product_id );
	if ( ! $style ) {
		return '';
	}

	if ( null === $text ) {
		$text = get_the_title( $product_id );
	}

	return Aksara_Specimen_Image::get_img_tag( $style, $text, $size );
}

/**
 * Saat aktivasi: pastikan WooCommerce aktif, buat tabel, siapkan folder privat.
 */
function aksara_marketplace_activate() {
	require_once AKSARA_MARKETPLACE_DIR . 'includes/db/class-db-installer.php';
	require_once AKSARA_MARKETPLACE_DIR . 'includes/class-file-storage.php';

	Aksara_DB_Installer::install();
	Aksara_File_Storage::ensure_protected_dir( 'fonts' );
	Aksara_File_Storage::ensure_protected_dir( 'templates' );
	Aksara_File_Storage::ensure_protected_dir( 'certificates' );

	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'aksara_marketplace_activate' );

/**
 * Saat deaktivasi: siram ulang rewrite rules & batalkan jadwal cron.
 * Data & tabel TIDAK dihapus (uninstall data berbahaya untuk dilakukan
 * otomatis di sini).
 */
function aksara_marketplace_deactivate() {
	require_once AKSARA_MARKETPLACE_DIR . 'includes/class-cleanup-jobs.php';
	Aksara_Cleanup_Jobs::unschedule();

	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'aksara_marketplace_deactivate' );
