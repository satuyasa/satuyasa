<?php
/**
 * Blok: daftar jenis lisensi, dirender dari data yang dikelola admin di
 * WooCommerce > Font Licenses — bukan teks statis, supaya halaman License
 * selalu sinkron begitu admin menambah atau mengubah jenis lisensi.
 *
 * @package Aksara
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$licenses = class_exists( 'Aksara_Font_Licenses_Repository' ) ? Aksara_Font_Licenses_Repository::get_all() : array();

if ( empty( $licenses ) ) {
	echo '<p>' . esc_html__( 'No license types have been set up yet.', 'aksara' ) . '</p>';
	return;
}

foreach ( $licenses as $license ) :
	?>
	<div class="license-page-item">
		<h3><?php echo esc_html( $license->name ); ?></h3>
		<div class="license-description"><?php echo wp_kses_post( wpautop( $license->description ) ); ?></div>
	</div>
	<?php
endforeach;
