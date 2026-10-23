<?php
/**
 * Branding situs: custom logo kalau ada, judul situs kalau tidak.
 *
 * @package Aksara
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="site-branding">
	<?php if ( has_custom_logo() ) : ?>
		<?php the_custom_logo(); ?>
	<?php else : ?>
		<p class="site-title"><a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php bloginfo( 'name' ); ?></a></p>
	<?php endif; ?>
</div>
