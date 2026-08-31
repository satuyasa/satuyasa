<?php
/**
 * Template footer.
 *
 * @package Satuyasa
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
	</div><!-- #content -->

	<footer id="colophon" class="site-footer">
		<div class="container">

			<?php if ( is_active_sidebar( 'footer-1' ) || is_active_sidebar( 'footer-2' ) || is_active_sidebar( 'footer-3' ) ) : ?>
				<div class="footer-widgets">
					<?php for ( $satuyasa_i = 1; $satuyasa_i <= 3; $satuyasa_i++ ) : ?>
						<?php if ( is_active_sidebar( 'footer-' . $satuyasa_i ) ) : ?>
							<div class="footer-widget-area">
								<?php dynamic_sidebar( 'footer-' . $satuyasa_i ); ?>
							</div>
						<?php endif; ?>
					<?php endfor; ?>
				</div>
			<?php endif; ?>

			<div class="site-footer-bottom">
				<p class="site-info">
					&copy; <?php echo esc_html( date_i18n( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?>.
					<?php
					$satuyasa_footer_text = satuyasa_get_toolkit_option( 'footer_text', '' );
					if ( $satuyasa_footer_text ) {
						echo ' ' . wp_kses_post( $satuyasa_footer_text );
					} else {
						esc_html_e( 'Seluruh hak cipta dilindungi.', 'satuyasa' );
					}
					?>
				</p>

				<?php
				$satuyasa_socials = array(
					'facebook'  => satuyasa_get_toolkit_option( 'facebook_url' ),
					'instagram' => satuyasa_get_toolkit_option( 'instagram_url' ),
					'whatsapp'  => satuyasa_get_toolkit_option( 'whatsapp_number' ) ? 'https://wa.me/' . preg_replace( '/\D/', '', satuyasa_get_toolkit_option( 'whatsapp_number' ) ) : '',
				);
				$satuyasa_socials = array_filter( $satuyasa_socials );
				if ( ! empty( $satuyasa_socials ) ) :
					?>
					<ul class="satuyasa-social-links">
						<?php foreach ( $satuyasa_socials as $satuyasa_label => $satuyasa_url ) : ?>
							<li>
								<a href="<?php echo esc_url( $satuyasa_url ); ?>" target="_blank" rel="noopener noreferrer">
									<?php echo esc_html( ucfirst( $satuyasa_label ) ); ?>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>

				<?php if ( has_nav_menu( 'footer' ) ) : ?>
					<nav class="footer-navigation" aria-label="<?php esc_attr_e( 'Menu footer', 'satuyasa' ); ?>">
						<?php
						wp_nav_menu( array(
							'theme_location' => 'footer',
							'menu_id'        => 'footer-menu',
							'depth'          => 1,
						) );
						?>
					</nav>
				<?php endif; ?>
			</div>

		</div>
	</footer>
</div><!-- #page -->

<?php wp_footer(); ?>
</body>
</html>
