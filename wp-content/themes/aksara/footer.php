<?php
/**
 * Kerangka footer situs.
 *
 * Sama seperti header.php: hanya menyusun. Komponennya di
 * template-parts/footer/. Lihat header.php untuk alasan lengkapnya.
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
			<?php get_template_part( 'template-parts/footer/cta' ); ?>
			<?php get_template_part( 'template-parts/footer/menus' ); ?>
			<?php get_template_part( 'template-parts/footer/bottom' ); ?>
		</div>
	</footer>
</div><!-- #page -->

<?php wp_footer(); ?>
</body>
</html>
