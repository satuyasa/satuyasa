<?php
/** Authentype-backed font specimen row. Font bytes never reach the browser. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$font_id = get_the_ID();
if ( 'ath_font' !== get_post_type( $font_id ) || ! function_exists( 'aksara_authentype_styles' ) ) {
	return;
}

$styles  = aksara_authentype_styles( $font_id );
$default = ! empty( $styles[0] ) ? $styles[0] : null;
$product = aksara_authentype_linked_product( $font_id );
$ready   = $product && $product->is_purchasable();
$gallery = is_front_page() && function_exists( 'aksara_authentype_product_gallery_ids' ) ? aksara_authentype_product_gallery_ids( $font_id, 3 ) : array();
$archive_gallery = is_post_type_archive( 'ath_font' ) && function_exists( 'aksara_authentype_product_gallery_ids' ) ? aksara_authentype_product_gallery_ids( $font_id, 1 ) : array();
$archive_image_id = $archive_gallery ? absint( $archive_gallery[0] ) : ( is_post_type_archive( 'ath_font' ) ? absint( get_post_thumbnail_id( $font_id ) ) : 0 );
aksara_authentype_enqueue_preview();
?>
<article class="specimen-row ath-specimen ath-specimen-v7 aksara-catalog-specimen"
	data-font-post-id="<?php echo esc_attr( $font_id ); ?>"
	data-text-color="#111111"
	data-bg-color="#ffffff">
	<div class="sp-controls">
		<div class="sp-label">
			<span class="sp-name-text"><?php the_title(); ?></span>
			<span class="sp-meta">
				<?php printf( esc_html( _n( '%d style', '%d styles', count( $styles ), 'aksara' ) ), count( $styles ) ); ?>
				<?php if ( $default && ! empty( $default['name'] ) ) : ?> · <?php echo esc_html( $default['name'] ); ?><?php endif; ?>
			</span>
		</div>
		<div class="sp-actions">
			<span class="sp-price-group"><span class="sp-price"><?php echo $product ? wp_kses_post( $product->get_price_html() ) : esc_html__( 'Preparing price', 'aksara' ); ?></span><?php echo $product && function_exists( 'aksara_product_discount_badge' ) ? wp_kses_post( aksara_product_discount_badge( $product ) ) : ''; ?></span>
			<?php if ( $product && function_exists( 'aksara_wishlist_button' ) ) { aksara_wishlist_button( $product->get_id() ); } ?>
			<a class="btn-trial" href="<?php echo esc_url( get_permalink( $font_id ) . '#font-specimen' ); ?>"><?php esc_html_e( 'Try', 'aksara' ); ?></a>
			<a class="btn-view<?php echo $ready ? '' : ' is-preparing'; ?>" href="<?php echo esc_url( get_permalink( $font_id ) ); ?>"><?php esc_html_e( 'View', 'aksara' ); ?></a>
		</div>
	</div>
	<a class="sp-specimen" href="<?php echo esc_url( get_permalink( $font_id ) ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'View %s', 'aksara' ), get_the_title( $font_id ) ) ); ?>">
		<?php if ( $default && ! empty( $default['token'] ) ) : ?>
			<canvas class="ath-server-canvas aksara-row-canvas"
				data-font-token="<?php echo esc_attr( $default['token'] ); ?>"
				data-mode="style-text"
				data-text="<?php echo esc_attr( get_the_title( $font_id ) ); ?>"
				data-font-size="112"
				data-fit-single-line="1"
				aria-label="<?php echo esc_attr( sprintf( __( '%s font preview', 'aksara' ), get_the_title( $font_id ) ) ); ?>"></canvas>
		<?php else : ?>
			<span class="sp-specimen-fallback"><?php the_title(); ?></span>
		<?php endif; ?>
	</a>
	<?php if ( $archive_image_id ) : ?>
		<a class="font-archive-image" href="<?php echo esc_url( get_permalink( $font_id ) ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'View %s product images', 'aksara' ), get_the_title( $font_id ) ) ); ?>">
			<?php echo wp_get_attachment_image( $archive_image_id, 'aksara-preview-md', false, array( 'loading' => 'lazy', 'sizes' => '(max-width: 960px) calc(100vw - 32px), 50vw' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</a>
	<?php endif; ?>
	<?php if ( $gallery ) : ?>
		<div class="font-product-gallery" aria-label="<?php echo esc_attr( sprintf( __( '%s image gallery', 'aksara' ), get_the_title( $font_id ) ) ); ?>">
			<?php foreach ( $gallery as $image_id ) : ?>
				<a href="<?php echo esc_url( get_permalink( $font_id ) ); ?>">
					<?php echo wp_get_attachment_image( $image_id, 'aksara-preview-xl', false, array( 'loading' => 'lazy', 'sizes' => '(max-width: 760px) calc(100vw - 32px), 33vw' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</a>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</article>
