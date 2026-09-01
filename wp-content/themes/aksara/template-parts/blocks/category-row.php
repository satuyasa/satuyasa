<?php
/**
 * Blok: baris tiga kategori. Teksnya statis tapi URL-nya dicari runtime
 * dari page template yang dipakai, jadi tidak bisa jadi blok statis.
 *
 * @package Aksara
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section class="categories">
	<div class="wrap cat-grid">
		<a class="cat-card" href="<?php echo esc_url( aksara_get_listing_url( 'fonts' ) ); ?>">
			<div><h3><?php esc_html_e( 'Fonts', 'aksara' ); ?></h3><p><?php esc_html_e( 'Buy per style, with the license you actually need — desktop, web, or commercial.', 'aksara' ); ?></p></div>
			<span class="go"><?php esc_html_e( 'Browse fonts', 'aksara' ); ?></span>
		</a>
		<a class="cat-card" href="<?php echo esc_url( aksara_get_listing_url( 'templates' ) ); ?>">
			<div><h3><?php esc_html_e( 'Canva Templates', 'aksara' ); ?></h3><p><?php esc_html_e( 'Ready-to-edit designs for social media, resumes, presentations, and more.', 'aksara' ); ?></p></div>
			<span class="go"><?php esc_html_e( 'Browse templates', 'aksara' ); ?></span>
		</a>
		<a class="cat-card" href="<?php echo esc_url( aksara_get_listing_url( 'elements' ) ); ?>">
			<div><h3><?php esc_html_e( 'Canva Elements', 'aksara' ); ?></h3><p><?php esc_html_e( 'Icons, illustrations, and graphic shapes to finish your design.', 'aksara' ); ?></p></div>
			<span class="go"><?php esc_html_e( 'Browse elements', 'aksara' ); ?></span>
		</a>
	</div>
</section>
