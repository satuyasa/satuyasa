<?php
/**
 * Template Name: Aksara — Halaman License
 *
 * Referensi hukum untuk semua jenis lisensi font, dirender otomatis dari
 * data yang admin kelola di WooCommerce > Lisensi Font (lihat
 * includes/admin/class-license-admin.php di plugin Aksara Marketplace) —
 * bukan teks statis, supaya selalu sinkron begitu admin menambah/mengubah
 * jenis lisensi.
 *
 * @package Aksara
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$licenses = class_exists( 'Aksara_Font_Licenses_Repository' ) ? Aksara_Font_Licenses_Repository::get_all() : array();
?>

<div class="wrap content-area">
	<header class="page-header">
		<h1 class="page-title"><?php the_title(); ?></h1>
	</header>

	<?php
	while ( have_posts() ) :
		the_post();
		the_content();
	endwhile;
	?>

	<?php if ( empty( $licenses ) ) : ?>
		<p><?php esc_html_e( 'Belum ada jenis lisensi yang diatur.', 'aksara' ); ?></p>
	<?php else : ?>
		<?php foreach ( $licenses as $license ) : ?>
			<div class="license-page-item">
				<h3><?php echo esc_html( $license->name ); ?></h3>
				<div class="license-description"><?php echo wp_kses_post( wpautop( $license->description ) ); ?></div>
			</div>
		<?php endforeach; ?>
	<?php endif; ?>
</div>

<?php
get_footer();
