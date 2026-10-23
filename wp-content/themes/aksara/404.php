<?php
/**
 * Template halaman 404.
 *
 * @package Aksara
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<div class="wrap content-area">
	<main id="primary">
		<section class="no-results not-found">
			<header class="page-header">
				<h1 class="page-title"><?php esc_html_e( 'Page Not Found (404)', 'aksara' ); ?></h1>
			</header>
			<div class="page-content">
				<p><?php esc_html_e( 'Nothing seems to match that address. Try the search below.', 'aksara' ); ?></p>
				<?php get_search_form(); ?>
			</div>
		</section>
	</main>
</div>

<?php
get_footer();
