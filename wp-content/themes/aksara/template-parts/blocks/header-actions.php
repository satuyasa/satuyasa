<?php
/**
 * Blok: aksi header (masuk + keranjang dengan jumlah item).
 *
 * Jumlah item keranjang wajib dinamis dan tidak boleh ikut ter-cache
 * halaman penuh, jadi ini tidak bisa jadi blok statis di parts/header.html.
 *
 * @package Aksara
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WooCommerce' ) ) {
	return;
}

$count = ( function_exists( 'WC' ) && WC()->cart ) ? WC()->cart->get_cart_contents_count() : 0;
?>
<div class="header-actions">
	<a href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>"><?php esc_html_e( 'Sign in', 'aksara' ); ?></a>
	<a href="<?php echo esc_url( wc_get_cart_url() ); ?>">
		<?php esc_html_e( 'Cart', 'aksara' ); ?>
		<span class="cart-count"><?php echo esc_html( $count ); ?></span>
	</a>
</div>
