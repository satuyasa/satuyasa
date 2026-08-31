<?php
/**
 * Template part untuk hasil pencarian.
 *
 * @package Satuyasa
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<article id="post-<?php the_ID(); ?>" <?php post_class( 'entry-card' ); ?>>

	<header class="entry-header">
		<?php the_title( sprintf( '<h2 class="entry-title"><a href="%s" rel="bookmark">', esc_url( get_permalink() ) ), '</a></h2>' ); ?>
		<div class="entry-meta">
			<?php
			satuyasa_posted_on();
			satuyasa_posted_by();
			?>
		</div>
	</header>

	<div class="entry-summary">
		<?php the_excerpt(); ?>
	</div>
</article>
