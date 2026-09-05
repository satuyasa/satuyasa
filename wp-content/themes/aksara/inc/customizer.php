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
		// Umum
		'aksara_contact_email'    => '',

		// Header
		'aksara_logo_height'      => '32',
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

		// License page — public presentation only; product/license records stay in the plugin.
		/* Nama merek dan foundry dulu dipatok langsung di template-license.php,
		 * di lima tempat. Artinya mengganti nama merek — hal yang lumrah
		 * terjadi — menuntut menyunting PHP, dan satu dari lima tempat pasti
		 * terlewat. Dijadikan setting supaya cukup diketik sekali. Nilai
		 * bawaannya persis seperti yang dulu dipatok, jadi tampilannya tidak
		 * berubah sama sekali sampai ada yang mengubahnya. */
		'aksara_license_brand'            => 'HiveGlyph',
		'aksara_license_foundry'          => 'Ekayasa',
		'aksara_license_website'          => 'https://www.hiveglyph.com',
		'aksara_license_eyebrow'          => __( 'HiveGlyph licensing guide', 'aksara' ),
		'aksara_license_intro'            => __( 'Clear rights for type used in real work. Choose the scope that matches your project, then contact us when your use falls outside the standard options.', 'aksara' ),
		'aksara_license_guide_title'      => __( 'Start with the output, not the font.', 'aksara' ),
		'aksara_license_guide_text'       => __( 'Use Desktop for static graphics, Webfont for one self-hosted domain, App for software embedding, ePub for digital publications, Server for server-side generation, and Extended when your project creates products for resale.', 'aksara' ),
		'aksara_license_catalogue_title'  => __( 'Six ways to put type to work.', 'aksara' ),
		'aksara_license_catalogue_note'   => __( 'The summaries below explain the standard HiveGlyph scopes. The license attached to your purchase controls the permitted use.', 'aksara' ),
		'aksara_license_ip_text'          => __( 'The Font Software remains the intellectual property of Ekayasa. HiveGlyph is authorized to distribute the Font Software and grant usage licenses. Redistribution outside the license terms is prohibited.', 'aksara' ),
		'aksara_license_contact_title'    => __( 'Need a different scope?', 'aksara' ),
		'aksara_license_contact_text'     => __( 'For upgrades, larger teams, higher traffic, multi-app use, exclusive rights, or unlisted usage, contact HiveGlyph before purchasing.', 'aksara' ),
		'aksara_license_contact_label'    => __( 'Contact HiveGlyph', 'aksara' ),
		'aksara_license_desktop_overview' => __( 'Installation and use on desktop devices for personal and commercial projects.', 'aksara' ),
		'aksara_license_desktop_allowed' => "Installation on up to 3 devices within one organization or household.\nCreation of static designs such as logos, posters, brochures, and packaging.\nUse in social media graphics and marketing materials.",
		'aksara_license_desktop_prohibited' => "Embedding the font in software, games, or apps.\nDistributing editable design files containing the font.\nSharing or sublicensing the font with third parties.",
		'aksara_license_desktop_limitations' => __( 'For larger teams or more devices, an enterprise license is required.', 'aksara' ),
		'aksara_license_webfont_overview' => __( 'Usage of the font on one self-hosted website domain.', 'aksara' ),
		'aksara_license_webfont_allowed' => "Usage on one self-hosted domain.\nUp to 10,000 monthly pageviews.\nStyling headings, body text, and website design elements.",
		'aksara_license_webfont_prohibited' => "Hosting or providing the font for public download.\nEmbedding the font in email templates.\nUsing it on multiple domains without proper licensing.",
		'aksara_license_webfont_limitations' => __( 'For traffic above 10,000 monthly pageviews, an extended or enterprise license is required.', 'aksara' ),
		'aksara_license_app_overview' => __( 'Embedding the font in a single mobile or desktop application.', 'aksara' ),
		'aksara_license_app_allowed' => "Use in one mobile or desktop application (iOS, Android, or WebApp).\nUnlimited downloads for that single application.\nSecure embedding where font extraction is not possible.",
		'aksara_license_app_prohibited' => "Using the font in multiple apps without additional licensing.\nProviding the font as a downloadable asset within the application.\nRedistributing the font file outside the application.",
		'aksara_license_app_limitations' => __( 'Contact HiveGlyph for multi-app or enterprise embedding options.', 'aksara' ),
		'aksara_license_epub_overview' => __( 'Embedding the font in digital publications distributed as ePub files.', 'aksara' ),
		'aksara_license_epub_allowed' => "Embedding the font in ePub publications you create.\nDistributing the finished ePub to readers through your chosen store or platform.\nUsing the font for text and display typography inside the publication.",
		'aksara_license_epub_prohibited' => "Distributing the font as a standalone file.\nUsing the embedded font in unrelated publications without a valid license.\nAllowing readers to extract or reuse the font as a design asset.",
		'aksara_license_epub_limitations' => __( 'For subscription libraries, large catalogues, or app-based reading platforms, contact us for a custom license.', 'aksara' ),
		'aksara_license_server_overview' => __( 'Using the font on a server to generate rendered documents, images, or other fixed outputs.', 'aksara' ),
		'aksara_license_server_allowed' => "Server-side rendering of fixed visual outputs such as PDFs, images, and documents.\nUse within one owned or operated service.\nServing the generated output to your customers or end users.",
		'aksara_license_server_prohibited' => "Exposing the font file or font-generation access to end users.\nAllowing users to download or install the Font Software.\nUsing the font across unrelated services without additional licensing.",
		'aksara_license_server_limitations' => __( 'For SaaS platforms, high-volume generation, or multiple services, contact us for a custom license.', 'aksara' ),
		'aksara_license_extended_overview' => __( 'Expanded rights for products for resale, including the rights of Desktop, Webfont, and App licenses.', 'aksara' ),
		'aksara_license_extended_allowed' => "All rights granted under Desktop, Webfont, and App licenses.\nCreation of products for resale, including templates, merchandise, and digital goods.\nUse in large-scale commercial projects and campaigns.",
		'aksara_license_extended_prohibited' => "Selling or distributing the font as a standalone product.\nSharing the font with third parties outside the licensed organization.",
		'aksara_license_extended_limitations' => __( 'For exclusive rights or large-scale usage such as TV, films, or SaaS platforms, contact us for a custom license.', 'aksara' ),
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

