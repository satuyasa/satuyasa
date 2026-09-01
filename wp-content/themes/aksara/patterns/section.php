<?php
/**
 * Title: Section with heading
 * Slug: aksara/section
 * Categories: aksara
 * Description: A full-width section with a small uppercase heading, a "view all" link, and room for content.
 * Inserter: true
 *
 * @package Aksara
 */

?>
<!-- wp:group {"className":"section","layout":{"type":"default"}} -->
<div class="wp-block-group section">
	<!-- wp:group {"className":"wrap","layout":{"type":"default"}} -->
	<div class="wp-block-group wrap">
		<!-- wp:group {"className":"section-head","layout":{"type":"flex","justifyContent":"space-between","flexWrap":"wrap"}} -->
		<div class="wp-block-group section-head">
			<!-- wp:heading {"level":2} --><h2 class="wp-block-heading"><?php esc_html_e( 'Section title', 'aksara' ); ?></h2><!-- /wp:heading -->
			<!-- wp:paragraph --><p><a href="#"><?php esc_html_e( 'View all', 'aksara' ); ?></a></p><!-- /wp:paragraph -->
		</div>
		<!-- /wp:group -->

		<!-- wp:paragraph --><p><?php esc_html_e( 'Add blocks here.', 'aksara' ); ?></p><!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->
</div>
<!-- /wp:group -->
