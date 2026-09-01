<?php
/**
 * Fungsi & definisi utama tema Aksara.
 *
 * Sejak 1.0.0 ini BLOCK THEME (FSE): struktur halaman ada di templates/*.html
 * dan parts/*.html, token desain di theme.json, dan bagian yang isinya query
 * jadi blok dinamis di inc/blocks.php. Template PHP versi classic sudah
 * dihapus — WordPress mengabaikannya begitu templates/ ada, jadi
 * meninggalkannya hanya akan menyesatkan pembaca berikutnya.
 *
 * @package Aksara
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AKSARA_THEME_VERSION', '1.0.4' );
define( 'AKSARA_THEME_DIR', get_template_directory() );
define( 'AKSARA_THEME_URI', get_template_directory_uri() );

/**
 * Pengaturan dasar tema.
 */
function aksara_setup() {
	load_theme_textdomain( 'aksara', AKSARA_THEME_DIR . '/languages' );

	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'custom-logo', array(
		'height'      => 60,
		'width'       => 200,
		'flex-height' => true,
		'flex-width'  => true,
	) );
	add_theme_support( 'html5', array(
		'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script',
	) );
	add_theme_support( 'woocommerce' );
	add_theme_support( 'wc-product-gallery-zoom' );
	add_theme_support( 'wc-product-gallery-lightbox' );
	add_theme_support( 'wc-product-gallery-slider' );

	// Consistent 3:2 product-preview crops. WordPress keeps responsive
	// variants so archives do not download the full 1820px source per card.
	add_image_size( 'aksara-preview-xl', 1820, 1214, true );
	add_image_size( 'aksara-preview-md', 910, 607, true );
	add_image_size( 'aksara-preview-sm', 600, 400, true );

	/*
	 * Tidak ada register_nav_menus() maupun register_sidebar() lagi: di block
	 * theme, menu dikelola lewat blok Navigation di parts/header.html dan
	 * parts/footer.html, dan widget digantikan blok biasa. Mendaftarkannya
	 * hanya akan memunculkan panel Menus/Widgets yang tidak berpengaruh
	 * apa pun terhadap tampilan.
	 */
}
add_action( 'after_setup_theme', 'aksara_setup' );

/** Match WooCommerce product image crops to Aksara's 3:2 preview system. */
function aksara_woocommerce_single_image_size( $size ) {
	return array( 'width' => 1820, 'height' => 1214, 'crop' => 1 );
}
add_filter( 'woocommerce_get_image_size_single', 'aksara_woocommerce_single_image_size' );

function aksara_woocommerce_thumbnail_image_size( $size ) {
	return array( 'width' => 910, 'height' => 607, 'crop' => 1 );
}
add_filter( 'woocommerce_get_image_size_thumbnail', 'aksara_woocommerce_thumbnail_image_size' );

/**
 * Enqueue webfont UI, style.css, dan JS navigasi.
 *
 * Kenapa cuma SATU keluarga (Work Sans), bukan dua seperti sebelumnya:
 * DESIGN.md menetapkan Sterling sebagai satu-satunya suara UI, dengan
 * Work Sans sebagai "silent failsafe" — dan secara eksplisit melarang
 * memakai dua keluarga dekoratif. Berkas Sterling milik foundry dan tidak
 * ikut dalam repo ini, jadi yang benar-benar dimuat adalah failsafe-nya.
 * Begitu berkas Sterling dipasang (mis. @font-face di child theme), ia
 * otomatis menang karena disebut lebih dulu di --font-ui (style.css) —
 * tanpa perlu mengubah fungsi ini.
 *
 * Pasangan Fraunces + Inter versi lama sudah dilepas: keduanya milik
 * bahasa visual mockup hangat yang digantikan DESIGN.md, dan memuat dua
 * keluarga display yang tak terpakai berarti dua request font sia-sia.
 *
 * PENTING: webfont di sini HANYA untuk chrome UI situs — bukan font yang
 * dijual. Font produk TIDAK PERNAH di-@font-face secara publik dari tema;
 * itu justru yang dicegah oleh seluruh sistem preview di PRD Bagian 4.3.
 */
function aksara_scripts() {
	wp_enqueue_style(
		'aksara-google-fonts',
		'https://fonts.googleapis.com/css2?family=Work+Sans:wght@300;400;500&display=swap',
		array(),
		null
	);

	wp_enqueue_style( 'aksara-style', get_stylesheet_uri(), array(), AKSARA_THEME_VERSION );

	// navigation.js sudah dilepas: ia hanya menangani .menu-toggle milik
	// header PHP versi classic. Di block theme, blok Navigation membawa
	// overlay & tombol hamburger-nya sendiri, jadi skrip itu tidak akan
	// pernah menemukan elemennya — memuatnya berarti satu request sia-sia
	// di setiap halaman.

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}
add_action( 'wp_enqueue_scripts', 'aksara_scripts' );

require AKSARA_THEME_DIR . '/inc/template-functions.php';
require AKSARA_THEME_DIR . '/inc/woocommerce-helpers.php';
require AKSARA_THEME_DIR . '/inc/authentype-integration.php';
require AKSARA_THEME_DIR . '/inc/seo.php';
require AKSARA_THEME_DIR . '/inc/blocks.php';

/**
 * Kategori sendiri di inserter, supaya blok tema tidak tercecer di antara
 * blok core dan sulit ditemukan.
 *
 * @param array $categories Kategori yang sudah ada.
 * @return array
 */
function aksara_block_category( $categories ) {
	array_unshift(
		$categories,
		array(
			'slug'  => 'aksara',
			'title' => __( 'Aksara', 'aksara' ),
		)
	);
	return $categories;
}
add_filter( 'block_categories_all', 'aksara_block_category' );

/**
 * Daftarkan kategori PATTERN 'aksara'.
 *
 * Kategori blok (di atas) dan kategori pattern adalah dua registry terpisah.
 * patterns/section.php dan patterns/trust.php sama-sama mendeklarasikan
 * "Categories: aksara"; tanpa pendaftaran ini kategorinya tidak ada, jadi
 * kedua pattern tidak pernah muncul sebagai tab tersendiri di inserter.
 */
function aksara_register_pattern_category() {
	if ( ! function_exists( 'register_block_pattern_category' ) ) {
		return;
	}
	register_block_pattern_category( 'aksara', array(
		'label' => __( 'Aksara', 'aksara' ),
	) );
}
add_action( 'init', 'aksara_register_pattern_category' );

/** Show a clear setup warning instead of silently rendering an empty catalog. */
function aksara_dependency_notice() {
	if ( ! current_user_can( 'activate_plugins' ) || ! function_exists( 'get_current_screen' ) ) {
		return;
	}
	if ( ! class_exists( 'WooCommerce' ) ) {
		echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'Aksara requires WooCommerce.', 'aksara' ) . '</strong></p></div>';
		return;
	}
	if ( ! defined( 'AUTHENTYPE_SPECIMEN_VERSION' ) ) {
		echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'Aksara font pages require Authentype Font Specimen Commerce.', 'aksara' ) . '</strong> ' . esc_html__( 'Activate Authentype, then save Settings > Permalinks once.', 'aksara' ) . '</p></div>';
	}
}
add_action( 'admin_notices', 'aksara_dependency_notice' );

/**
 * Lebar konten default.
 */
function aksara_content_width() {
	$GLOBALS['content_width'] = apply_filters( 'aksara_content_width', 820 );
}
add_action( 'after_setup_theme', 'aksara_content_width', 0 );
