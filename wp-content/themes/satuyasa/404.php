<?php
/**
 * Template untuk halaman 404 (tidak ditemukan).
 *
 * @package Satuyasa
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<div class="container">
	<main id="primary" class="content-area">

		<section class="no-results not-found">
			<header class="page-header">
				<h1 class="page-title"><?php esc_html_e( 'Halaman Tidak Ditemukan (404)', 'satuyasa' ); ?></h1>
			</header>

			<div class="page-content">
				<p><?php esc_html_e( 'Sepertinya tidak ada yang cocok dengan alamat yang Anda tuju. Coba gunakan pencarian di bawah ini.', 'satuyasa' ); ?></p>
				<?php get_search_form(); ?>
			</div>
		</section>

	</main>
</div>

<?php
get_footer();
