<?php
/**
 * Template untuk halaman (page) standar — dipakai untuk Contact, FAQ,
 * Terms of Service, Privacy Policy, Refund Policy, dsb. Halaman
 * Home/Fonts/Templates/Elements/License memakai page template kustom
 * di page-templates/ (dipilih lewat "Page Attributes" saat page dibuat).
 *
 * @package Aksara
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<div class="wrap content-area">
	<main id="primary">
		<?php
		while ( have_posts() ) :
			the_post();
			get_template_part( 'template-parts/content', 'page' );
			if ( comments_open() || get_comments_number() ) {
				comments_template();
			}
		endwhile;
		?>
	</main>
</div>

<?php
get_footer();
