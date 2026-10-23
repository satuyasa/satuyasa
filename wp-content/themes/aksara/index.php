<?php
/**
 * Template utama fallback (blog listing).
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
		<?php if ( have_posts() ) : ?>

			<?php if ( is_home() && ! is_front_page() ) : ?>
				<header class="page-header"><h1 class="page-title"><?php single_post_title(); ?></h1></header>
			<?php endif; ?>

			<?php
			while ( have_posts() ) :
				the_post();
				get_template_part( 'template-parts/content' );
			endwhile;

			the_posts_pagination( array(
				'prev_text' => esc_html__( '&laquo; Previous', 'aksara' ),
				'next_text' => esc_html__( 'Next &raquo;', 'aksara' ),
			) );
			?>

		<?php else : ?>
			<?php get_template_part( 'template-parts/content', 'none' ); ?>
		<?php endif; ?>
	</main>
</div>

<?php
get_footer();
