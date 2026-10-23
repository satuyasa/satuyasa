<?php
/**
 * Baris tautan sosial di footer.
 *
 * Hanya tampil kalau admin benar-benar memasang menu di lokasi 'social'. Tanpa
 * menu, tidak ada apa pun yang dicetak — bukan daftar ikon kosong, bukan
 * pembungkus kosong. Baris ini berada DI LUAR .footer-grid supaya kolom-kolom
 * grid tidak bergeser saat menunya diisi.
 *
 * Tautannya teks, bukan ikon. Ikon jaringan sosial adalah logo berwarna milik
 * pihak lain; menempelkannya di sistem yang sepenuhnya akromatik akan menjadi
 * satu-satunya warna di seluruh halaman. Nama jaringan sebagai teks tetap
 * terbaca, tetap bisa di-crawl, dan tidak perlu diperbarui setiap kali sebuah
 * platform mengganti lambangnya.
 *
 * @package Aksara
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! has_nav_menu( 'social' ) ) {
	return;
}
?>
<nav class="footer-social" aria-label="<?php esc_attr_e( 'Social links', 'aksara' ); ?>">
	<?php
	wp_nav_menu( array(
		'theme_location' => 'social',
		'container'      => false,
		'depth'          => 1,
		'menu_class'     => 'footer-social__list',
	) );
	?>
</nav>
