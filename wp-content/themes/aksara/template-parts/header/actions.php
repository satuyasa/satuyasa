<?php
/**
 * Aksi header: akun dan keranjang.
 *
 * PEMBUNGKUS .header-actions SELALU DICETAK, walau WooCommerce nonaktif dan
 * isinya kosong. Itu diambil apa adanya dari header.php lama, dan bukan
 * kebetulan: .site-header-inner memakai justify-content: space-between, jadi
 * menghilangkan anak ketiga akan menggeser posisi branding dan navigasi.
 *
 * Draf pertama part ini melakukan `return` lebih awal saat Woo nonaktif —
 * kelihatan lebih rapi, tapi mengubah keluaran. Ketahuan oleh perbandingan
 * keluaran sebelum-sesudah. Kalau div kosong itu memang ingin dihapus, itu
 * keputusan desain tersendiri, bukan efek samping refactor.
 *
 * Catatan cache: jumlah item keranjang berbeda per pengunjung, jadi blok ini
 * tidak boleh dianggap statis oleh full-page cache. Yang menjaganya benar
 * adalah cookie WooCommerce (woocommerce_items_in_cart,
 * wp_woocommerce_session_*) yang membuat cache di-bypass — default di WP
 * Rocket, LiteSpeed dan W3TC, tapi di Varnish/Nginx dan Cloudflare aturannya
 * harus ditulis sendiri.
 *
 * @package Aksara
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="header-actions">
	<?php if ( class_exists( 'WooCommerce' ) ) : ?>
		<a href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>"><?php esc_html_e( 'Sign in', 'aksara' ); ?></a>
		<a href="<?php echo esc_url( wc_get_cart_url() ); ?>">
			<?php esc_html_e( 'Cart', 'aksara' ); ?>
			<span class="cart-count"><?php echo esc_html( WC()->cart ? WC()->cart->get_cart_contents_count() : 0 ); ?></span>
		</a>
	<?php endif; ?>
</div>
