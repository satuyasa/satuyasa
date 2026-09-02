<?php
/**
 * Navigasi utama beserta tombol toggle-nya.
 *
 * Tombol dan <nav> ada di SATU part, tidak dipisah: aria-controls pada tombol
 * menunjuk id menu di dalam <nav>, dan assets/js/navigation.js mengandalkan
 * keduanya hadir bersama. Memisahkannya membuat pasangan itu bisa terputus
 * tanpa ada yang menyadarinya.
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
		) );
	} else {
		aksara_fallback_menu();
	}
	?>
</nav>
