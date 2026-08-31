<?php
/**
 * Template untuk satu artikel (single post / CPT).
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

			get_template_part( 'template-parts/content', get_post_type() );

			the_post_navigation( array(
				'prev_text' => '<span class="nav-subtitle">' . esc_html__( 'Sebelumnya', 'satuyasa' ) . '</span> <span class="nav-title">%title</span>',
				'next_text' => '<span class="nav-subtitle">' . esc_html__( 'Selanjutnya', 'satuyasa' ) . '</span> <span class="nav-title">%title</span>',
			) );

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
