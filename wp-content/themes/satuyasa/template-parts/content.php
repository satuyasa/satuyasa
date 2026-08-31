<?php
/**
 * Template part untuk menampilkan satu entri artikel di daftar/arsip.
 *
 * @package Satuyasa
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

		if ( 'post' === get_post_type() ) :
			?>
			<div class="entry-meta">
				<?php
				satuyasa_posted_on();
				satuyasa_posted_by();
				?>
			</div>
		<?php endif; ?>
	</header>

	<div class="entry-content">
		<?php
		if ( is_singular() ) {
			the_content( sprintf(
				wp_kses(
					/* translators: %s: nama artikel. */
					__( 'Lanjutkan membaca %s', 'satuyasa' ),
					array( 'span' => array( 'class' => array() ) )
				),
				the_title( '<span class="screen-reader-text">"', '"</span>', false )
			) );

			wp_link_pages( array(
				'before' => '<div class="page-links">' . esc_html__( 'Halaman:', 'satuyasa' ),
				'after'  => '</div>',
			) );
		} else {
			the_excerpt();
		}
		?>
	</div>

	<footer class="entry-footer">
		<?php satuyasa_entry_footer(); ?>
	</footer>
</article>
