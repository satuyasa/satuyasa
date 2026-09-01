<?php
/**
 * Blok: grid produk Canva Template / Element.
 *
 * @package Aksara
 * @var array $attributes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$limit = isset( $attributes['limit'] ) ? max( 1, min( 48, (int) $attributes['limit'] ) ) : 8;
$type  = isset( $attributes['type'] ) ? (string) $attributes['type'] : 'both';

$terms = array( 'canva_template', 'canva_element' );
if ( 'template' === $type ) {
	$terms = array( 'canva_template' );
} elseif ( 'element' === $type ) {
	$terms = array( 'canva_element' );
}

$query = new WP_Query(
	array(
		'post_type'      => 'product',
		'posts_per_page' => $limit,
		'post_status'    => 'publish',
		'no_found_rows'  => true,
		'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			array(
				'taxonomy' => 'product_type',
				'field'    => 'slug',
				'terms'    => $terms,
			),
		),
	)
);

if ( ! $query->have_posts() ) {
	echo '<p>' . esc_html__( 'No templates or elements have been published yet.', 'aksara' ) . '</p>';
	return;
}
?>
<div class="asset-grid">
	<?php
	while ( $query->have_posts() ) :
		$query->the_post();
		get_template_part( 'template-parts/asset-card' );
	endwhile;
	wp_reset_postdata();
	?>
</div>
