<?php
/**
 * Template arsip blog generik (kategori, tag, tanggal). Arsip produk
 * WooCommerce dilayani woocommerce.php / page-templates kustom.
 *
 * @package Aksara
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<main id="primary" class="editorial-index editorial-archive"><div class="wrap">
		<?php if ( have_posts() ) : ?>
			<header class="editorial-masthead editorial-masthead--archive">
				<?php
				the_archive_title( '<h1 class="page-title">', '</h1>' );
				the_archive_description( '<div class="archive-description">', '</div>' );
				?>
			</header>

			<?php
			echo '<div class="editorial-feed">';
			while ( have_posts() ) :
				the_post();
				get_template_part( 'template-parts/editorial-card' );
			endwhile;
			echo '</div>';

			the_posts_pagination();
			?>
		<?php else : ?>
			<?php get_template_part( 'template-parts/content', 'none' ); ?>
		<?php endif; ?>
	</div></main>

<?php
get_footer();
