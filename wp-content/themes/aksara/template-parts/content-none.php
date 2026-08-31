<?php
/**
 * Template part ketika tidak ada konten yang ditemukan.
 *
 * @package Aksara
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section class="no-results not-found">
	<header class="page-header">
		<h1 class="page-title"><?php esc_html_e( 'Tidak ditemukan', 'aksara' ); ?></h1>
	</header>
	<div class="page-content">
		<?php if ( is_search() ) : ?>
			<p><?php esc_html_e( 'Maaf, tidak ada hasil yang cocok dengan pencarian Anda.', 'aksara' ); ?></p>
			<?php get_search_form(); ?>
		<?php else : ?>
			<p><?php esc_html_e( 'Belum ada konten di sini.', 'aksara' ); ?></p>
		<?php endif; ?>
	</div>
</section>
