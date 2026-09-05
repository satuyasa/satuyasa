<?php
/** Editorial posts page. */
defined( 'ABSPATH' ) || exit;
get_header();
$blog_title = single_post_title( '', false );
if ( ! $blog_title ) $blog_title = __( 'Journal', 'aksara' );
?>
<main id="primary" class="editorial-index"><div class="wrap">
	<header class="editorial-masthead">
		<p class="editorial-kicker"><?php esc_html_e( 'Ideas, type and independent design', 'aksara' ); ?></p>
		<h1><?php echo esc_html( $blog_title ); ?></h1>
		<p><?php esc_html_e( 'Stories about typography, creative practice, licensing and the people behind the work.', 'aksara' ); ?></p>
	</header>
	<?php if ( have_posts() ) : ?><div class="editorial-feed">
		<?php $index = 0; while ( have_posts() ) : the_post(); get_template_part( 'template-parts/editorial-card', null, array( 'featured' => 0 === $index ) ); $index++; endwhile; ?>
	</div><?php the_posts_pagination( array( 'prev_text' => __( 'Previous', 'aksara' ), 'next_text' => __( 'Next', 'aksara' ) ) ); else : get_template_part( 'template-parts/content', 'none' ); endif; ?>
</div></main>
<?php get_footer(); ?>