/**
 * Tinggi logo, dijepit 16-48px.
 *
 * Batas bawahnya bukan basa-basi: di bawah 16px logo jadi lebih kecil daripada
 * wordmark teks yang digantikannya, dan itu justru masalah yang membuat kontrol
 * ini ada. Batas atasnya juga: di atas 32px header memang ikut meninggi (32px
 * adalah seluruh kotak isi .site-header-inner), dan 48px adalah titik di mana
 * header masih terbaca sebagai bilah tipis, bukan panel.
 */
function aksara_sanitize_logo_height( $value ) {
	return (string) min( 48, max( 16, absint( $value ) ) );
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

	$wp_customize->add_setting( 'aksara_logo_height', array(
		'default'           => $defaults['aksara_logo_height'],
		'sanitize_callback' => 'aksara_sanitize_logo_height',
		'transport'         => 'refresh',
	) );
	$wp_customize->add_control( 'aksara_logo_height', array(
		'section'     => 'aksara_header',
		'label'       => __( 'Logo height', 'aksara' ),
		'description' => __( 'Only applies when a logo image is set. Up to 32px the header keeps its height; above that it grows. If the logo still looks small at 32px, the file itself probably has empty space around it — trimming that in the image works better than making it taller.', 'aksara' ),
		'type'        => 'number',
		'input_attrs' => array( 'min' => 16, 'max' => 48, 'step' => 1 ),
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

	/* --- Kontak -------------------------------------------------------- */
	$wp_customize->add_section( 'aksara_contact', array(
		'title'       => __( 'Contact form', 'aksara' ),
		'panel'       => 'aksara_panel',
		'description' => __( 'Where messages from the contact form are sent. The form itself is placed by adding the shortcode [aksara_contact_form] to any page.', 'aksara' ),
	) );

	$wp_customize->add_setting( 'aksara_contact_email', array(
		'default'           => $defaults['aksara_contact_email'],
		'sanitize_callback' => 'sanitize_email',
		'transport'         => 'refresh',
	) );
	$wp_customize->add_control( 'aksara_contact_email', array(
		'section'     => 'aksara_contact',
		'label'       => __( 'Send messages to', 'aksara' ),
		'description' => __( 'Leave empty to use the site administration email.', 'aksara' ),
		'type'        => 'email',
	) );

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

	/* --- License page -------------------------------------------------- */
	$wp_customize->add_section( 'aksara_license_page', array(
		'title'       => __( 'License page', 'aksara' ),
		'panel'       => 'aksara_panel',
		'description' => __( 'Edit the public presentation of the HiveGlyph license guide. These controls do not change WooCommerce products, purchase rights, or the license engine. Use one line per bullet in the Allowed Uses and Prohibited Uses fields.', 'aksara' ),
	) );
	/* Tiga ruas identitas didaftarkan TERPISAH dari prosa di bawahnya. Loop
	 * prosa memakai type "textarea" dan sanitize_textarea_field — masuk akal
	 * untuk paragraf, tapi bukan untuk nama merek (satu baris) apalagi untuk
	 * URL, yang butuh esc_url_raw supaya skema aneh tidak pernah tersimpan.
	 * Keluarannya memang sudah lewat esc_url(), jadi ini bukan lubang XSS —
	 * tapi menyimpan nilai yang tidak sah lalu membuangnya saat mencetak
	 * berarti penyunting melihat kolomnya terisi sementara halamannya kosong,
	 * tanpa penjelasan apa pun. */
	$license_identity_fields = array(
		'aksara_license_brand'   => array( __( 'Brand name', 'aksara' ), 'text', 'sanitize_text_field' ),
		'aksara_license_foundry' => array( __( 'Type foundry name', 'aksara' ), 'text', 'sanitize_text_field' ),
		'aksara_license_website' => array( __( 'Website URL', 'aksara' ), 'url', 'esc_url_raw' ),
	);
	foreach ( $license_identity_fields as $key => $field ) {
		list( $label, $type, $sanitize ) = $field;
		$wp_customize->add_setting( $key, array( 'default' => $defaults[ $key ], 'sanitize_callback' => $sanitize, 'transport' => 'refresh' ) );
		$wp_customize->add_control( $key, array( 'section' => 'aksara_license_page', 'label' => $label, 'type' => $type ) );
	}

	$license_text_fields = array(
		'aksara_license_eyebrow' => __( 'Eyebrow', 'aksara' ),
		'aksara_license_intro' => __( 'Introductory text', 'aksara' ),
		'aksara_license_guide_title' => __( 'Guide heading', 'aksara' ),
		'aksara_license_guide_text' => __( 'Guide text', 'aksara' ),
		'aksara_license_catalogue_title' => __( 'Catalogue heading', 'aksara' ),
		'aksara_license_catalogue_note' => __( 'Catalogue note', 'aksara' ),
		'aksara_license_ip_text' => __( 'Intellectual Property text', 'aksara' ),
		'aksara_license_contact_title' => __( 'Contact heading', 'aksara' ),
		'aksara_license_contact_text' => __( 'Contact text', 'aksara' ),
		'aksara_license_contact_label' => __( 'Contact button label', 'aksara' ),
	);
	foreach ( $license_text_fields as $key => $label ) {
		$wp_customize->add_setting( $key, array( 'default' => $defaults[ $key ], 'sanitize_callback' => 'sanitize_textarea_field', 'transport' => 'refresh' ) );
		$wp_customize->add_control( $key, array( 'section' => 'aksara_license_page', 'label' => $label, 'type' => 'textarea' ) );
	}
	foreach ( array( 'desktop', 'webfont', 'app', 'epub', 'server', 'extended' ) as $license_key ) {
		foreach ( array( 'overview', 'allowed', 'prohibited', 'limitations' ) as $part ) {
			$key = 'aksara_license_' . $license_key . '_' . $part;
			$wp_customize->add_setting( $key, array( 'default' => $defaults[ $key ], 'sanitize_callback' => 'sanitize_textarea_field', 'transport' => 'refresh' ) );
			$wp_customize->add_control( $key, array( 'section' => 'aksara_license_page', 'label' => ucfirst( $license_key ) . ' — ' . ucfirst( $part ), 'type' => 'textarea' ) );
		}
	}

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

/**
 * Satu-satunya setting di sini yang berujung ke CSS, bukan ke teks.
 *
 * Dicetak sebagai custom property, bukan aturan .custom-logo utuh, supaya
 * style.css tetap satu-satunya tempat yang tahu bentuk aturannya — di sini
 * cuma angkanya. Dan tidak dicetak sama sekali kalau nilainya masih bawaan,
 * jadi situs yang tidak mengubah apa pun tidak mendapat <style> tambahan.
 */
function aksara_logo_height_css() {
	$height   = aksara_mod( 'aksara_logo_height' );
	$defaults = aksara_mod_defaults();

	if ( $height === $defaults['aksara_logo_height'] ) {
		return;
	}

	printf(
		'<style id="aksara-logo-height">:root{--aksara-logo-height:%dpx}</style>' . "\n",
		(int) aksara_sanitize_logo_height( $height )
	);
}
add_action( 'wp_head', 'aksara_logo_height_css' );
