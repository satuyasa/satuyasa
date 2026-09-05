<?php
/** Editorial single post. WooCommerce products are handled separately. */
defined( 'ABSPATH' ) || exit;
get_header();
while ( have_posts() ) : the_post();
	$category = aksara_editorial_category();
	$related_args = array( 'post_type' => 'post', 'posts_per_page' => 3, 'post__not_in' => array( get_the_ID() ), 'ignore_sticky_posts' => true, 'no_found_rows' => true );
	if ( $category ) $related_args['category__in'] = array( $category->term_id );
	$related = new WP_Query( $related_args );
	?>
	<main id="primary" class="editorial-single"><article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
		<header class="editorial-single__header wrap">
			<div class="editorial-card__meta"><?php if ( $category ) : ?><a href="<?php echo esc_url( get_category_link( $category->term_id ) ); ?>"><?php echo esc_html( $category->name ); ?></a><?php endif; ?><span><?php echo esc_html( get_the_date() ); ?></span><span><?php printf( esc_html__( '%d min read', 'aksara' ), aksara_reading_time() ); ?></span></div>
			<h1><?php the_title(); ?></h1>
			<?php if ( has_excerpt() ) : ?><div class="editorial-single__dek"><?php the_excerpt(); ?></div><?php endif; ?>
			<div class="editorial-single__byline"><?php echo get_avatar( get_the_author_meta( 'ID' ), 44 ); ?><span><?php esc_html_e( 'Written by', 'aksara' ); ?> <strong><?php the_author(); ?></strong></span></div>
		</header>
		<?php if ( has_post_thumbnail() ) : ?><figure class="editorial-single__hero wrap"><?php the_post_thumbnail( 'aksara-preview-xl', array( 'loading' => 'eager' ) ); ?><?php if ( get_the_post_thumbnail_caption() ) : ?><figcaption><?php echo wp_kses_post( get_the_post_thumbnail_caption() ); ?></figcaption><?php endif; ?></figure><?php endif; ?>
		<div class="editorial-single__body"><?php the_content(); wp_link_pages(); ?></div>
		<footer class="editorial-single__footer wrap">
			<div class="editorial-author"><?php echo get_avatar( get_the_author_meta( 'ID' ), 80 ); ?><div><p class="editorial-kicker"><?php esc_html_e( 'About the author', 'aksara' ); ?></p><h2><?php the_author(); ?></h2><?php if ( get_the_author_meta( 'description' ) ) : ?><div><?php echo wp_kses_post( wpautop( get_the_author_meta( 'description' ) ) ); ?></div><?php endif; ?></div></div>
			<?php the_post_navigation( array( 'prev_text' => '<span>' . esc_html__( 'Previous story', 'aksara' ) . '</span><strong>%title</strong>', 'next_text' => '<span>' . esc_html__( 'Next story', 'aksara' ) . '</span><strong>%title</strong>' ) ); ?>
		</footer>
		<?php if ( comments_open() || get_comments_number() ) : ?><div class="editorial-comments"><?php comments_template(); ?></div><?php endif; ?>
	</article>
	<?php if ( $related->have_posts() ) : ?><section class="editorial-related"><div class="wrap"><p class="editorial-kicker"><?php esc_html_e( 'Continue reading', 'aksara' ); ?></p><h2><?php esc_html_e( 'Related stories', 'aksara' ); ?></h2><div class="editorial-related__grid"><?php while ( $related->have_posts() ) : $related->the_post(); get_template_part( 'template-parts/editorial-card' ); endwhile; wp_reset_postdata(); ?></div></div></section><?php endif; ?>
	</main>
	<?php
endwhile;
get_footer();
