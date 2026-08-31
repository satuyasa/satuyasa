<?php
/**
 * Fungsi yang memengaruhi tampilan/markup tema.
 *
 * @package Satuyasa
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tambahkan class body kustom.
 *
 * @param array $classes Daftar class body.
 * @return array
 */
function satuyasa_body_classes( $classes ) {
	if ( ! is_active_sidebar( 'sidebar-1' ) ) {
		$classes[] = 'no-sidebar';
	}

	if ( is_singular() ) {
		$classes[] = 'singular';
	}

	return $classes;
}
add_filter( 'body_class', 'satuyasa_body_classes' );

/**
 * Perpendek excerpt otomatis.
 *
 * @param int $length Panjang excerpt default.
 * @return int
 */
function satuyasa_excerpt_length( $length ) {
	return 30;
}
add_filter( 'excerpt_length', 'satuyasa_excerpt_length' );

/**
 * Ganti tanda excerpt "[...]" menjadi elipsis dengan tautan.
 *
 * @param string $more Tanda excerpt default.
 * @return string
 */
function satuyasa_excerpt_more( $more ) {
	return '&hellip;';
}
add_filter( 'excerpt_more', 'satuyasa_excerpt_more' );
