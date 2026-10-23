<?php
/**
 * Wrapper WooCommerce standar: bungkus woocommerce_content() dengan
 * header/footer tema. Ini membuat SEMUA halaman WooCommerce bawaan
 * (shop archive, single product, cart, checkout, my account) otomatis
 * memakai styling tema lewat CSS + hook, tanpa perlu override setiap
 * template WooCommerce satu per satu.
 *
 * Halaman Fonts/Templates/Elements/License yang butuh tampilan khusus
 * (lihat page-templates/) TIDAK memakai file ini — itu Page biasa dengan
 * template kustom.
 *
 * @package Aksara
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<div class="wrap aksara-product-summary">
	<?php woocommerce_content(); ?>
</div>

<?php
get_footer();
