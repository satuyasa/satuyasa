<?php
/**
 * Template untuk halaman (page) standar.
 *
 * @package Satuyasa
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<div class="container <?php echo is_active_sidebar( 'sidebar-1' ) ? 'has-sidebar' : ''; ?>">
	<main id="primary" class="content-area">

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

	<?php get_sidebar(); ?>
</div>

<?php
get_footer();
