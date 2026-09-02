<?php
/**
 * Empat kolom footer: identitas situs + tiga menu.
 *
 * Judul kolom tetap dicetak walau menunya belum diisi, supaya kerangka
 * footernya tidak berubah bentuk begitu admin menambahkan menu. Perilaku itu
 * diambil apa adanya dari footer.php lama.
 *
 * @package Aksara
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$aksara_footer_menus = array(
	'footer_shop'  => __( 'Shop', 'aksara' ),
	'footer_help'  => __( 'Help', 'aksara' ),
	'footer_about' => __( 'Company', 'aksara' ),
);
?>
<div class="footer-grid">
	<div>
		<p class="site-title"><?php bloginfo( 'name' ); ?></p>
		<p class="footer-tagline"><?php bloginfo( 'description' ); ?></p>
	</div>
	<?php foreach ( $aksara_footer_menus as $aksara_location => $aksara_heading ) : ?>
		<div>
			<h5><?php echo esc_html( $aksara_heading ); ?></h5>
			<?php
			if ( has_nav_menu( $aksara_location ) ) {
				wp_nav_menu( array( 'theme_location' => $aksara_location, 'container' => false, 'depth' => 1 ) );
			}
			?>
		</div>
	<?php endforeach; ?>
</div>
