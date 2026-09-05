<?php
/**
 * Memberi tahu kalau halaman Licenses tertinggal dari yang benar-benar dijual.
 *
 * MASALAH YANG DIJAGA
 *
 * Halaman Licenses kini merender kartu untuk tiap lisensi yang ditawarkan
 * keluarga font terbit. Nama dan ringkasan pendeknya bisa diambil dari
 * Authentype, tapi ketentuan hukumnya — Allowed Uses, Prohibited Uses,
 * Limitations — tidak ada di sana dan tidak akan pernah ada: Authentype tidak
 * menyimpan struktur itu di mana pun.
 *
 * Artinya sinkronisasi otomatis hanya bisa membawa halaman sampai setengah
 * jalan. Menambah lisensi baru di sebuah font akan memunculkan kartunya
 * seketika, tapi kartu itu hanya berisi satu kalimat ringkas sampai seseorang
 * menuliskan ketentuannya. Tanpa pemberitahuan, "sampai seseorang menuliskan"
 * berarti berbulan-bulan — dan sepanjang itu ada lisensi yang bisa dibeli
 * tanpa penjelasan hukum apa pun di situs.
 *
 * Notice ini yang menutup jarak itu. Ia tidak memperbaiki apa pun sendiri;
 * ia hanya memastikan yang tertinggal tidak tertinggal diam-diam.
 *
 * KENAPA TIDAK MEMPERINGATKAN ARAH SEBALIKNYA
 *
 * Lisensi yang ketentuannya sudah ditulis tapi tidak dijual di mana pun bukan
 * masalah: halaman memang tidak merendernya, teksnya tetap tersimpan, dan
 * begitu lisensinya ditawarkan lagi kartunya kembali sendiri. Memperingatkan
 * hal itu berarti menyalakan lampu merah untuk keadaan yang sepenuhnya benar.
 *
 * @package Aksara
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lisensi yang dijual tapi ketentuannya masih kosong.
 *
 * @return array<int, array{slug:string, label:string}>
 */
function aksara_license_sync_gaps() {
	if ( ! function_exists( 'aksara_authentype_sold_licenses' ) || ! function_exists( 'aksara_mod' ) ) {
		return array();
	}

	$gaps = array();
	foreach ( aksara_authentype_sold_licenses() as $slug => $entry ) {
		$written = '';
		foreach ( array( 'overview', 'allowed', 'prohibited', 'limitations' ) as $part ) {
			$written .= aksara_mod( 'aksara_license_' . $slug . '_' . $part );
		}
		if ( '' === trim( $written ) ) {
			$gaps[] = array(
				'slug'  => $slug,
				'label' => $entry['label'],
			);
		}
	}

	return $gaps;
}

/**
 * Cetak notice-nya.
 *
 * Hanya untuk yang memang bisa menindaklanjuti (mengubah Customizer menuntut
 * edit_theme_options), dan tidak di AJAX/cron/REST supaya tidak pernah ada
 * kerja tak terduga di jalur yang sensitif waktu.
 */
function aksara_license_sync_notice() {
	if ( ! current_user_can( 'edit_theme_options' ) ) {
		return;
	}
	if ( wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return;
	}

	$gaps = aksara_license_sync_gaps();
	if ( ! $gaps ) {
		return;
	}

	$names = wp_list_pluck( $gaps, 'label' );

	echo '<div class="notice notice-warning"><p><strong>';
	echo esc_html(
		sprintf(
			/* translators: %d: number of licenses without written terms. */
			esc_html( _n( '%d license is sold without written terms.', '%d licenses are sold without written terms.', count( $gaps ), 'aksara' ) ),
			count( $gaps )
		)
	);
	echo '</strong> ';
	printf(
		/* translators: %s: comma-separated license names. */
		esc_html__( 'The Licenses page shows %s with only a one-line summary, because Allowed Uses, Prohibited Uses and Limitations are still empty. Authentype does not store those, so they have to be written here.', 'aksara' ),
		esc_html( implode( ', ', $names ) )
	);
	echo ' <a href="' . esc_url( admin_url( 'customize.php?autofocus[section]=aksara_license_page' ) ) . '">';
	esc_html_e( 'Write the terms', 'aksara' );
	echo '</a></p></div>';
}
add_action( 'admin_notices', 'aksara_license_sync_notice' );
