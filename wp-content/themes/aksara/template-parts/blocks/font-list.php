<?php
/**
 * Blok: daftar spesimen font (baris besar), dipakai di beranda.
 *
 * @package Aksara
 * @var array $attributes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$limit = isset( $attributes['limit'] ) ? max( 1, min( 24, (int) $attributes['limit'] ) ) : 6;
$fonts = function_exists( 'aksara_query_authentype_fonts' ) ? aksara_query_authentype_fonts( $limit ) : null;

if ( ! $fonts || ! $fonts->have_posts() ) {
	echo '<p>' . esc_html__( 'No fonts have been published yet.', 'aksara' ) . '</p>';
	return;
}

if ( function_exists( 'aksara_authentype_enqueue_preview' ) ) {
	aksara_authentype_enqueue_preview();
}
?>
<div class="specimen-list">
	<?php
	while ( $fonts->have_posts() ) :
		$fonts->the_post();
		get_template_part( 'template-parts/font-specimen-row' );
	endwhile;
	wp_reset_postdata();
	?>
</div>
