<?php
/**
 * Title: Trust points
 * Slug: aksara/trust
 * Categories: aksara
 * Description: Three numbered proof points on the dark band, used on the home page.
 * Inserter: true
 *
 * Pola statis (bukan blok dinamis) karena isinya murni teks — dengan
 * begini seluruh kalimatnya bisa disunting langsung di editor.
 *
 * @package Aksara
 */

?>
<!-- wp:group {"tagName":"section","className":"trust","layout":{"type":"default"}} -->
<section class="wp-block-group trust">
	<!-- wp:group {"className":"wrap","layout":{"type":"default"}} -->
	<div class="wp-block-group wrap">
		<!-- wp:group {"layout":{"type":"default"}} -->
		<div class="wp-block-group">
			<!-- wp:paragraph {"className":"trust-index"} --><p class="trust-index">01</p><!-- /wp:paragraph -->
			<!-- wp:heading {"level":3} --><h3 class="wp-block-heading"><?php esc_html_e( 'Licensing you can actually read', 'aksara' ); ?></h3><!-- /wp:heading -->
			<!-- wp:paragraph --><p><?php esc_html_e( 'Every font comes with its full licensing terms — desktop, web, app, and commercial — without the confusing jargon.', 'aksara' ); ?></p><!-- /wp:paragraph -->
		</div>
		<!-- /wp:group -->

		<!-- wp:group {"layout":{"type":"default"}} -->
		<div class="wp-block-group">
			<!-- wp:paragraph {"className":"trust-index"} --><p class="trust-index">02</p><!-- /wp:paragraph -->
			<!-- wp:heading {"level":3} --><h3 class="wp-block-heading"><?php esc_html_e( 'Try before you buy', 'aksara' ); ?></h3><!-- /wp:heading -->
			<!-- wp:paragraph --><p><?php esc_html_e( 'Type your own text and see every style live, without downloading anything.', 'aksara' ); ?></p><!-- /wp:paragraph -->
		</div>
		<!-- /wp:group -->

		<!-- wp:group {"layout":{"type":"default"}} -->
		<div class="wp-block-group">
			<!-- wp:paragraph {"className":"trust-index"} --><p class="trust-index">03</p><!-- /wp:paragraph -->
			<!-- wp:heading {"level":3} --><h3 class="wp-block-heading"><?php esc_html_e( 'Your files stay protected', 'aksara' ); ?></h3><!-- /wp:heading -->
			<!-- wp:paragraph --><p><?php esc_html_e( 'Original files are only released after successful payment, through a protected download link.', 'aksara' ); ?></p><!-- /wp:paragraph -->
		</div>
		<!-- /wp:group -->
	</div>
	<!-- /wp:group -->
</section>
<!-- /wp:group -->
