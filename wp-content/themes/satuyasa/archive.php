<?php
/**
 * Template untuk halaman arsip (kategori, tag, tanggal, CPT, taksonomi).
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

			<header class="page-header">
				<?php
				the_archive_title( '<h1 class="page-title">', '</h1>' );
				the_archive_description( '<div class="archive-description">', '</div>' );
				?>
			</header>

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
