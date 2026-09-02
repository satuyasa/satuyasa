<?php
/**
 * Customizer — bagian header & footer yang boleh diubah dari wp-admin.
 *
 * PRINSIP YANG DIPEGANG DI SINI
 *
 * 1. NILAI BAWAAN = TEKS YANG SUDAH ADA. Setiap setting default-nya persis
 *    string yang selama ini tertulis di template. Artinya situs yang belum
 *    pernah menyentuh Customizer menghasilkan HTML yang SAMA PERSIS seperti
 *    sebelum berkas ini ada — dan itu bisa dibuktikan, bukan diklaim.
 *
 * 2. YANG BISA DIUBAH HANYA ISI, BUKAN STRUKTUR. Tidak ada kontrol warna,
 *    ukuran huruf, atau lebar kolom. DESIGN.md menetapkan sistem visualnya
 *    dan itu bukan wilayah admin — membuka warna berarti mengundang situs
 *    keluar dari sistemnya sendiri, persis yang dicegah theme.json di versi
 *    block theme dulu.
 *
 * 3. NAVIGASI TETAP LEWAT MENU, BUKAN KONTROL CUSTOM. Tautan sosial memakai
 *    lokasi menu 'social' biasa. Menu WordPress sudah punya UI pengurutan,
 *    target, dan judul; membuat kontrol repeater sendiri hanya menirunya
 *    dengan lebih buruk.
 *
 * 4. SEMUA MASUKAN DISANITASI DI SINI, dan tetap di-escape lagi saat
 *    dicetak. Customizer hanya bisa diakses admin, tapi "hanya admin" bukan
 *    alasan untuk melewatkan sanitasi.
 *
 * @package Aksara
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Nilai bawaan setiap setting.
 *
 * Dikumpulkan di satu tempat supaya template dan Customizer tidak bisa
 * berbeda pendapat soal apa yang default. Template membacanya lewat
 * aksara_mod(), Customizer memakainya sebagai 'default'.
 *
 * @return array<string,string>
 */
function aksara_mod_defaults() {
	return array(
		// Header
		'aksara_topbar_enabled'   => '',
		'aksara_topbar_text'      => '',
		'aksara_topbar_url'       => '',

		// Footer — ajakan
		'aksara_footer_cta_scope' => 'editorial',
		'aksara_footer_cta_text'  => __( 'More ideas, type and independent design.', 'aksara' ),
		'aksara_footer_cta_label' => __( 'Explore the font library', 'aksara' ),
		'aksara_footer_cta_url'   => '',

		// Footer — judul kolom
		'aksara_footer_heading_shop'  => __( 'Shop', 'aksara' ),
		'aksara_footer_heading_help'  => __( 'Help', 'aksara' ),
		'aksara_footer_heading_about' => __( 'Company', 'aksara' ),

		// Footer — baris penutup
		'aksara_footer_note' => __( 'Made for Indonesian creators.', 'aksara' ),

		// Home (sudah dipakai front-page.php tapi belum pernah didaftarkan)
		'aksara_hero_title'    => __( 'The right type for your work.', 'aksara' ),
		'aksara_hero_subtitle' => __( 'Thousands of clearly licensed fonts, ready-made Canva templates, and design elements — all in one place, with a live preview before you buy.', 'aksara' ),
	);
}

/**
 * Baca satu setting berikut nilai bawaannya.
 *
 * @param string $key Nama setting.
 * @return string
 */
function aksara_mod( $key ) {
	$defaults = aksara_mod_defaults();
	$default  = isset( $defaults[ $key ] ) ? $defaults[ $key ] : '';
	return (string) get_theme_mod( $key, $default );
}

/** Checkbox disimpan sebagai '1' atau '' supaya cocok dengan pembacaan string. */
function aksara_sanitize_checkbox( $value ) {
	return $value ? '1' : '';
}

/** Cakupan ajakan footer: hanya tiga nilai yang diterima. */
function aksara_sanitize_cta_scope( $value ) {
	return in_array( $value, array( 'editorial', 'all', 'off' ), true ) ? $value : 'editorial';
}

/**
 * Daftarkan seluruh kontrol.
 *
 * @param WP_Customize_Manager $wp_customize Manajer Customizer.
 */
