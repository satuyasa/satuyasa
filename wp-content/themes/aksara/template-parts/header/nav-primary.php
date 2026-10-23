<?php
/**
 * Navigasi utama beserta tombol toggle-nya.
 *
 * Tombol dan <nav> ada di SATU part, tidak dipisah: aria-controls pada tombol
 * menunjuk id menu di dalam <nav>, dan assets/js/navigation.js mengandalkan
 * keduanya hadir bersama. Memisahkannya membuat pasangan itu bisa terputus
 * tanpa ada yang menyadarinya.
 *
 * Dropdown satu tingkatnya murni CSS (:hover + :focus-within) dan tidak butuh
 * skrip apa pun. Alasan lengkapnya, termasuk kenapa TIDAK ada aria-expanded di
 * sini, ada di blok "Dropdown satu tingkat" di style.css.
 *
 * @package Aksara
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<button class="menu-toggle" aria-controls="primary-menu" aria-expanded="false">
	<?php esc_html_e( 'Menu', 'aksara' ); ?>
</button>

<nav id="site-navigation" class="main-navigation" aria-label="<?php esc_attr_e( 'Primary menu', 'aksara' ); ?>">
	<?php
	if ( has_nav_menu( 'primary' ) ) {
		wp_nav_menu( array(
			'theme_location' => 'primary',
			'menu_id'        => 'primary-menu',
			/*
			 * Dua tingkat: induk + satu panel dropdown. Tingkat ketiga TIDAK
			 * dicetak sama sekali, dan itu disengaja — bukan disembunyikan
			 * lewat CSS.
			 *
			 * Bedanya penting. Sampai 0.9.26 seluruh sub-menu dicetak ke HTML
			 * lalu ditutup oleh ".main-navigation ul ul { display: none }", jadi
			 * item yang dibuat admin di Appearance > Menus hilang tanpa jejak:
			 * ada di sumber halaman, tidak pernah tampil, tanpa satu pun tanda
			 * kenapa. Kalau memang tidak didukung, lebih jujur tidak
			 * mencetaknya — admin melihat item ketiganya tidak muncul di mana
			 * pun dan tahu itu batasnya.
			 */
			'depth'          => 2,
		) );
	} else {
		aksara_fallback_menu();
	}
	?>
</nav>
