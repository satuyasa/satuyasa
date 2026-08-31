<?php
/**
 * Fungsi yang memengaruhi tampilan/markup tema.
 *
 * @package Aksara
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
function aksara_body_classes( $classes ) {
	if ( is_singular() ) {
		$classes[] = 'singular';
	}
	return $classes;
}
add_filter( 'body_class', 'aksara_body_classes' );

/**
 * Perpendek excerpt.
 *
 * @param int $length Panjang default.
 * @return int
 */
function aksara_excerpt_length( $length ) {
	return 30;
}
add_filter( 'excerpt_length', 'aksara_excerpt_length' );

/**
 * Ganti tanda excerpt.
 *
 * @param string $more Tanda default.
 * @return string
 */
function aksara_excerpt_more( $more ) {
	return '&hellip;';
}
add_filter( 'excerpt_more', 'aksara_excerpt_more' );

/**
 * Ubah jumlah produk per baris grid WooCommerce agar sesuai CSS 4-kolom kita.
 *
 * @return int
 */
function aksara_loop_columns() {
	return 4;
}
add_filter( 'loop_shop_columns', 'aksara_loop_columns' );
