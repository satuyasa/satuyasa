<?php
/**
 * Ajakan "Explore the font library" di footer.
 *
 * Bawaannya masih persis seperti sebelumnya: hanya tampil di konteks editorial
 * (blog, post tunggal, arsip taksonomi), karena di halaman toko ajakan ini
 * justru menjauhkan pengunjung dari produk yang sedang dilihat. Bedanya kini
 * syarat itu bisa dilonggarkan atau dimatikan dari Customizer — dan pilihan
 * bawaannya tetap 'editorial', jadi tidak ada situs yang berubah sendiri.
 *
 * Teks dan label tautannya juga bisa diubah, tapi PANAHNYA TIDAK. Panah itu
 * bagian dari bentuk tombol, bukan kalimat; membiarkannya diketik admin berarti
 * membiarkan tombol ini kehilangan bentuknya.
 *
 * @package Aksara
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$aksara_cta_scope = aksara_mod( 'aksara_footer_cta_scope' );

if ( 'off' === $aksara_cta_scope ) {
	return;
}

if ( 'all' !== $aksara_cta_scope
	&& ! ( is_home() || is_singular( 'post' ) || is_category() || is_tag() || is_date() || is_author() ) ) {
	return;
}

$aksara_cta_text  = aksara_mod( 'aksara_footer_cta_text' );
$aksara_cta_label = aksara_mod( 'aksara_footer_cta_label' );

// Kosongkan salah satunya di Customizer dan blok ini hilang seluruhnya. Sebuah
// tombol tanpa label, atau kalimat ajakan tanpa tombol, bukan versi lebih
// ringkas dari komponen ini — itu komponen yang rusak.
if ( '' === trim( $aksara_cta_text ) || '' === trim( $aksara_cta_label ) ) {
	return;
}

$aksara_cta_url = aksara_mod( 'aksara_footer_cta_url' );
if ( '' === $aksara_cta_url ) {
	$aksara_cta_url = get_post_type_archive_link( 'ath_font' ) ?: home_url( '/' );
}
?>
<div class="editorial-footer-cta"><p><?php echo esc_html( $aksara_cta_text ); ?></p><a href="<?php echo esc_url( $aksara_cta_url ); ?>"><?php echo esc_html( $aksara_cta_label ); ?> <span aria-hidden="true">&#8599;</span></a></div>
