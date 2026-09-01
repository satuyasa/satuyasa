<?php
/**
 * Blok: aksi header (akun + keranjang dengan jumlah item).
 *
 * Blok ini dinamis karena jumlah item keranjang dan status login berbeda
 * per pengunjung, jadi isinya tidak bisa ditulis statis di parts/header.html.
 *
 * PENTING soal cache: menjadi blok dinamis TIDAK membuat keluarannya lolos
 * dari full-page cache. Blok dinamis tetap dirender di server dan hasilnya
 * ikut tersimpan di HTML yang di-cache. Yang benar-benar menjaga angka ini
 * tetap benar adalah cookie WooCommerce (woocommerce_items_in_cart dan
 * wp_woocommerce_session_*): page cache wajib dikonfigurasi untuk mem-bypass
 * pengunjung yang membawa cookie tersebut - itu default di WP Rocket,
 * LiteSpeed, dan W3TC, tapi pada Varnish/Nginx dan Cloudflare aturannya
 * harus ditulis sendiri. Tanpa itu, pengunjung dengan keranjang kosong bisa
 * melihat angka milik pengunjung lain.
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
	<a href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>"><?php echo esc_html( is_user_logged_in() ? __( 'My account', 'aksara' ) : __( 'Sign in', 'aksara' ) ); ?></a>
	<a href="<?php echo esc_url( wc_get_cart_url() ); ?>">
		<?php esc_html_e( 'Cart', 'aksara' ); ?>
		<span class="cart-count"><?php echo esc_html( $count ); ?></span>
	</a>
</div>
