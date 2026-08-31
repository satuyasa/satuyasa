<?php
/**
 * Template part ketika tidak ada konten yang ditemukan.
 *
 * @package Satuyasa
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section class="no-results not-found">
	<header class="page-header">
		<h1 class="page-title"><?php esc_html_e( 'Tidak ditemukan', 'satuyasa' ); ?></h1>
	</header>

	<div class="page-content">
		<?php if ( is_home() && current_user_can( 'publish_posts' ) ) : ?>

			<p>
				<?php
				printf(
					wp_kses(
						/* translators: %s: tautan buat tulisan baru. */
						__( 'Siap menerbitkan tulisan pertama Anda? <a href="%s">Mulai di sini</a>.', 'satuyasa' ),
						array( 'a' => array( 'href' => array() ) )
					),
					esc_url( admin_url( 'post-new.php' ) )
				);
				?>
			</p>

		<?php elseif ( is_search() ) : ?>

			<p><?php esc_html_e( 'Maaf, tidak ada hasil yang cocok dengan pencarian Anda. Silakan coba dengan kata kunci lain.', 'satuyasa' ); ?></p>
			<?php get_search_form(); ?>

		<?php else : ?>

			<p><?php esc_html_e( 'Sepertinya kami tidak dapat menemukan yang Anda cari. Coba gunakan pencarian.', 'satuyasa' ); ?></p>
			<?php get_search_form(); ?>

		<?php endif; ?>
	</div>
</section>
