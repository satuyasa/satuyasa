<?php
/**
 * Ajakan "Explore the font library" di footer.
 *
 * Hanya tampil di konteks editorial (blog, post tunggal, arsip taksonomi):
 * di halaman toko ajakannya justru menjauhkan pengunjung dari produk yang
 * sedang dilihat. Syarat itu dipertahankan APA ADANYA dari footer.php lama —
 * memindahkannya ke sini tidak boleh sekaligus mengubah kapan ia muncul.
 *
 * @package Aksara
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! ( is_home() || is_singular( 'post' ) || is_category() || is_tag() || is_date() || is_author() ) ) {
	return;
}
?>
<div class="editorial-footer-cta"><p><?php esc_html_e( 'More ideas, type and independent design.', 'aksara' ); ?></p><a href="<?php echo esc_url( get_post_type_archive_link( 'ath_font' ) ?: home_url( '/' ) ); ?>"><?php esc_html_e( 'Explore the font library', 'aksara' ); ?> <span aria-hidden="true">&#8599;</span></a></div>
