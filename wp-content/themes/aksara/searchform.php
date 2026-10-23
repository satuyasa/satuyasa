<?php
/**
 * Formulir pencarian.
 *
 * Tanpa berkas ini WordPress mencetak formulir bawaannya, yang membawa
 * markup dan label generik di luar sistem visual tema — dan ia muncul di
 * tempat yang tidak terduga: hasil pencarian kosong, halaman 404, dan widget
 * mana pun yang memanggil get_search_form().
 *
 * Bentuknya sengaja meminjam .hero-search yang sudah ada di Home, bukan gaya
 * baru: keduanya mengerjakan hal yang sama, dan satu-satunya alasan mereka
 * pernah berbeda adalah karena yang satu belum pernah dibuat.
 *
 * Label dibungkus .screen-reader-text, bukan dihilangkan. Placeholder BUKAN
 * pengganti label: ia hilang begitu orang mulai mengetik, dan sebagian
 * pembaca layar tidak membacanya sama sekali.
 *
 * @package Aksara
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$aksara_search_id = wp_unique_id( 'search-field-' );
?>
<form class="hero-search search-form" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label class="screen-reader-text" for="<?php echo esc_attr( $aksara_search_id ); ?>"><?php esc_html_e( 'Search this site', 'aksara' ); ?></label>
	<input id="<?php echo esc_attr( $aksara_search_id ); ?>" type="search" name="s"
		value="<?php echo esc_attr( get_search_query() ); ?>"
		placeholder="<?php esc_attr_e( 'Search fonts, templates, articles…', 'aksara' ); ?>">
	<button type="submit"><?php esc_html_e( 'Search', 'aksara' ); ?></button>
</form>
