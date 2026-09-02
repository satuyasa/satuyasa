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
			<?php if ( is_home() || is_singular( 'post' ) || is_category() || is_tag() || is_date() || is_author() ) : ?>
				<div class="editorial-footer-cta"><p><?php esc_html_e( 'More ideas, type and independent design.', 'aksara' ); ?></p><a href="<?php echo esc_url( get_post_type_archive_link( 'ath_font' ) ?: home_url( '/' ) ); ?>"><?php esc_html_e( 'Explore the font library', 'aksara' ); ?> <span aria-hidden="true">&#8599;</span></a></div>
			<?php endif; ?>
			<div class="footer-grid">
				<div>
					<p class="site-title"><?php bloginfo( 'name' ); ?></p>
					<p class="footer-tagline"><?php bloginfo( 'description' ); ?></p>
				</div>
				<div>
					<h5><?php esc_html_e( 'Shop', 'aksara' ); ?></h5>
					<?php
					if ( has_nav_menu( 'footer_shop' ) ) {
						wp_nav_menu( array( 'theme_location' => 'footer_shop', 'container' => false, 'depth' => 1 ) );
					}
					?>
				</div>
				<div>
					<h5><?php esc_html_e( 'Help', 'aksara' ); ?></h5>
					<?php
					if ( has_nav_menu( 'footer_help' ) ) {
						wp_nav_menu( array( 'theme_location' => 'footer_help', 'container' => false, 'depth' => 1 ) );
					}
					?>
				</div>
				<div>
					<h5><?php esc_html_e( 'Company', 'aksara' ); ?></h5>
					<?php
					if ( has_nav_menu( 'footer_about' ) ) {
						wp_nav_menu( array( 'theme_location' => 'footer_about', 'container' => false, 'depth' => 1 ) );
					}
					?>
				</div>
			</div>

			<div class="footer-bottom">
				<span>&copy; <?php echo esc_html( date_i18n( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?>.</span>
				<span><?php esc_html_e( 'Made for Indonesian creators.', 'aksara' ); ?></span>
			</div>
		</div>
	</footer>
</div><!-- #page -->

<?php wp_footer(); ?>
</body>
</html>
