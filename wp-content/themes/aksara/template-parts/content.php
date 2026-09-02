<?php
/**
 * Template part untuk satu entri artikel blog di daftar/arsip.
 *
 * @package Aksara
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<article id="post-<?php the_ID(); ?>" <?php post_class( 'entry-card' ); ?>>

	<?php if ( has_post_thumbnail() && ! is_singular() ) : ?>
		<a class="entry-thumbnail" href="<?php the_permalink(); ?>">
			<?php the_post_thumbnail( 'large' ); ?>
		</a>
	<?php endif; ?>

	<header class="entry-header">
		<?php
		if ( is_singular() ) {
			the_title( '<h1 class="entry-title">', '</h1>' );
		} else {
			the_title( '<h2 class="entry-title"><a href="' . esc_url( get_permalink() ) . '" rel="bookmark">', '</a></h2>' );
		}
		?>
		<div class="entry-meta">
			<?php aksara_posted_on(); aksara_posted_by(); ?>
		</div>
	</header>

	<div class="entry-content">
		<?php
		if ( is_singular() ) {
			the_content();
		} else {
			the_excerpt();
		}
		?>
	</div>

	<footer class="entry-footer">
		<?php aksara_entry_footer(); ?>
	</footer>
</article>
