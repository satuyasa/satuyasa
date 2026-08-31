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
<a class="asset-card" href="<?php the_permalink(); ?>">
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
