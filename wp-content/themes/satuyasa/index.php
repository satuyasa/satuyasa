<?php
/**
 * Template utama fallback.
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

		<?php if ( have_posts() ) : ?>

			<?php if ( is_home() && ! is_front_page() ) : ?>
				<header class="page-header">
					<h1 class="page-title"><?php single_post_title(); ?></h1>
				</header>
			<?php endif; ?>

			<?php
			while ( have_posts() ) :
				the_post();
				get_template_part( 'template-parts/content' );
			endwhile;

			the_posts_pagination( array(
				'mid_size'  => 2,
				'prev_text' => esc_html__( '&laquo; Sebelumnya', 'satuyasa' ),
				'next_text' => esc_html__( 'Berikutnya &raquo;', 'satuyasa' ),
			) );
			?>

		<?php else : ?>

			<?php get_template_part( 'template-parts/content', 'none' ); ?>

		<?php endif; ?>

	</main>

	<?php get_sidebar(); ?>
</div>

<?php
get_footer();
