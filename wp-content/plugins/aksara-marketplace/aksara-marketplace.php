<?php
/**
 * Plugin Name: Aksara Marketplace
 * Plugin URI: https://github.com/satuyasa/satuyasa
 * Description: Marketplace WooCommerce untuk Font (per-style, lisensi bertingkat), Canva Template, dan Canva Element. Menambahkan product type kustom, manajemen style font, matriks harga lisensi, dan alur beli dasar.
 * Version: 0.1.0 (Fase 1 — fondasi produk)
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

define( 'AKSARA_MARKETPLACE_VERSION', '0.1.0' );
define( 'AKSARA_MARKETPLACE_DIR', plugin_dir_path( __FILE__ ) );
define( 'AKSARA_MARKETPLACE_URL', plugin_dir_url( __FILE__ ) );
define( 'AKSARA_MARKETPLACE_FILE', __FILE__ );

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
	require_once AKSARA_MARKETPLACE_DIR . 'includes/admin/class-canva-info-metabox.php';
	require_once AKSARA_MARKETPLACE_DIR . 'includes/admin/class-font-styles-metabox.php';
	require_once AKSARA_MARKETPLACE_DIR . 'includes/admin/class-license-admin.php';
	require_once AKSARA_MARKETPLACE_DIR . 'includes/class-cart-handler.php';
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

	Aksara_Product_Type_Registrar::init();
	Aksara_Canva_Info_Metabox::init();
	Aksara_Font_Styles_Metabox::init();
	Aksara_License_Admin::init();
	Aksara_Cart_Handler::init();

	Aksara_DB_Installer::maybe_upgrade();
}
add_action( 'plugins_loaded', 'aksara_marketplace_init' );

/**
 * Notice admin jika WooCommerce belum aktif.
 */
function aksara_marketplace_missing_woocommerce_notice() {
	echo '<div class="notice notice-error"><p>' .
		esc_html__( 'Aksara Marketplace membutuhkan plugin WooCommerce yang aktif.', 'aksara-marketplace' ) .
		'</p></div>';
}

/**
 * Muat aset CSS/JS di sisi depan, hanya di halaman yang membutuhkan.
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

	if ( is_product() ) {
		global $product;
		if ( $product instanceof WC_Product_Font ) {
			wp_enqueue_script(
				'aksara-font-purchase-form',
				AKSARA_MARKETPLACE_URL . 'assets/js/font-purchase-form.js',
				array(),
				AKSARA_MARKETPLACE_VERSION,
				true
			);
		}
	}
}
add_action( 'wp_enqueue_scripts', 'aksara_marketplace_enqueue_assets' );

/**
 * Saat aktivasi: pastikan WooCommerce aktif, buat tabel, siapkan folder privat.
 */
function aksara_marketplace_activate() {
	require_once AKSARA_MARKETPLACE_DIR . 'includes/db/class-db-installer.php';
	require_once AKSARA_MARKETPLACE_DIR . 'includes/class-file-storage.php';

	Aksara_DB_Installer::install();
	Aksara_File_Storage::ensure_protected_dir( 'fonts' );
	Aksara_File_Storage::ensure_protected_dir( 'templates' );

	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'aksara_marketplace_activate' );

/**
 * Saat deaktivasi: siram ulang rewrite rules. Data & tabel TIDAK dihapus
 * (uninstall data berbahaya untuk dilakukan otomatis di sini).
 */
function aksara_marketplace_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'aksara_marketplace_deactivate' );
