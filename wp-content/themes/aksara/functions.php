<?php
/**
 * Fungsi & definisi utama tema Aksara.
 *
 * @package Aksara
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AKSARA_THEME_VERSION', '0.1.0' );
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

	register_nav_menus( array(
		'primary' => __( 'Menu Utama', 'aksara' ),
		'footer_shop'  => __( 'Footer — Belanja', 'aksara' ),
		'footer_help'  => __( 'Footer — Bantuan', 'aksara' ),
		'footer_about' => __( 'Footer — Perusahaan', 'aksara' ),
	) );
}
add_action( 'after_setup_theme', 'aksara_setup' );

/**
 * Enqueue Google Fonts (Fraunces untuk heading/branding, Inter untuk UI chrome),
 * style.css, dan JS navigasi.
 *
 * PENTING: font Google di sini HANYA untuk chrome UI situs (judul, tombol, dst.) —
 * bukan font yang dijual. Font produk TIDAK PERNAH di-@font-face secara publik
 * dari tema; itu justru yang dicegah oleh seluruh sistem preview di PRD Bagian 4.3.
 */
function aksara_scripts() {
	wp_enqueue_style(
		'aksara-google-fonts',
		'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600&display=swap',
		array(),
		null
	);

	wp_enqueue_style( 'aksara-style', get_stylesheet_uri(), array(), AKSARA_THEME_VERSION );

	wp_enqueue_script( 'aksara-navigation', AKSARA_THEME_URI . '/assets/js/navigation.js', array(), AKSARA_THEME_VERSION, true );

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}
add_action( 'wp_enqueue_scripts', 'aksara_scripts' );

/**
 * Daftarkan area widget.
 */
function aksara_widgets_init() {
	register_sidebar( array(
		'name'          => __( 'Sidebar Blog', 'aksara' ),
		'id'            => 'sidebar-1',
		'before_widget' => '<div id="%1$s" class="widget %2$s">',
		'after_widget'  => '</div>',
		'before_title'  => '<h2 class="widget-title">',
		'after_title'   => '</h2>',
	) );
}
add_action( 'widgets_init', 'aksara_widgets_init' );

require AKSARA_THEME_DIR . '/inc/template-tags.php';
require AKSARA_THEME_DIR . '/inc/template-functions.php';
require AKSARA_THEME_DIR . '/inc/woocommerce-helpers.php';
require AKSARA_THEME_DIR . '/inc/seo.php';

/**
 * Fallback menu jika belum ada menu Utama yang diatur.
 */
function aksara_fallback_menu() {
	echo '<ul id="primary-menu" class="menu">';
	wp_list_pages( array( 'title_li' => '' ) );
	echo '</ul>';
}

/**
 * Lebar konten default.
 */
function aksara_content_width() {
	$GLOBALS['content_width'] = apply_filters( 'aksara_content_width', 820 );
}
add_action( 'after_setup_theme', 'aksara_content_width', 0 );
