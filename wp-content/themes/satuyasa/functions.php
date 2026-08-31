<?php
/**
 * Fungsi & definisi utama tema Satuyasa.
 *
 * @package Satuyasa
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SATUYASA_VERSION', '1.0.0' );
define( 'SATUYASA_DIR', get_template_directory() );
define( 'SATUYASA_URI', get_template_directory_uri() );

/**
 * Pengaturan dasar tema.
 */
function satuyasa_setup() {
	load_theme_textdomain( 'satuyasa', SATUYASA_DIR . '/languages' );

	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'custom-logo', array(
		'height'      => 80,
		'width'       => 240,
		'flex-height' => true,
		'flex-width'  => true,
	) );
	add_theme_support( 'html5', array(
		'search-form',
		'comment-form',
		'comment-list',
		'gallery',
		'caption',
		'style',
		'script',
		'navigation-widgets',
	) );
	add_theme_support( 'align-wide' );
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'editor-styles' );
	add_editor_style( 'style.css' );
	add_theme_support( 'responsive-embeds' );

	register_nav_menus( array(
		'primary' => __( 'Menu Utama', 'satuyasa' ),
		'footer'  => __( 'Menu Footer', 'satuyasa' ),
	) );
}
add_action( 'after_setup_theme', 'satuyasa_setup' );

/**
 * Lebar konten default (untuk oEmbed dsb).
 */
function satuyasa_content_width() {
	$GLOBALS['content_width'] = apply_filters( 'satuyasa_content_width', 800 );
}
add_action( 'after_setup_theme', 'satuyasa_content_width', 0 );

/**
 * Daftarkan area widget.
 */
function satuyasa_widgets_init() {
	register_sidebar( array(
		'name'          => __( 'Sidebar', 'satuyasa' ),
		'id'            => 'sidebar-1',
		'description'   => __( 'Tampil di halaman blog dan artikel.', 'satuyasa' ),
		'before_widget' => '<div id="%1$s" class="widget %2$s">',
		'after_widget'  => '</div>',
		'before_title'  => '<h2 class="widget-title">',
		'after_title'   => '</h2>',
	) );

	for ( $i = 1; $i <= 3; $i++ ) {
		register_sidebar( array(
			'name'          => sprintf( __( 'Footer Widget %d', 'satuyasa' ), $i ),
			'id'            => 'footer-' . $i,
			'description'   => __( 'Tampil pada footer situs.', 'satuyasa' ),
			'before_widget' => '<div id="%1$s" class="widget %2$s">',
			'after_widget'  => '</div>',
			'before_title'  => '<h2 class="widget-title">',
			'after_title'   => '</h2>',
		) );
	}
}
add_action( 'widgets_init', 'satuyasa_widgets_init' );

/**
 * Enqueue style & script.
 */
function satuyasa_scripts() {
	wp_enqueue_style( 'satuyasa-style', get_stylesheet_uri(), array(), SATUYASA_VERSION );
	wp_style_add_data( 'satuyasa-style', 'rtl', 'replace' );

	wp_enqueue_script( 'satuyasa-navigation', SATUYASA_URI . '/assets/js/navigation.js', array(), SATUYASA_VERSION, true );

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}
add_action( 'wp_enqueue_scripts', 'satuyasa_scripts' );

/**
 * File pendukung.
 */
require SATUYASA_DIR . '/inc/template-tags.php';
require SATUYASA_DIR . '/inc/template-functions.php';
require SATUYASA_DIR . '/inc/customizer.php';

/**
 * Fallback menu jika belum ada menu Utama yang diatur.
 */
function satuyasa_fallback_menu() {
	echo '<ul id="primary-menu" class="menu">';
	wp_list_pages( array(
		'title_li' => '',
	) );
	echo '</ul>';
}

/**
 * Ambil opsi dari plugin Satuyasa Toolkit dengan aman (tema tetap jalan tanpa plugin).
 *
 * @param string $key     Nama opsi.
 * @param string $default Nilai default.
 * @return string
 */
function satuyasa_get_toolkit_option( $key, $default = '' ) {
	if ( function_exists( 'satuyasa_toolkit_get_option' ) ) {
		return satuyasa_toolkit_get_option( $key, $default );
	}
	return $default;
}