function aksara_customize_register( $wp_customize ) {
	$defaults = aksara_mod_defaults();

	$wp_customize->add_panel( 'aksara_panel', array(
		'title'       => __( 'Aksara', 'aksara' ),
		'description' => __( 'Editable text for the header, footer, and home page. Colours, type, and layout follow the theme design system and are intentionally not editable here.', 'aksara' ),
		'priority'    => 30,
	) );

	/* --- Header ------------------------------------------------------- */
	$wp_customize->add_section( 'aksara_header', array(
		'title' => __( 'Header', 'aksara' ),
		'panel' => 'aksara_panel',
	) );

	$wp_customize->add_setting( 'aksara_topbar_enabled', array(
		'default'           => $defaults['aksara_topbar_enabled'],
		'sanitize_callback' => 'aksara_sanitize_checkbox',
		'transport'         => 'refresh',
	) );
	$wp_customize->add_control( 'aksara_topbar_enabled', array(
		'section'     => 'aksara_header',
		'label'       => __( 'Show announcement bar', 'aksara' ),
		'description' => __( 'A single hairline strip above the header. Off by default.', 'aksara' ),
		'type'        => 'checkbox',
	) );

	$wp_customize->add_setting( 'aksara_topbar_text', array(
		'default'           => $defaults['aksara_topbar_text'],
		'sanitize_callback' => 'sanitize_text_field',
		'transport'         => 'postMessage',
	) );
	$wp_customize->add_control( 'aksara_topbar_text', array(
		'section'     => 'aksara_header',
		'label'       => __( 'Announcement text', 'aksara' ),
		'type'        => 'text',
	) );

	$wp_customize->add_setting( 'aksara_topbar_url', array(
		'default'           => $defaults['aksara_topbar_url'],
		'sanitize_callback' => 'esc_url_raw',
		'transport'         => 'refresh',
	) );
	$wp_customize->add_control( 'aksara_topbar_url', array(
		'section'     => 'aksara_header',
		'label'       => __( 'Announcement link (optional)', 'aksara' ),
		'description' => __( 'Leave empty to show the text without a link.', 'aksara' ),
		'type'        => 'url',
	) );

	/* --- Footer ------------------------------------------------------- */
	$wp_customize->add_section( 'aksara_footer', array(
		'title'       => __( 'Footer', 'aksara' ),
		'panel'       => 'aksara_panel',
		'description' => __( 'The footer link columns and the social row are ordinary WordPress menus — edit them under Menus, at the locations “Footer — Shop”, “Footer — Help”, “Footer — Company” and “Footer — Social”.', 'aksara' ),
	) );

	$wp_customize->add_setting( 'aksara_footer_cta_scope', array(
		'default'           => $defaults['aksara_footer_cta_scope'],
		'sanitize_callback' => 'aksara_sanitize_cta_scope',
		'transport'         => 'refresh',
	) );
	$wp_customize->add_control( 'aksara_footer_cta_scope', array(
		'section'     => 'aksara_footer',
		'label'       => __( 'Show the footer call to action', 'aksara' ),
		'description' => __( 'On shop pages this invitation competes with the product being viewed, which is why editorial-only is the default.', 'aksara' ),
		'type'        => 'select',
		'choices'     => array(
			'editorial' => __( 'Blog and article pages only (default)', 'aksara' ),
			'all'       => __( 'Every page', 'aksara' ),
			'off'       => __( 'Never', 'aksara' ),
		),
	) );

	foreach ( array(
		'aksara_footer_cta_text'  => __( 'Call to action text', 'aksara' ),
		'aksara_footer_cta_label' => __( 'Call to action link label', 'aksara' ),
	) as $key => $label ) {
		$wp_customize->add_setting( $key, array(
			'default'           => $defaults[ $key ],
			'sanitize_callback' => 'sanitize_text_field',
			'transport'         => 'postMessage',
		) );
		$wp_customize->add_control( $key, array(
			'section' => 'aksara_footer',
			'label'   => $label,
			'type'    => 'text',
		) );
	}

	$wp_customize->add_setting( 'aksara_footer_cta_url', array(
		'default'           => $defaults['aksara_footer_cta_url'],
		'sanitize_callback' => 'esc_url_raw',
		'transport'         => 'refresh',
	) );
	$wp_customize->add_control( 'aksara_footer_cta_url', array(
		'section'     => 'aksara_footer',
		'label'       => __( 'Call to action link', 'aksara' ),
		'description' => __( 'Leave empty to link to the font library automatically.', 'aksara' ),
		'type'        => 'url',
	) );

	foreach ( array(
		'aksara_footer_heading_shop'  => __( 'Column 2 heading', 'aksara' ),
		'aksara_footer_heading_help'  => __( 'Column 3 heading', 'aksara' ),
		'aksara_footer_heading_about' => __( 'Column 4 heading', 'aksara' ),
		'aksara_footer_note'          => __( 'Closing line', 'aksara' ),
	) as $key => $label ) {
		$wp_customize->add_setting( $key, array(
			'default'           => $defaults[ $key ],
			'sanitize_callback' => 'sanitize_text_field',
			'transport'         => 'postMessage',
		) );
		$wp_customize->add_control( $key, array(
			'section' => 'aksara_footer',
			'label'   => $label,
			'type'    => 'text',
		) );
	}

	/* --- Home --------------------------------------------------------- */
	$wp_customize->add_section( 'aksara_home', array(
		'title'       => __( 'Home hero', 'aksara' ),
		'panel'       => 'aksara_panel',
		'description' => __( 'These two were already read by the home page template but had never been registered here, so they could not be edited.', 'aksara' ),
	) );

	$wp_customize->add_setting( 'aksara_hero_title', array(
		'default'           => $defaults['aksara_hero_title'],
		'sanitize_callback' => 'sanitize_text_field',
		'transport'         => 'postMessage',
	) );
	$wp_customize->add_control( 'aksara_hero_title', array(
		'section' => 'aksara_home',
		'label'   => __( 'Headline', 'aksara' ),
		'type'    => 'text',
	) );

	$wp_customize->add_setting( 'aksara_hero_subtitle', array(
		'default'           => $defaults['aksara_hero_subtitle'],
		'sanitize_callback' => 'sanitize_text_field',
		'transport'         => 'postMessage',
	) );
	$wp_customize->add_control( 'aksara_hero_subtitle', array(
		'section' => 'aksara_home',
		'label'   => __( 'Sub-headline', 'aksara' ),
		'type'    => 'textarea',
	) );

	/* --- Pratinjau langsung -------------------------------------------
	 * Setting bertransport postMessage tidak memuat ulang halaman, jadi bagian
	 * yang berubah harus dirender ulang sendiri. Partial di sini sengaja
	 * dipasang PER KOMPONEN, bukan per teks, dan render_callback-nya memanggil
	 * template part YANG SAMA dengan yang dipakai halaman sungguhan.
	 *
	 * Alternatifnya — satu partial per setting dengan callback yang mencetak
	 * teksnya saja — terlihat lebih sederhana tapi salah: label ajakan footer
	 * berbagi elemen <a> dengan panahnya, jadi mengganti isi elemen itu dengan
	 * teks polos akan menghapus panah tersebut di pratinjau. Dan callback yang
	 * menyusun ulang markup itu sendiri berarti markup yang sama ditulis di dua
	 * tempat, yang cepat atau lambat akan berbeda.
	 *
	 * container_inclusive: true karena template part mencetak pembungkusnya
	 * sendiri; tanpa ini pembungkusnya akan bersarang dua kali setiap kali
	 * pratinjau menyegarkan bagian tersebut. */
	if ( isset( $wp_customize->selective_refresh ) ) {
		$components = array(
			'aksara_topbar' => array(
				'selector' => '.site-topbar',
				'part'     => 'template-parts/header/topbar',
				'settings' => array( 'aksara_topbar_text' ),
			),
			'aksara_footer_cta' => array(
				'selector' => '.editorial-footer-cta',
				'part'     => 'template-parts/footer/cta',
				'settings' => array( 'aksara_footer_cta_text', 'aksara_footer_cta_label' ),
			),
			'aksara_footer_menus' => array(
				'selector' => '.footer-grid',
				'part'     => 'template-parts/footer/menus',
				'settings' => array(
					'aksara_footer_heading_shop',
					'aksara_footer_heading_help',
					'aksara_footer_heading_about',
				),
			),
			'aksara_footer_bottom' => array(
				'selector' => '.footer-bottom',
				'part'     => 'template-parts/footer/bottom',
				'settings' => array( 'aksara_footer_note' ),
			),
		);

		foreach ( $components as $id => $component ) {
			$part = $component['part'];
			$wp_customize->selective_refresh->add_partial( $id, array(
				'selector'            => $component['selector'],
				'settings'            => $component['settings'],
				'container_inclusive' => true,
				'render_callback'     => static function () use ( $part ) {
					get_template_part( $part );
				},
			) );
		}

		/* Dua yang ini isinya memang cuma teks, tanpa markup di dalamnya, jadi
		 * mengganti isi elemennya sudah cukup. */
		foreach ( array(
			'aksara_hero_title'    => '.hero-headline',
			'aksara_hero_subtitle' => '.hero-sub',
		) as $setting => $selector ) {
			$wp_customize->selective_refresh->add_partial( $setting, array(
				'selector'        => $selector,
				'render_callback' => static function () use ( $setting ) {
					return esc_html( aksara_mod( $setting ) );
				},
			) );
		}
	}
}
add_action( 'customize_register', 'aksara_customize_register' );
