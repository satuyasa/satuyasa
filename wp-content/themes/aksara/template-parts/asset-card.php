<?php
/**
 * Satu kartu produk di grid Template/Element (Home & listing).
 *
 * @package Aksara
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$product = wc_get_product( get_the_ID() );
if ( ! $product ) {
	return;
}
?>
<div class="asset-card">
	<?php if ( function_exists( 'aksara_wishlist_button' ) ) : ?>
		<?php aksara_wishlist_button( $product->get_id() ); ?>
	<?php endif; ?>
	<a href="<?php the_permalink(); ?>">
		<div class="asset-thumb">
			<?php if ( has_post_thumbnail() ) : ?>
				<?php the_post_thumbnail( 'medium' ); ?>
			<?php endif; ?>
		</div>
		<div class="asset-info">
			<h4><?php the_title(); ?></h4>
			<span><?php echo wp_kses_post( $product->get_price_html() ); ?></span>
		</div>
	</a>
</div>
