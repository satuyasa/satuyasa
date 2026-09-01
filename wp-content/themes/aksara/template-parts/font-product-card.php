<?php
/** Compact canonical font card for related-family sections. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$font_id = get_the_ID();
$product = function_exists( 'aksara_authentype_linked_product' ) ? aksara_authentype_linked_product( $font_id ) : null;
$styles  = function_exists( 'aksara_authentype_styles' ) ? aksara_authentype_styles( $font_id ) : array();
$images  = function_exists( 'aksara_authentype_product_gallery_ids' ) ? aksara_authentype_product_gallery_ids( $font_id, 1 ) : array();
?>
<article class="font-product-card">
	<a class="font-product-card__image" href="<?php the_permalink(); ?>">
		<?php if ( $images ) : ?>
			<?php echo wp_get_attachment_image( $images[0], 'aksara-preview-md', false, array( 'loading' => 'lazy', 'sizes' => '(max-width: 760px) calc(100vw - 32px), 33vw' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php else : ?><span><?php the_title(); ?></span><?php endif; ?>
	</a>
	<div class="font-product-card__body">
		<div><h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3><p><?php printf( esc_html( _n( '%d style', '%d styles', count( $styles ), 'aksara' ) ), count( $styles ) ); ?></p></div>
		<div class="font-product-card__price"><?php echo $product ? wp_kses_post( $product->get_price_html() ) : ''; ?><?php echo $product && function_exists( 'aksara_product_discount_badge' ) ? wp_kses_post( aksara_product_discount_badge( $product ) ) : ''; ?></div>
	</div>
</article>
