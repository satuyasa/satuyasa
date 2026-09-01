<?php
/** Professional canonical font product page backed by Authentype + Woo. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();
while ( have_posts() ) :
	the_post();
	$font_id    = get_the_ID();
	$styles     = function_exists( 'aksara_authentype_styles' ) ? aksara_authentype_styles( $font_id ) : array();
	$product    = function_exists( 'aksara_authentype_linked_product' ) ? aksara_authentype_linked_product( $font_id ) : null;
	$gallery    = function_exists( 'aksara_authentype_product_gallery_ids' ) ? aksara_authentype_product_gallery_ids( $font_id, 3 ) : array();
	$categories = function_exists( 'aksara_authentype_product_terms' ) ? aksara_authentype_product_terms( $product, 'product_cat' ) : array();
	$tags       = function_exists( 'aksara_authentype_product_terms' ) ? aksara_authentype_product_terms( $product, 'product_tag' ) : array();
	$content    = trim( (string) get_the_content() );
	$summary    = $product ? trim( (string) $product->get_short_description() ) : '';
	if ( ! $content && $product ) {
		$content = trim( (string) $product->get_description() );
	}
	$related = function_exists( 'aksara_related_authentype_fonts' ) ? aksara_related_authentype_fonts( $font_id, 3 ) : null;
	$rating_count = $product ? (int) $product->get_rating_count() : 0;
	?>
	<main id="primary" class="site-main authentype-single"><div class="wrap">
		<nav class="font-breadcrumb" aria-label="<?php esc_attr_e( 'Breadcrumb', 'aksara' ); ?>"><a href="<?php echo esc_url( aksara_authentype_archive_url() ); ?>"><?php esc_html_e( 'Fonts', 'aksara' ); ?></a><span aria-hidden="true">/</span><span><?php the_title(); ?></span></nav>
		<header class="font-detail-header">
			<div><p class="eyebrow"><?php esc_html_e( 'Font family', 'aksara' ); ?></p><h1><?php the_title(); ?></h1><?php if ( $summary ) : ?><div class="font-detail-lead"><?php echo wp_kses_post( wpautop( $summary ) ); ?></div><?php endif; ?></div>
			<div class="font-detail-meta"><span><?php printf( esc_html( _n( '%d style', '%d styles', count( $styles ), 'aksara' ) ), count( $styles ) ); ?></span><?php if ( $rating_count && function_exists( 'wc_get_rating_html' ) ) : ?><span class="font-rating"><?php echo wp_kses_post( wc_get_rating_html( $product->get_average_rating(), $rating_count ) ); ?> <?php printf( esc_html( _n( '%d review', '%d reviews', $rating_count, 'aksara' ) ), $rating_count ); ?></span><?php endif; ?><?php if ( $product ) : ?><span><?php echo wp_kses_post( $product->get_price_html() ); ?></span><?php echo function_exists( 'aksara_product_discount_badge' ) ? wp_kses_post( aksara_product_discount_badge( $product ) ) : ''; ?><?php endif; ?></div>
		</header>

		<?php if ( $gallery ) : ?>
			<div class="font-single-gallery">
				<?php foreach ( $gallery as $image_id ) : ?>
					<a href="<?php echo esc_url( wp_get_attachment_image_url( $image_id, 'full' ) ); ?>"><?php echo wp_get_attachment_image( $image_id, 'aksara-preview-xl', false, array( 'sizes' => '(max-width: 760px) calc(100vw - 32px), 33vw' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
				<?php endforeach; ?>
			</div>
		<?php elseif ( has_post_thumbnail() ) : ?>
			<div class="font-cover"><?php the_post_thumbnail( 'aksara-preview-xl', array( 'sizes' => '(max-width: 1820px) 100vw, 1820px' ) ); ?></div>
		<?php endif; ?>

		<section class="font-overview">
			<div class="font-overview__content"><p class="eyebrow"><?php esc_html_e( 'About this family', 'aksara' ); ?></p><?php echo $content ? wp_kses_post( apply_filters( 'the_content', $content ) ) : '<p>' . esc_html__( 'Product description will be available soon.', 'aksara' ) . '</p>'; ?></div>
			<aside class="font-overview__meta"><h2><?php esc_html_e( 'Product details', 'aksara' ); ?></h2><dl>
				<div><dt><?php esc_html_e( 'Styles', 'aksara' ); ?></dt><dd><?php echo esc_html( count( $styles ) ); ?></dd></div>
				<?php if ( $product ) : ?><div><dt><?php esc_html_e( 'Availability', 'aksara' ); ?></dt><dd><?php echo esc_html( $product->is_in_stock() ? __( 'Available', 'aksara' ) : __( 'Unavailable', 'aksara' ) ); ?></dd></div><?php endif; ?>
				<?php if ( $product && $product->get_sku() ) : ?><div><dt><?php esc_html_e( 'SKU', 'aksara' ); ?></dt><dd><?php echo esc_html( $product->get_sku() ); ?></dd></div><?php endif; ?>
				<?php if ( $categories ) : ?><div><dt><?php esc_html_e( 'Categories', 'aksara' ); ?></dt><dd><?php echo wp_kses_post( aksara_term_links( $categories ) ); ?></dd></div><?php endif; ?>
				<?php if ( $tags ) : ?><div><dt><?php esc_html_e( 'Tags', 'aksara' ); ?></dt><dd><?php echo wp_kses_post( aksara_term_links( $tags ) ); ?></dd></div><?php endif; ?>
			</dl></aside>
		</section>

		<section id="font-specimen" class="authentype-specimen-shell"><div class="product-section-heading"><p class="eyebrow"><?php esc_html_e( 'Try and buy', 'aksara' ); ?></p><h2><?php esc_html_e( 'Preview every style', 'aksara' ); ?></h2></div><?php if ( shortcode_exists( 'authentype_font_specimen' ) ) { echo do_shortcode( '[authentype_font_specimen id="' . absint( $font_id ) . '"]' ); } else { echo '<p>' . esc_html__( 'Authentype is required to display this font.', 'aksara' ) . '</p>'; } // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></section>

		<?php if ( $related && $related->have_posts() ) : ?>
			<section class="related-fonts"><div class="product-section-heading"><p class="eyebrow"><?php esc_html_e( 'Keep exploring', 'aksara' ); ?></p><h2><?php esc_html_e( 'Related font families', 'aksara' ); ?></h2></div><div class="related-font-grid"><?php while ( $related->have_posts() ) : $related->the_post(); get_template_part( 'template-parts/font-product-card' ); endwhile; wp_reset_postdata(); ?></div></section>
		<?php endif; ?>
	</div></main>
<?php endwhile; get_footer(); ?>
