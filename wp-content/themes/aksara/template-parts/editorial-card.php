<?php
/** Editorial archive card. @var array $args */
defined( 'ABSPATH' ) || exit;
$featured = ! empty( $args['featured'] );
$category = aksara_editorial_category();
?>
<article id="post-<?php the_ID(); ?>" <?php post_class( $featured ? 'editorial-card editorial-card--featured' : 'editorial-card' ); ?>>
	<?php if ( has_post_thumbnail() ) : ?><a class="editorial-card__image" href="<?php the_permalink(); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Read %s', 'aksara' ), get_the_title() ) ); ?>"><?php the_post_thumbnail( $featured ? 'aksara-preview-xl' : 'large', array( 'loading' => $featured ? 'eager' : 'lazy' ) ); ?></a><?php endif; ?>
	<div class="editorial-card__copy">
		<div class="editorial-card__meta"><?php if ( $category ) : ?><a href="<?php echo esc_url( get_category_link( $category->term_id ) ); ?>"><?php echo esc_html( $category->name ); ?></a><?php else : ?><span><?php esc_html_e( 'Journal', 'aksara' ); ?></span><?php endif; ?><span><?php echo esc_html( get_the_date() ); ?></span><span><?php printf( esc_html__( '%d min read', 'aksara' ), aksara_reading_time() ); ?></span></div>
		<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
		<div class="editorial-card__excerpt"><?php the_excerpt(); ?></div>
		<a class="editorial-read-link" href="<?php the_permalink(); ?>"><?php esc_html_e( 'Read story', 'aksara' ); ?> <span aria-hidden="true">&#8599;</span></a>
	</div>
</article>
