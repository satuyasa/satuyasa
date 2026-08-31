<?php
/**
 * Plugin Name: Satuyasa Toolkit
 * Plugin URI: https://github.com/satuyasa/satuyasa
 * Description: Menambahkan Custom Post Type Portofolio, shortcode formulir kontak, dan pengaturan tautan sosial media. Dirancang untuk tema Satuyasa, namun tetap berfungsi dengan tema WordPress lain.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Satuyasa
 * Author URI: https://github.com/satuyasa
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: satuyasa-toolkit
 *
 * @package Satuyasa_Toolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Akses langsung tidak diizinkan.
}

define( 'SATUYASA_TOOLKIT_VERSION', '1.0.0' );
define( 'SATUYASA_TOOLKIT_DIR', plugin_dir_path( __FILE__ ) );
define( 'SATUYASA_TOOLKIT_URL', plugin_dir_url( __FILE__ ) );
define( 'SATUYASA_TOOLKIT_FILE', __FILE__ );

require_once SATUYASA_TOOLKIT_DIR . 'includes/class-satuyasa-cpt.php';
require_once SATUYASA_TOOLKIT_DIR . 'includes/class-satuyasa-metaboxes.php';
require_once SATUYASA_TOOLKIT_DIR . 'includes/class-satuyasa-shortcodes.php';
require_once SATUYASA_TOOLKIT_DIR . 'includes/class-satuyasa-settings.php';

/**
 * Inisialisasi seluruh modul plugin.
 */
function satuyasa_toolkit_init() {
	Satuyasa_CPT::init();
	Satuyasa_Metaboxes::init();
	Satuyasa_Shortcodes::init();
	Satuyasa_Settings::init();
}
add_action( 'plugins_loaded', 'satuyasa_toolkit_init' );

/**
 * Muat aset CSS di sisi depan.
 */
function satuyasa_toolkit_enqueue_assets() {
	wp_enqueue_style(
		'satuyasa-toolkit',
		SATUYASA_TOOLKIT_URL . 'assets/css/satuyasa-toolkit.css',
		array(),
		SATUYASA_TOOLKIT_VERSION
	);
}
add_action( 'wp_enqueue_scripts', 'satuyasa_toolkit_enqueue_assets' );

/**
 * Saat plugin diaktifkan: daftarkan CPT lalu siram (flush) rewrite rules.
 */
function satuyasa_toolkit_activate() {
	Satuyasa_CPT::register();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'satuyasa_toolkit_activate' );

/**
 * Saat plugin dinonaktifkan: siram ulang rewrite rules.
 */
function satuyasa_toolkit_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'satuyasa_toolkit_deactivate' );
