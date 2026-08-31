<?php
/**
 * Pengaturan Customizer tema.
 *
 * @package Satuyasa
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Daftarkan panel, section, setting, dan control Customizer.
 *
 * @param WP_Customize_Manager $wp_customize Instance Customizer.
 */
function satuyasa_customize_register( $wp_customize ) {

	$wp_customize->add_setting( 'blogdescription', array(
		'transport' => 'postMessage',
	) );

	// Section khusus hero halaman depan.
	$wp_customize->add_section( 'satuyasa_hero_section', array(
		'title'       => __( 'Hero Halaman Depan', 'satuyasa' ),
		'priority'    => 30,
		'description' => __( 'Atur judul, subjudul, dan tombol pada bagian atas halaman depan.', 'satuyasa' ),
	) );

	$wp_customize->add_setting( 'satuyasa_hero_title', array(
		'default'           => get_bloginfo( 'name' ),
		'sanitize_callback' => 'sanitize_text_field',
		'transport'         => 'postMessage',
	) );
	$wp_customize->add_control( 'satuyasa_hero_title', array(
		'label'   => __( 'Judul Hero', 'satuyasa' ),
		'section' => 'satuyasa_hero_section',
		'type'    => 'text',
	) );

	$wp_customize->add_setting( 'satuyasa_hero_subtitle', array(
		'default'           => get_bloginfo( 'description' ),
		'sanitize_callback' => 'sanitize_text_field',
		'transport'         => 'postMessage',
	) );
	$wp_customize->add_control( 'satuyasa_hero_subtitle', array(
		'label'   => __( 'Subjudul Hero', 'satuyasa' ),
		'section' => 'satuyasa_hero_section',
		'type'    => 'text',
	) );

	$wp_customize->add_setting( 'satuyasa_hero_button_text', array(
		'default'           => __( 'Hubungi Kami', 'satuyasa' ),
		'sanitize_callback' => 'sanitize_text_field',
	) );
	$wp_customize->add_control( 'satuyasa_hero_button_text', array(
		'label'   => __( 'Teks Tombol', 'satuyasa' ),
		'section' => 'satuyasa_hero_section',
		'type'    => 'text',
	) );

	$wp_customize->add_setting( 'satuyasa_hero_button_url', array(
		'default'           => '#',
		'sanitize_callback' => 'esc_url_raw',
	) );
	$wp_customize->add_control( 'satuyasa_hero_button_url', array(
		'label'   => __( 'Tautan Tombol', 'satuyasa' ),
		'section' => 'satuyasa_hero_section',
		'type'    => 'url',
	) );

	// Section warna aksen.
	$wp_customize->add_section( 'satuyasa_color_section', array(
		'title'    => __( 'Warna Aksen', 'satuyasa' ),
		'priority' => 40,
	) );

	$wp_customize->add_setting( 'satuyasa_accent_color', array(
		'default'           => '#2563eb',
		'sanitize_callback' => 'sanitize_hex_color',
		'transport'         => 'postMessage',
	) );
	$wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, 'satuyasa_accent_color', array(
		'label'   => __( 'Warna Aksen', 'satuyasa' ),
		'section' => 'satuyasa_color_section',
	) ) );
}
add_action( 'customize_register', 'satuyasa_customize_register' );

/**
 * Cetak CSS kustom berdasarkan warna aksen yang dipilih.
 */
function satuyasa_customize_css() {
	$accent = get_theme_mod( 'satuyasa_accent_color', '#2563eb' );
	if ( ! $accent ) {
		return;
	}
	?>
	<style type="text/css">
		:root { --satuyasa-color-accent: <?php echo esc_html( $accent ); ?>; }
	</style>
	<?php
}
add_action( 'wp_head', 'satuyasa_customize_css' );

/**
 * Muat script untuk preview langsung Customizer.
 */
function satuyasa_customize_preview_js() {
	wp_enqueue_script( 'satuyasa-customizer', SATUYASA_URI . '/assets/js/customizer.js', array( 'customize-preview' ), SATUYASA_VERSION, true );
}
add_action( 'customize_preview_init', 'satuyasa_customize_preview_js' );
