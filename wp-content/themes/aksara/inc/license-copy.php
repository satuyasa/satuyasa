<?php
/**
 * Membuat teks penjelasan lisensi bisa diedit — TANPA menyunting plugin.
 *
 * MASALAHNYA
 *
 * Tab "Licensing" di halaman produk font dirender shortcode Authentype
 * (includes/shortcode-specimen.php sekitar baris 843-856). Yang datang dari
 * data hanya label dan deskripsi tiap lisensi; empat kalimat di sekelilingnya
 * ditulis langsung di berkas plugin:
 *
 *     "Licensing Options"                     judul bagian
 *     "Choose the license that matches..."    paragraf penjelasan
 *     "usage license"                         akhiran di belakang nama lisensi
 *     "Read full license details"             label tautan
 *
 * Tidak ada satu pun layar di wp-admin yang bisa mengubahnya.
 *
 * KENAPA TIDAK DIPERBAIKI DI PLUGIN SAJA
 *
 * Authentype plugin pihak ketiga. Setiap suntingan di sana akan hilang pada
 * pembaruan berikutnya, dan yang lebih buruk: hilangnya diam-diam, biasanya
 * baru ketahuan berbulan-bulan kemudian ketika seseorang bertanya kenapa
 * teksnya kembali ke bahasa Inggris.
 *
 * CARANYA
 *
 * Keempat kalimat itu dibungkus esc_html_e( ..., 'authentype-font-specimen' ),
 * artinya semuanya melewati gettext. Filter 'gettext' bisa menukar hasilnya
 * sebelum dicetak — mekanisme resmi WordPress, dipakai persis untuk keperluan
 * ini, dan sepenuhnya aman terhadap pembaruan plugin.
 *
 * BIAYANYA DIJAGA. Filter gettext berjalan untuk SETIAP string yang
 * diterjemahkan di setiap permintaan halaman — ribuan kali. Karena itu fungsi
 * di bawah keluar pada baris pertama kalau domainnya bukan milik Authentype,
 * yang menyingkirkan hampir seluruh panggilan sebelum ada pekerjaan apa pun,
 * dan peta penggantinya dibangun sekali lalu disimpan statis.
 *
 * @package Aksara
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Domain teks milik plugin Authentype. */
const AKSARA_ATH_DOMAIN = 'authentype-font-specimen';

/**
 * Kalimat yang bisa ditimpa, beserta bunyi aslinya di plugin.
 *
 * Kunci array = string PERSIS seperti tertulis di plugin. Kalau plugin
 * mengubah kalimatnya di versi berikutnya, penggantian untuk kalimat itu
 * berhenti berlaku dan teks aslinya yang tampil — gagal ke keadaan yang masih
 * benar, bukan ke halaman rusak.
 *
 * @return array<string,array{setting:string,label:string}>
 */
function aksara_license_copy_map() {
	return array(
		'Licensing Options' => array(
			'setting' => 'aksara_lic_heading',
			'label'   => __( 'Licensing section heading', 'aksara' ),
		),
		'Choose the license that matches the way the font will be used. For company-wide, agency, high-volume, or unlisted usage, review the full license terms or contact the foundry before purchasing.' => array(
			'setting' => 'aksara_lic_intro',
			'label'   => __( 'Licensing intro paragraph', 'aksara' ),
		),
		'usage license' => array(
			'setting' => 'aksara_lic_suffix',
			'label'   => __( 'Suffix after each license name', 'aksara' ),
		),
		'Read full license details' => array(
			'setting' => 'aksara_lic_link',
			'label'   => __( 'Link label to the full license', 'aksara' ),
		),
	);
}

/**
 * Tukar teks Authentype dengan versi yang diatur admin.
 *
 * @param string $translation Hasil terjemahan.
 * @param string $text        Teks asli.
 * @param string $domain      Domain teks.
 * @return string
 */
function aksara_license_copy_gettext( $translation, $text, $domain ) {
	if ( AKSARA_ATH_DOMAIN !== $domain ) {
		return $translation;
	}

	static $overrides = null;
	if ( null === $overrides ) {
		$overrides = array();
		foreach ( aksara_license_copy_map() as $original => $meta ) {
			$value = trim( (string) get_theme_mod( $meta['setting'], '' ) );
			if ( '' !== $value ) {
				$overrides[ $original ] = $value;
			}
		}
	}

	return $overrides[ $text ] ?? $translation;
}
add_filter( 'gettext', 'aksara_license_copy_gettext', 10, 3 );

/**
 * Kontrolnya di Customizer.
 *
 * Dibiarkan KOSONG secara bawaan, dan kosong berarti "pakai teks asli plugin".
 * Kalau defaultnya diisi salinan kalimat plugin, salinan itu akan membeku:
 * plugin memperbaiki kalimatnya di versi berikutnya, situs tetap menampilkan
 * versi lama tanpa ada yang tahu kenapa.
 *
 * @param WP_Customize_Manager $wp_customize Manajer Customizer.
 */
function aksara_license_copy_customize( $wp_customize ) {
	$wp_customize->add_section( 'aksara_license_copy', array(
		'title'       => __( 'Licensing text', 'aksara' ),
		'panel'       => 'aksara_panel',
		'description' => __( 'The Licensing tab on a font page is rendered by the Authentype plugin, and four of its sentences are written into the plugin itself. Fill a field here to replace one; leave it empty to keep the plugin wording. The license names and descriptions themselves are edited under WooCommerce → Font Licenses.', 'aksara' ),
	) );

	foreach ( aksara_license_copy_map() as $original => $meta ) {
		$wp_customize->add_setting( $meta['setting'], array(
			'default'           => '',
			'sanitize_callback' => 'sanitize_text_field',
			'transport'         => 'refresh',
		) );
		$wp_customize->add_control( $meta['setting'], array(
			'section'     => 'aksara_license_copy',
			'label'       => $meta['label'],
			/* translators: %s: kalimat asli dari plugin. */
			'description' => sprintf( __( 'Plugin wording: “%s”', 'aksara' ), $original ),
			'type'        => strlen( $original ) > 60 ? 'textarea' : 'text',
		) );
	}
}
add_action( 'customize_register', 'aksara_license_copy_customize', 20 );
