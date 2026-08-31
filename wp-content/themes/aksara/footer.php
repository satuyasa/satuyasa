<?php
/**
 * Template footer.
 *
 * @package Aksara
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
	</div><!-- #content -->

	<footer id="colophon" class="site-footer">
		<div class="wrap">
			<div class="footer-grid">
				<div>
					<p class="site-title"><?php bloginfo( 'name' ); ?></p>
					<p class="footer-tagline"><?php bloginfo( 'description' ); ?></p>
				</div>
				<div>
					<h5><?php esc_html_e( 'Belanja', 'aksara' ); ?></h5>
					<?php
					if ( has_nav_menu( 'footer_shop' ) ) {
						wp_nav_menu( array( 'theme_location' => 'footer_shop', 'container' => false, 'depth' => 1 ) );
					}
					?>
				</div>
				<div>
					<h5><?php esc_html_e( 'Bantuan', 'aksara' ); ?></h5>
					<?php
					if ( has_nav_menu( 'footer_help' ) ) {
						wp_nav_menu( array( 'theme_location' => 'footer_help', 'container' => false, 'depth' => 1 ) );
					}
					?>
				</div>
				<div>
					<h5><?php esc_html_e( 'Perusahaan', 'aksara' ); ?></h5>
					<?php
					if ( has_nav_menu( 'footer_about' ) ) {
						wp_nav_menu( array( 'theme_location' => 'footer_about', 'container' => false, 'depth' => 1 ) );
					}
					?>
				</div>
			</div>

			<div class="footer-bottom">
				<span>&copy; <?php echo esc_html( date_i18n( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?>.</span>
				<span><?php esc_html_e( 'Dibuat untuk kreator Indonesia.', 'aksara' ); ?></span>
			</div>
		</div>
	</footer>
</div><!-- #page -->

<?php wp_footer(); ?>
</body>
</html>
